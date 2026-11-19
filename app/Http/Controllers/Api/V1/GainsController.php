<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Audit\AuditLogger;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\PayoutAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Gains\UpdatePayoutAccountRequest;
use App\Http\Resources\V1\CommissionResource;
use App\Http\Resources\V1\PayoutAccountResource;
use App\Http\Resources\V1\PayoutResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Mes gains', 'Commissions gagnées, coordonnées Mobile Money pour être payé et historique des paiements reçus (MOB-26).', weight: 3)]
class GainsController extends Controller
{
    /**
     * Mes commissions.
     *
     * Commissions gagnées pour mes relais validés et diffusés, la plus récente d'abord (20 par page).
     * `status=due` : à recevoir ; `status=paid` : déjà reçues. Les commissions annulées ne sont jamais renvoyées.
     * `meta.totals` : `due` (à recevoir) et `paid` (déjà reçu), en FCFA. `meta.gains_eligible` : identité vérifiée,
     * condition pour être payé. `meta.payout_account_set` : coordonnées Mobile Money enregistrées.
     */
    #[QueryParameter('status', 'Filtre : `due` (à recevoir) ou `paid` (reçues). Sans filtre : toutes.', type: 'string', example: 'due')]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $status = CommissionStatus::tryFrom($request->string('status')->toString());

        $commissions = Commission::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->when($status !== null && $status !== CommissionStatus::Cancelled, fn ($query) => $query->where('status', $status?->value))
            ->with(['relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->latest('id')
            ->paginate(20);

        $totals = Commission::query()->where('user_id', $user->id)
            ->selectRaw('status, sum(amount) as total')->groupBy('status')->pluck('total', 'status');

        return CommissionResource::collection($commissions)->additional(['meta' => [
            'totals' => [
                'due' => (int) ($totals[CommissionStatus::Due->value] ?? 0),
                'paid' => (int) ($totals[CommissionStatus::Paid->value] ?? 0),
                'currency' => 'XOF',
            ],
            'gains_eligible' => $user->isEligibleForGains(),
            'payout_account_set' => $user->payoutAccount()->exists(),
        ]]);
    }

    /**
     * Mes paiements reçus.
     *
     * Historique des versements Mobile Money reçus, le plus récent d'abord (20 par page) : montant, opérateur,
     * référence de la transaction, date et commissions soldées. `meta.total_received` : total reçu en FCFA.
     */
    public function payouts(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $payouts = $user->payouts()
            ->withCount('commissions')
            ->with(['commissions.relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->latest('paid_at')
            ->paginate(20);

        return PayoutResource::collection($payouts)->additional(['meta' => [
            'total_received' => (int) $user->payouts()->sum('amount'),
            'currency' => 'XOF',
        ]]);
    }

    /**
     * Mes coordonnées de paiement.
     *
     * Opérateur Mobile Money, numéro et nom du titulaire où le Relais veut être payé. `data: null` tant qu'elles ne
     * sont pas enregistrées. `meta.methods` : opérateurs proposés (`value` à envoyer, `label` à afficher).
     */
    public function payoutAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $user->payoutAccount;

        return response()->json([
            'data' => $account === null ? null : (new PayoutAccountResource($account))->resolve($request),
            'meta' => ['methods' => self::methods()],
        ]);
    }

    /**
     * Enregistrer mes coordonnées de paiement.
     *
     * Opérateur (`method` : `wave`, `orange_money`, `mtn_momo`, `moov_money`), numéro Mobile Money (`phone`, ivoirien,
     * par exemple `0707070707`) et nom du titulaire (`holder_name`). Remplace les coordonnées précédentes.
     * L'administration paie sur ce numéro ; la modification est inscrite au journal d'audit.
     */
    public function updatePayoutAccount(UpdatePayoutAccountRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $method = PaymentMethod::from($request->string('method')->toString());

        $account = PayoutAccount::query()->updateOrCreate(['user_id' => $user->id], [
            'method' => $method,
            'phone' => $request->phone()->e164,
            'holder_name' => trim($request->string('holder_name')->toString()),
        ]);

        $audit->record('payout_account.updated', $user, ['method' => $method->value, 'phone' => $request->phone()->masked()], actor: $user);

        return response()->json([
            'data' => (new PayoutAccountResource($account))->resolve($request),
            'meta' => ['methods' => self::methods()],
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function methods(): array
    {
        return array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PayoutAccount::methods());
    }
}
