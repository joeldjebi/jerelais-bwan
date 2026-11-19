<?php

namespace App\Livewire\Console\Editorial;

use App\Domain\Access\Enums\Permission;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Sensitive;
use App\Domain\Contribution\States\Validated;
use App\Domain\Editorial\Actions\BroadcastAndPublish;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bureau de la rédaction (ADM-10, ADM-13, §8.2) : relais sensibles à arbitrer, validés à exploiter,
 * sélection en cours (dont les relais qui me sont affectés), relais diffusés. Bouton « Diffuser » sur chaque relais
 * validé ou sélectionné : diffusion et publication sur la page d'accueil sans ouvrir la fiche.
 *
 * @property-read RelaisItem|null $publishing
 * @property-read LengthAwarePaginator<int, RelaisItem> $items
 * @property-read array<string, int> $counts
 */
#[Title('Exploitation')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'onglet')]
    public string $tab = 'a-exploiter';

    public bool $mine = false;

    #[Locked]
    public ?string $publishUuid = null;

    public bool $publishOpen = false;

    public string $broadcastAt = '';

    public string $broadcastProgramme = '';

    public string $broadcastUrl = '';

    public string $showcaseTitle = '';

    public string $showcaseText = '';

    public bool $showcaseImage = true;

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'mine'], true)) {
            $this->resetPage();
        }
    }

    public function openPublish(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->resetErrorBag();
        $this->publishUuid = $uuid;
        unset($this->publishing);
        $relais = $this->publishing;
        abort_if($relais === null, 404);

        $this->broadcastAt = now()->timezone('Africa/Abidjan')->format('Y-m-d\\TH:i');
        $this->broadcastProgramme = (string) $relais->programme;
        $this->broadcastUrl = '';
        $this->showcaseTitle = (string) ($relais->showcase_title ?? $relais->title);
        $this->showcaseText = (string) $relais->showcase_text;
        $this->showcaseImage = ! ($relais->blur_required || $relais->has_minors);
        $this->publishOpen = true;
    }

    /**
     * Diffuser et publier (ADM-13, WEB-05) : même geste que sur la fiche du relais.
     */
    public function publish(BroadcastAndPublish $publish): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can(Permission::RelaisArbitrate->value), 403);
        $relais = $this->publishing;
        abort_if($relais === null, 404);

        $this->validate([
            'broadcastAt' => ['required', 'date'],
            'broadcastProgramme' => ['required', 'string', 'max:120'],
            'broadcastUrl' => ['nullable', 'url:https,http', 'max:500'],
            'showcaseTitle' => ['required', 'string', 'max:150'],
            'showcaseText' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'broadcastAt' => __('date de diffusion'), 'broadcastProgramme' => __('émission'), 'broadcastUrl' => __('lien'),
            'showcaseTitle' => __('titre public'), 'showcaseText' => __('texte public'),
        ]);

        try {
            $publish->handle(
                $relais, $user, CarbonImmutable::parse($this->broadcastAt, 'Africa/Abidjan'), $this->broadcastProgramme,
                $this->broadcastUrl, $this->showcaseTitle, $this->showcaseText, $this->showcaseImage && ! ($relais->blur_required || $relais->has_minors),
            );
        } catch (EditorialRefused $exception) {
            $this->addError('publish', $exception->getMessage());

            return;
        }

        $this->publishOpen = false;
        $this->publishUuid = null;
        unset($this->items, $this->counts);
        $this->dispatch('toast', message: __('Relais diffusé et publié sur la page d’accueil. Le Relais est prévenu.'));
    }

    #[Computed]
    public function publishing(): ?RelaisItem
    {
        return $this->publishUuid === null ? null : RelaisItem::query()
            ->where('uuid', $this->publishUuid)
            ->whereIn('state', [Validated::getMorphClass(), Selected::getMorphClass()])
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, RelaisItem>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $with = ['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'assignee:id,name', 'medias' => fn ($query) => $query->with('variants')];

        // Vitrine « Vu sur REFLET TV » : exactement ce que renvoie GET /diffusions à l'application (MOB-25, WEB-05).
        if ($this->tab === 'vitrine') {
            return RelaisItem::query()->whereNotNull('showcased_at')->with($with)->latest('showcased_at')->paginate(25);
        }

        $state = match ($this->tab) {
            'a-arbitrer' => Sensitive::getMorphClass(),
            'selection' => Selected::getMorphClass(),
            'diffuses' => Broadcast::getMorphClass(),
            default => Validated::getMorphClass(),
        };

        return RelaisItem::query()
            ->where('state', $state)
            ->when($this->tab === 'selection' && $this->mine, fn ($query) => $query->where('assigned_to', Auth::id()))
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'assignee:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->orderByRaw("case urgency when 'urgent' then 0 when 'important' then 1 else 2 end")
            ->latest('updated_at')
            ->paginate(25);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byState = RelaisItem::query()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');

        return [
            'a-arbitrer' => (int) ($byState['sensitive'] ?? 0),
            'a-exploiter' => (int) ($byState['validated'] ?? 0),
            'selection' => (int) ($byState['selected'] ?? 0),
            'diffuses' => (int) ($byState['broadcast'] ?? 0),
            'vitrine' => RelaisItem::query()->whereNotNull('showcased_at')->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.console.editorial.index');
    }
}
