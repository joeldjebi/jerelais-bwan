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
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Group('Mes gains', 'Portefeuille : gains accordés par l’administration, numéros Mobile Money (deux au plus), retrait immédiat et historique (MOB-26).', weight: 3)]
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
     * `payout_accounts` : numéros Mobile Money enregistrés (deux au plus), à proposer au moment du retrait ;
     * `payout_account` : le premier d'entre eux (numéro par défaut).
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
            'payout_accounts' => PayoutAccountResource::collection($user->payoutAccounts)->resolve($request),
            'pending_withdrawal' => ($pending = $user->payouts()->where('status', PayoutStatus::Requested->value)->latest('id')->first()) === null ? null : (new PayoutResource($pending))->resolve($request),
        ]]);
    }

    /**
     * Retirer.
     *
     * Retire `amount` FCFA du portefeuille vers l'un des numéros Mobile Money enregistrés (`payout_account` : son
     * `uuid` ; sans lui, le premier numéro enregistré). Le retrait est envoyé immédiatement : **201** avec
     * `status: paid`, la `reference` de la transaction et le numéro crédité, notification `gains.paid`.
     * Pendant la phase de test, l'envoi est **simulé** (`provider: simulation`, référence `SIM-…`, aucun argent envoyé).
     * Si le prestataire échoue, le retrait reste `requested` et l'administration le traite depuis la console.
     * Conditions : identité vérifiée, au moins un numéro enregistré, aucun retrait en attente, montant entre le minimum
     * et le solde disponible (422 sinon, avec le motif dans `errors.amount`).
     */
    #[BodyParameter('amount', 'Montant à retirer, en FCFA (entier).', type: 'int', example: 5000)]
    #[BodyParameter('payout_account', 'UUID du numéro Mobile Money à créditer (facultatif : premier numéro enregistré).', required: false, type: 'string', example: '9d6f2c1e-1a2b-4c3d-8e9f-0a1b2c3d4e5f')]
    public function requestWithdrawal(Request $request, ManageWithdrawals $withdrawals): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['amount' => ['required', 'integer', 'min:1'], 'payout_account' => ['nullable', 'uuid']]);

        try {
            $payout = $withdrawals->request($user, $request->integer('amount'), $request->filled('payout_account') ? $request->string('payout_account')->toString() : null);
        } catch (CommissionRefused $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }

    /**
     * Mes retraits.
     *
     * Historique des retraits du portefeuille, le plus récent d'abord (20 par page) : montant, statut
     * (`requested` en attente, `paid` payé avec sa référence, `rejected` refusé avec son motif), opérateur
     * (`method_label`) et numéro crédité (`phone`, tel qu'il était au moment du retrait), `provider`.
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
     * Mes numéros de retrait.
     *
     * Numéros Mobile Money enregistrés (deux au plus), le premier enregistré d'abord : `uuid` (à envoyer au retrait),
     * opérateur, numéro, titulaire. `meta.max` : nombre maximum ; `meta.methods` : opérateurs proposés.
     */
    public function payoutAccounts(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return PayoutAccountResource::collection($user->payoutAccounts)->additional(['meta' => ['max' => PayoutAccount::MAX_PER_USER, 'methods' => self::methods()]]);
    }

    /**
     * Ajouter un numéro de retrait.
     *
     * Opérateur (`method` : `wave`, `orange_money`, `mtn_momo`, `moov_money`), numéro (`phone`, ivoirien, par exemple
     * `0707070707`) et titulaire (`holder_name`). **201**. 422 si le Relais a déjà deux numéros (`errors.phone`).
     */
    public function storePayoutAccount(UpdatePayoutAccountRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->payoutAccounts()->count() >= PayoutAccount::MAX_PER_USER) {
            throw ValidationException::withMessages(['phone' => __('gains.errors.payout_accounts_full', ['max' => PayoutAccount::MAX_PER_USER])]);
        }

        $account = PayoutAccount::query()->create(['user_id' => $user->id, ...$this->accountFields($request)]);
        $audit->record('payout_account.created', $user, ['method' => $account->method->value, 'phone' => $request->phone()->masked()], actor: $user);

        return (new PayoutAccountResource($account))->response()->setStatusCode(201);
    }

    /**
     * Modifier un numéro de retrait.
     *
     * Mêmes champs que l'ajout. Les retraits déjà faits gardent l'ancien numéro. 404 si le numéro n'est pas le sien.
     */
    public function updatePayoutAccountByUuid(UpdatePayoutAccountRequest $request, string $payoutAccount, AuditLogger $audit): JsonResource
    {
        $account = $this->ownAccount($request, $payoutAccount);
        $account->update($this->accountFields($request));
        $audit->record('payout_account.updated', $account->user, ['method' => $account->method->value, 'phone' => $request->phone()->masked()], actor: $account->user);

        return new PayoutAccountResource($account);
    }

    /**
     * Supprimer un numéro de retrait.
     *
     * **204**. Les retraits déjà faits gardent le numéro crédité. 404 si le numéro n'est pas le sien.
     */
    public function destroyPayoutAccount(Request $request, string $payoutAccount, AuditLogger $audit): Response
    {
        $account = $this->ownAccount($request, $payoutAccount);
        $account->delete();
        $audit->record('payout_account.deleted', $account->user, ['method' => $account->method->value], actor: $account->user);

        return response()->noContent();
    }

    /**
     * Mes coordonnées de paiement (numéro par défaut).
     *
     * Ancienne route, conservée : le premier numéro enregistré, ou `data: null`. Préférer `GET /me/payout-accounts`.
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
     * Enregistrer mes coordonnées de paiement (numéro par défaut).
     *
     * Ancienne route, conservée : crée ou remplace le premier numéro enregistré. Préférer
     * `POST /me/payout-accounts` et `PUT /me/payout-accounts/{uuid}`.
     */
    public function updatePayoutAccount(UpdatePayoutAccountRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $account = $user->payoutAccount;
        $fields = $this->accountFields($request);

        if ($account === null) {
            $account = PayoutAccount::query()->create(['user_id' => $user->id, ...$fields]);
        } else {
            $account->update($fields);
        }

        $audit->record('payout_account.updated', $user, ['method' => $account->method->value, 'phone' => $request->phone()->masked()], actor: $user);

        return response()->json([
            'data' => (new PayoutAccountResource($account))->resolve($request),
            'meta' => ['methods' => self::methods()],
        ]);
    }

    /**
     * @return array{method: PaymentMethod, phone: string, holder_name: string}
     */
    private function accountFields(UpdatePayoutAccountRequest $request): array
    {
        return [
            'method' => PaymentMethod::from($request->string('method')->toString()),
            'phone' => $request->phone()->e164,
            'holder_name' => trim($request->string('holder_name')->toString()),
        ];
    }

    private function ownAccount(Request $request, string $uuid): PayoutAccount
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(Str::isUuid($uuid), 404);

        return $user->payoutAccounts()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function methods(): array
    {
        return array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PayoutAccount::methods());
    }
}
