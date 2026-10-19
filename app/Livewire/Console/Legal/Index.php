<?php

namespace App\Livewire\Console\Legal;

use App\Domain\Access\Enums\Permission;
use App\Domain\Legal\Actions\PublishLegalDocument;
use App\Domain\Legal\Enums\LegalDocumentType;
use App\Domain\Legal\Models\Consent;
use App\Domain\Legal\Models\LegalDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Textes juridiques versionnés : charte, conditions, confidentialité (ADM-23, MOB-04).
 * Une version publiée n'est jamais modifiée ; chaque publication est auditée.
 *
 * @property-read array<string, LegalDocument> $current
 * @property-read Collection<int, LegalDocument> $history
 * @property-read array<int, int> $acceptances
 */
#[Title('Textes juridiques')]
class Index extends Component
{
    public bool $editing = false;

    public bool $confirming = false;

    #[Locked]
    public string $type = 'charte';

    public string $title = '';

    public string $body = '';

    public string $mode = 'edit';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function edit(string $type): void
    {
        $this->authorizeAccess();
        $legalType = LegalDocumentType::from($type);
        $current = $this->current[$legalType->value] ?? null;

        $this->type = $legalType->value;
        $this->title = $current->title ?? $legalType->label();
        $this->body = $current->body ?? '';
        $this->mode = 'edit';
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function askPublish(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:50', 'max:60000'],
        ], attributes: ['title' => __('titre'), 'body' => __('texte')]);

        $this->confirming = true;
    }

    public function publish(PublishLegalDocument $publish): void
    {
        $this->authorizeAccess();
        $this->askPublish();

        /** @var User $actor */
        $actor = Auth::user();
        $document = $publish->handle(LegalDocumentType::from($this->type), $this->title, $this->body, $actor);

        $this->confirming = false;
        $this->editing = false;
        unset($this->current, $this->history);

        $this->dispatch('toast', message: __(':title : version :version publiée.', ['title' => $document->title, 'version' => $document->version]), tone: 'success');
    }

    public function preview(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /**
     * @return array<string, LegalDocument>
     */
    #[Computed]
    public function current(): array
    {
        return LegalDocument::currentSet();
    }

    /**
     * @return Collection<int, LegalDocument>
     */
    #[Computed]
    public function history(): Collection
    {
        return LegalDocument::query()->orderByDesc('published_at')->orderByDesc('id')->limit(30)->get();
    }

    /**
     * Nombre d'acceptations par version.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function acceptances(): array
    {
        /** @var array<int, int> $counts */
        $counts = Consent::query()->selectRaw('legal_document_id, count(*) as total')->groupBy('legal_document_id')->pluck('total', 'legal_document_id')
            ->map(fn (mixed $total): int => (int) $total)->all();

        return $counts;
    }

    public function render(): View
    {
        return view('livewire.console.legal.index');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->hasPermissionTo(Permission::SettingsManage->value), 403);
    }
}
