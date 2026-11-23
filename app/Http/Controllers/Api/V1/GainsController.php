<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Audit\AuditLogger;
use App\Domain\Gains\Actions\ManageWithdrawals;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Enums\PayoutStatus;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\PayoutAccount;
use App\Domain\Gains\Support\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Gains\UpdatePayoutAccountRequest;
use App\Http\Resources\V1\CommissionResource;
use App\Http\Resources\V1\PayoutAccountResource;
use App\Http\Resources\V1\PayoutResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

#[Group('Mes gains', 'Portefeuille : gains crédités à chaque relais diffusé, coordonnées Mobile Money, demandes de retrait et historique (MOB-26).', weight: 3)]
class GainsController extends Controller
{
    /**
     * Mes gains.
     *
     * Gains crédités sur le portefeuille (un par relais diffusé), le plus récent d'abord (20 par page) : montant, relais.
     * Les gains annulés ne sont jamais renvoyés. Pour le solde et les retraits : `GET /me/wallet`.
     * `meta.totals.earned` : total gagné. `meta.gains_eligible` : identité vérifiée (condition pour retirer).
     * `meta.payout_account_set` : coordonnées Mobile Money enregistrées.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $commissions = Commission::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->with(['relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->latest('id')
            ->paginate(20);

        $totals = Commission::query()->where('user_id', $user->id)
            ->selectRaw('status, sum(amount) as total')->groupBy('status')->pluck('total', 'status');

        return CommissionResource::collection($commissions)->additional(['meta' => [
            'totals' => [
                'earned' => (int) ($totals[CommissionStatus::Due->value] ?? 0) + (int) ($totals[CommissionStatus::Paid->value] ?? 0),
                'due' => (int) ($totals[CommissionStatus::Due->value] ?? 0),
                'paid' => (int) ($totals[CommissionStatus::Paid->value] ?? 0),
                'currency' => 'XOF',
            ],
            'gains_eligible' => $user->isEligibleForGains(),
            'payout_account_set' => $user->payoutAccount()->exists(),
        ]]);
    }

    /**
     * Mon portefeuille.
     *
     * Solde du portefeuille (MOB-26) : `available` (retirable), `pending` (retrait en cours), `withdrawn` (déjà reçu),
     * `earned` (total gagné), en FCFA. Chaque relais diffusé crédite le portefeuille. `min_withdrawal` : retrait minimum.
     * `can_withdraw` et `blockers` disent si un retrait est possible et, sinon, pourquoi (identité à vérifier,
     * coordonnées Mobile Money à enregistrer, retrait déjà en cours, solde insuffisant) : messages prêts à afficher.
     */
    public function wallet(Request $request, Wallet $wallet): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $blockers = $wallet->blockers($user);
        $account = $user->payoutAccount;

        return response()->json(['data' => [
            ...$wallet->summary($user),
            'currency' => 'XOF',
            'min_withdrawal' => $wallet->minimumWithdrawal(),
            'can_withdraw' => $blockers === [],
            'blockers' => array_map(fn (string $key, string $message): array => ['code' => $key, 'message' => $message], array_keys($blockers), array_values($blockers)),
            'payout_account' => $account === null ? null : (new PayoutAccountResource($account))->resolve($request),
            'pending_withdrawal' => ($pending = $user->payouts()->where('status', PayoutStatus::Requested->value)->latest('id')->first()) === null ? null : (new PayoutResource($pending))->resolve($request),
        ]]);
    }

    /**
     * Demander un retrait.
     *
     * Retire `amount` FCFA du portefeuille vers le compte Mobile Money enregistré (`PUT /me/payout-account`).
     * Conditions : identité vérifiée, coordonnées enregistrées, aucun retrait en cours, montant entre le minimum et le
     * solde disponible (422 sinon, avec le motif dans `errors.amount`). L'administration paie ensuite par Mobile Money :
     * notification `gains.paid` (ou `gains.withdrawal_rejected`, la somme revient alors dans le portefeuille).
     */
    #[BodyParameter('amount', 'Montant à retirer, en FCFA (entier).', type: 'int', example: 5000)]
    public function requestWithdrawal(Request $request, ManageWithdrawals $withdrawals): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['amount' => ['required', 'integer', 'min:1']]);

        try {
            $payout = $withdrawals->request($user, $request->integer('amount'));
        } catch (CommissionRefused $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }

    /**
     * Mes retraits.
     *
     * Historique des retraits du portefeuille, le plus récent d'abord (20 par page) : montant, statut
     * (`requested` en attente, `paid` payé avec sa référence, `rejected` refusé avec son motif), opérateur, numéro.
     * `meta.total_received` : total déjà reçu en FCFA.
     */
    public function payouts(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return PayoutResource::collection($user->payouts()->latest('id')->paginate(20))->additional(['meta' => [
            'total_received' => (int) $user->payouts()->where('status', PayoutStatus::Paid->value)->sum('amount'),
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
