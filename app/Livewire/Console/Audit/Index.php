<?php

namespace App\Livewire\Console\Audit;

use App\Domain\Access\Enums\Permission;
use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Support\AuditChainVerifier;
use App\Domain\Audit\Support\AuditPresenter;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Journal d'audit non modifiable (ADM-25), consultable par les administrateurs.
 */
#[Title('Journal d’audit')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '7')]
    public string $period = '7';

    #[Locked]
    public ?string $selected = null;

    public bool $showing = false;

    /** @var array{checked: int, broken_at: ?int}|null */
    public ?array $integrity = null;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function show(string $uuid): void
    {
        $this->selected = $uuid;
        $this->showing = true;
    }

    public function verify(AuditChainVerifier $verifier, AuditLogger $audit): void
    {
        $this->authorizeAccess();
        $this->integrity = $verifier->verify();

        /** @var User $actor */
        $actor = Auth::user();
        $audit->record('audit.verified', payload: ['checked' => $this->integrity['checked'], 'intact' => $this->integrity['broken_at'] === null], actor: $actor);

        $this->dispatch('toast',
            message: $this->integrity['broken_at'] === null
                ? __('Journal intègre : :count lignes vérifiées.', ['count' => $this->integrity['checked']])
                : __('Anomalie détectée à la ligne :id.', ['id' => $this->integrity['broken_at']]),
            tone: $this->integrity['broken_at'] === null ? 'success' : 'error',
        );
    }

    #[Computed]
    public function entry(): ?AuditLog
    {
        return $this->selected ? AuditLog::query()->with('actor')->where('uuid', $this->selected)->first() : null;
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('actor')
            ->when($this->action !== '', fn ($query) => $query->where('action', $this->action))
            ->when($this->period !== 'all', fn ($query) => $query->where('occurred_at', '>=', now()->subDays(max(1, (int) $this->period))))
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function presenter(): AuditPresenter
    {
        return app(AuditPresenter::class);
    }

    public function render(): View
    {
        return view('livewire.console.audit.index');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::AuditView->value) === true, 403);
    }
}
