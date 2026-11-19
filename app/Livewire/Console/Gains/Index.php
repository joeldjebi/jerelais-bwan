<?php

namespace App\Livewire\Console\Gains;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Gains\Actions\PayCommissions;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Commission;
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
 * Paiements des Relais (ADM-27) : ce qui est dû à chaque Relais, son versement par Mobile Money (enregistré avec sa
 * référence) et l'historique. Réservé à l'administration.
 *
 * @property-read LengthAwarePaginator<int, User> $due
 * @property-read LengthAwarePaginator<int, Commission> $history
 * @property-read array{due_total: int, due_relais: int, paid_month: int} $stats
 * @property-read User|null $payee
 * @property-read Collection<int, Commission> $payeeCommissions
 */
#[Title('Paiements')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'a-payer')]
    public string $tab = 'a-payer';

    #[Locked]
    public ?string $payeeUuid = null;

    public bool $paying = false;

    public string $method = 'wave';

    public string $phone = '';

    public string $reference = '';

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
        $this->resetPage();
    }

    public function openPay(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->resetErrorBag();
        $this->payeeUuid = $uuid;
        unset($this->payee, $this->payeeCommissions);
        abort_if($this->payee === null, 404);
        $this->method = PaymentMethod::Wave->value;
        $this->phone = (string) $this->payee->phoneNumber()?->e164;
        $this->reference = '';
        $this->paying = true;
    }

    public function pay(PayCommissions $pay): void
    {
        $this->authorizeAccess();
        $this->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'reference' => ['required', 'string', 'max:120'],
        ], attributes: ['method' => __('moyen de paiement'), 'phone' => __('numéro'), 'reference' => __('référence de la transaction')]);

        $payee = $this->payee;
        abort_if($payee === null, 404);

        try {
            $total = $pay->pay($payee, $this->admin(), PaymentMethod::from($this->method), $this->reference, $this->phone);
        } catch (CommissionRefused $exception) {
            $this->addError('reference', $exception->getMessage());

            return;
        }

        $this->paying = false;
        $this->payeeUuid = null;
        unset($this->due, $this->stats, $this->history);
        $this->dispatch('toast', message: __('Paiement de :amount FCFA enregistré. Le Relais est prévenu.', ['amount' => number_format($total, 0, ',', ' ')]));
    }

    public function askCancel(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->resetErrorBag();
        $this->cancelUuid = $uuid;
        $this->cancelReason = '';
        $this->cancelling = true;
    }

    public function cancel(PayCommissions $pay): void
    {
        $this->authorizeAccess();
        $this->validate(['cancelReason' => ['required', 'string', 'max:500']], attributes: ['cancelReason' => __('motif')]);
        $commission = Commission::query()->where('uuid', (string) $this->cancelUuid)->firstOrFail();

        try {
            $pay->cancel($commission, $this->admin(), $this->cancelReason);
        } catch (CommissionRefused $exception) {
            $this->addError('cancelReason', $exception->getMessage());

            return;
        }

        $this->cancelling = false;
        unset($this->due, $this->stats, $this->payeeCommissions);
        $this->dispatch('toast', message: __('Commission annulée.'));
    }

    /**
     * Relais à qui l'on doit quelque chose, le plus gros montant d'abord.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function due(): LengthAwarePaginator
    {
        return User::query()
            ->whereHas('commissions', fn ($query) => $query->due())
            ->withSum(['commissions as due_total' => fn ($query) => $query->due()], 'amount')
            ->withCount(['commissions as due_count' => fn ($query) => $query->due()])
            ->with([
                'relaisProfile' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'user_id', 'display_name', 'number', 'commune_id')->with('commune:id,name'),
                'identityVerification:id,user_id,status',
            ])
            ->orderByDesc('due_total')
            ->paginate(20, pageName: 'dus');
    }

    /**
     * @return LengthAwarePaginator<int, Commission>
     */
    #[Computed]
    public function history(): LengthAwarePaginator
    {
        return Commission::query()
            ->whereIn('status', [CommissionStatus::Paid->value, CommissionStatus::Cancelled->value])
            ->with(['user:id,name', 'payer:id,name', 'relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->latest('updated_at')
            ->paginate(30, pageName: 'historique');
    }

    /**
     * @return array{due_total: int, due_relais: int, paid_month: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'due_total' => (int) Commission::query()->due()->sum('amount'),
            'due_relais' => Commission::query()->due()->distinct()->count('user_id'),
            'paid_month' => (int) Commission::query()->where('status', CommissionStatus::Paid->value)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
        ];
    }

    #[Computed]
    public function payee(): ?User
    {
        return $this->payeeUuid === null ? null : User::query()->where('uuid', $this->payeeUuid)->with('identityVerification:id,user_id,status')->first();
    }

    /**
     * @return Collection<int, Commission>
     */
    #[Computed]
    public function payeeCommissions(): Collection
    {
        return $this->payee === null ? new Collection : Commission::query()
            ->where('user_id', $this->payee->id)->due()
            ->with(['relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)->select('id', 'uuid', 'title')])
            ->oldest()->get();
    }

    public static function isVerified(User $user): bool
    {
        return $user->identityVerification?->status === IdentityStatus::Verified;
    }

    public function render(): View
    {
        return view('livewire.console.gains.index');
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
