<?php

namespace App\Livewire\Console\Referentiels;

use App\Domain\Settings\Actions\ManageReferenceList;
use App\Domain\Settings\Enums\ReferenceList;
use App\Domain\Territoire\Enums\LocaliteType;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Référentiels de la plateforme : localités et types de lieux (ADM-20), catégories et motifs de rejet (ADM-23, COO-15).
 *
 * @property-read list<ReferenceList> $lists
 * @property-read Collection<int, Model> $entries
 * @property-read Collection<int, Commune> $communeOptions
 */
#[Title('Référentiels')]
class Index extends Component
{
    #[Url(except: 'localites')]
    public string $list = 'localites';

    #[Url(except: '')]
    public string $commune = '';

    public string $name = '';

    public string $localiteType = 'quartier';

    public string $target = 'relais';

    #[Locked]
    public ?string $editing = null;

    public string $editingName = '';

    public function mount(): void
    {
        $lists = $this->lists;
        abort_if($lists === [], 403);

        if (! in_array($this->current(), $lists, true)) {
            $this->list = $lists[0]->value;
        }

        if ($this->commune === '') {
            $this->commune = (string) Commune::query()->active()->orderBy('name')->value('uuid');
        }
    }

    public function updatedList(): void
    {
        $this->resetErrorBag();
        $this->reset('name', 'editing', 'editingName');
    }

    public function add(ManageReferenceList $manage): void
    {
        $list = $this->authorizedList();
        $label = trim($this->name);
        $attributes = [];

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'localiteType' => [Rule::requiredIf($list === ReferenceList::Localites), Rule::enum(LocaliteType::class)],
            'target' => [Rule::requiredIf($list === ReferenceList::RejectionReasons), Rule::enum(RejectionTarget::class)],
        ]);

        if ($list === ReferenceList::Localites) {
            $commune = $this->selectedCommune();

            if ($commune->localites()->whereRaw('lower(name) = ?', [Str::lower($label)])->exists()) {
                $this->addError('name', __('Cette localité existe déjà dans la commune.'));

                return;
            }

            $attributes = ['commune_id' => $commune->id, 'type' => $this->localiteType];
        }

        if ($list === ReferenceList::RejectionReasons) {
            $attributes = ['applies_to' => $this->target];
        }

        $manage->create($list, $label, $attributes, $this->actor());

        $this->reset('name');
        unset($this->entries);
        $this->dispatch('toast', message: __(':label ajouté.', ['label' => $label]));
    }

    public function startEdit(string $uuid): void
    {
        $entry = $this->findEntry($uuid);

        $this->editing = $uuid;
        $this->editingName = (string) $entry->getAttribute($this->current()->labelColumn());
    }

    public function cancelEdit(): void
    {
        $this->reset('editing', 'editingName');
    }

    public function saveEdit(ManageReferenceList $manage): void
    {
        $list = $this->authorizedList();
        $this->validate(['editingName' => ['required', 'string', 'max:120']]);

        $manage->rename($list, $this->findEntry((string) $this->editing), trim($this->editingName), $this->actor());

        $this->reset('editing', 'editingName');
        unset($this->entries);
        $this->dispatch('toast', message: __('Modification enregistrée.'));
    }

    public function toggle(string $uuid, ManageReferenceList $manage): void
    {
        $list = $this->authorizedList();
        $entry = $this->findEntry($uuid);
        $active = ! $entry->getAttribute('is_active');

        $manage->setActive($list, $entry, $active, $this->actor());

        unset($this->entries);
        $this->dispatch('toast', message: $active ? __('Entrée réactivée.') : __('Entrée désactivée : elle n’est plus proposée.'));
    }

    /**
     * Listes que l'utilisateur peut gérer selon ses permissions.
     *
     * @return list<ReferenceList>
     */
    #[Computed]
    public function lists(): array
    {
        $user = Auth::user();

        return array_values(array_filter(ReferenceList::cases(), fn (ReferenceList $list): bool => $user?->can($list->permission()->value) === true));
    }

    /**
     * @return Collection<int, Model>
     */
    #[Computed]
    public function entries(): Collection
    {
        $list = $this->current();

        return match ($list) {
            ReferenceList::Localites => Str::isUuid($this->commune)
                ? $list->model()::query()->where('commune_id', $this->selectedCommune()->id)->orderBy('name')->get()
                : new Collection,
            ReferenceList::LieuTypes => $list->model()::query()->withCount('lieux')->orderBy('name')->get(),
            ReferenceList::Categories => $list->model()::query()->orderBy('position')->get(),
            ReferenceList::RejectionReasons => $list->model()::query()->orderBy('applies_to')->orderBy('position')->get(),
        };
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed]
    public function communeOptions(): Collection
    {
        return Commune::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'uuid', 'name', 'is_active']);
    }

    public function current(): ReferenceList
    {
        return ReferenceList::tryFrom($this->list) ?? ReferenceList::Localites;
    }

    public function render(): View
    {
        return view('livewire.console.referentiels.index');
    }

    private function authorizedList(): ReferenceList
    {
        $list = $this->current();
        abort_unless($this->actor()->can($list->permission()->value), 403);

        return $list;
    }

    private function findEntry(string $uuid): Model
    {
        $list = $this->authorizedList();
        abort_unless(Str::isUuid($uuid), 404);

        return $list->model()::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function selectedCommune(): Commune
    {
        abort_unless(Str::isUuid($this->commune), 404);

        return Commune::query()->where('uuid', $this->commune)->firstOrFail();
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
