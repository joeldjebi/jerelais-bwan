<?php

namespace App\Livewire\Console\Gains;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Gains\Actions\CancelCommission;
use App\Domain\Gains\Actions\ManageWithdrawals;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Enums\PayoutStatus;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\Payout;
use App\Domain\Gains\Support\Wallet;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Paiements des Relais (ADM-27) : demandes de retrait du portefeuille à payer par Mobile Money (ou à refuser),
 * historique des retraits, soldes des portefeuilles (avec annulation motivée d'un gain), gains annulés.
 *
 * @property-read LengthAwarePaginator<int, Payout> $requests
 * @property-read LengthAwarePaginator<int, Payout> $history
 * @property-read LengthAwarePaginator<int, User> $balances
 * @property-read LengthAwarePaginator<int, Commission> $cancelled
 * @property-read array{pending_total: int, pending_count: int, paid_month: int, balances_total: int} $stats
 * @property-read Payout|null $payout
 * @property-read User|null $holder
 * @property-read Collection<int, Commission> $holderCommissions
 */
#[Title('Paiements')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'demandes')]
    public string $tab = 'demandes';

    #[Locked]
    public ?string $payoutUuid = null;

    public bool $paying = false;

    public bool $rejecting = false;

    public string $method = 'wave';

    public string $phone = '';

    public string $reference = '';

    public string $rejectReason = '';

    #[Locked]
    public ?string $holderUuid = null;

    public bool $viewing = false;

    #[Locked]
    public ?string $cancelUuid = null;

    public bool $cancelling = false;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatedTab(): void
    {
        $this->resetPage('liste');
    }

    public function openPay(string $uuid): void
    {
        $this->selectPayout($uuid);
        $this->method = $this->payout?->method->value ?? PaymentMethod::Wave->value;
        $this->phone = (string) $this->payout?->phone;
        $this->reference = '';
        $this->paying = true;
    }

    public function openReject(string $uuid): void
    {
        $this->selectPayout($uuid);
        $this->rejectReason = '';
        $this->rejecting = true;
    }

    public function pay(ManageWithdrawals $withdrawals): void
    {
        $this->authorizeAccess();
        $this->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'reference' => ['required', 'string', 'max:120'],
        ], attributes: ['method' => __('moyen de paiement'), 'phone' => __('numéro'), 'reference' => __('référence de la transaction')]);

        $payout = $this->payout;
        abort_if($payout === null, 404);

        try {
            $paid = $withdrawals->pay($payout, $this->admin(), $this->reference, PaymentMethod::from($this->method), $this->phone);
        } catch (CommissionRefused $exception) {
            $this->addError('reference', $exception->getMessage());

            return;
        }

        $this->paying = false;
        $this->refreshLists();
        $this->dispatch('toast', message: __('Retrait de :amount payé. Le Relais est prévenu.', ['amount' => Wallet::fcfa($paid->amount)]));
    }

    public function reject(ManageWithdrawals $withdrawals): void
    {
        $this->authorizeAccess();
        $this->validate(['rejectReason' => ['required', 'string', 'max:500']], attributes: ['rejectReason' => __('motif')]);

        $payout = $this->payout;
        abort_if($payout === null, 404);

        try {
            $withdrawals->reject($payout, $this->admin(), $this->rejectReason);
        } catch (CommissionRefused $exception) {
            $this->addError('rejectReason', $exception->getMessage());

            return;
        }

        $this->rejecting = false;
        $this->refreshLists();
        $this->dispatch('toast', message: __('Retrait refusé : la somme revient dans le portefeuille du Relais.'));
    }

    public function openHolder(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->holderUuid = $uuid;
        unset($this->holder, $this->holderCommissions);
        abort_if($this->holder === null, 404);
        $this->viewing = true;
    }

    public function askCancel(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->resetErrorBag();
        $this->cancelUuid = $uuid;
        $this->cancelReason = '';
        $this->cancelling = true;
    }

    public function cancel(CancelCommission $cancel): void
    {
        $this->authorizeAccess();
        $this->validate(['cancelReason' => ['required', 'string', 'max:500']], attributes: ['cancelReason' => __('motif')]);
        $commission = Commission::query()->where('uuid', (string) $this->cancelUuid)->firstOrFail();

        try {
            $cancel->handle($commission, $this->admin(), $this->cancelReason);
        } catch (CommissionRefused $exception) {
            $this->addError('cancelReason', $exception->getMessage());

            return;
        }

        $this->cancelling = false;
        $this->refreshLists();
        unset($this->holderCommissions);
        $this->dispatch('toast', message: __('Gain annulé.'));
    }

    /**
     * @return LengthAwarePaginator<int, Payout>
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return Payout::query()->where('status', PayoutStatus::Requested->value)
            ->with(['user:id,uuid,name,phone', 'user.identityVerification:id,user_id,status'])
            ->oldest('requested_at')
            ->paginate(20, pageName: 'liste');
    }

    /**
     * @return LengthAwarePaginator<int, Payout>
     */
    #[Computed]
    public function history(): LengthAwarePaginator
    {
        return Payout::query()->whereIn('status', [PayoutStatus::Paid->value, PayoutStatus::Rejected->value])
            ->with(['user:id,uuid,name', 'payer:id,name'])
            ->latest('updated_at')
            ->paginate(30, pageName: 'liste');
    }

    /**
     * Portefeuilles crédités, le plus gros gain total d'abord.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function balances(): LengthAwarePaginator
    {
        return User::query()
            ->whereHas('commissions', fn ($query) => $query->whereIn('status', [CommissionStatus::Due->value, CommissionStatus::Paid->value]))
            ->withSum(['commissions as earned_total' => fn ($query) => $query->whereIn('status', [CommissionStatus::Due->value, CommissionStatus::Paid->value])], 'amount')
            ->withSum(['payouts as withdrawn_total' => fn ($query) => $query->whereIn('status', [PayoutStatus::Requested->value, PayoutStatus::Paid->value])], 'amount')
            ->with(['relaisProfile' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'user_id', 'display_name', 'number'), 'payoutAccount', 'identityVerification:id,user_id,status'])
            ->orderByDesc('earned_total')
            ->paginate(20, pageName: 'liste');
    }

    /**
     * @return LengthAwarePaginator<int, Commission>
     */
    #[Computed]
    public function cancelled(): LengthAwarePaginator
    {
        return Commission::query()->where('status', CommissionStatus::Cancelled->value)
            ->with(['user:id,name', 'relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->latest('cancelled_at')
            ->paginate(30, pageName: 'liste');
    }

    /**
     * @return array{pending_total: int, pending_count: int, paid_month: int, balances_total: int}
     */
    #[Computed]
    public function stats(): array
    {
        $earned = (int) Commission::query()->whereIn('status', [CommissionStatus::Due->value, CommissionStatus::Paid->value])->sum('amount');
        $withdrawn = (int) Payout::query()->whereIn('status', [PayoutStatus::Requested->value, PayoutStatus::Paid->value])->sum('amount');

        return [
            'pending_total' => (int) Payout::query()->where('status', PayoutStatus::Requested->value)->sum('amount'),
            'pending_count' => Payout::query()->where('status', PayoutStatus::Requested->value)->count(),
            'paid_month' => (int) Payout::query()->where('status', PayoutStatus::Paid->value)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'balances_total' => max(0, $earned - $withdrawn),
        ];
    }

    #[Computed]
    public function payout(): ?Payout
    {
        return $this->payoutUuid === null ? null : Payout::query()->where('uuid', $this->payoutUuid)->with('user')->first();
    }

    #[Computed]
    public function holder(): ?User
    {
        return $this->holderUuid === null ? null : User::query()->where('uuid', $this->holderUuid)->first();
    }

    /**
     * @return Collection<int, Commission>
     */
    #[Computed]
    public function holderCommissions(): Collection
    {
        return $this->holder === null ? new Collection : Commission::query()
            ->where('user_id', $this->holder->id)
            ->with(['relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->latest('id')->get();
    }

    /**
     * @return array{earned: int, available: int, pending: int, withdrawn: int}
     */
    public function walletOf(User $user): array
    {
        return app(Wallet::class)->summary($user);
    }

    public static function isVerified(User $user): bool
    {
        return $user->identityVerification?->status === IdentityStatus::Verified;
    }

    public function render(): View
    {
        return view('livewire.console.gains.index');
    }

    private function selectPayout(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->resetErrorBag();
        $this->payoutUuid = $uuid;
        unset($this->payout);
        abort_if($this->payout === null, 404);
    }

    private function refreshLists(): void
    {
        unset($this->requests, $this->history, $this->balances, $this->cancelled, $this->stats, $this->payout);
    }

    private function admin(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::CommissionsManage->value) === true, 403);
    }
}
