<?php

namespace App\Livewire\Console\Collections;

use App\Domain\Access\Enums\Permission;
use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Models\RelaisCollection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Une collection : ses relais, retrait, téléchargement en lot (ADM-15, ADM-11), renommage et suppression.
 *
 * @property-read Collection<int, RelaisItem> $items
 */
class Show extends Component
{
    #[Locked]
    public RelaisCollection $collection;

    public bool $editing = false;

    public string $name = '';

    public string $description = '';

    public bool $confirmingDelete = false;

    public function openEdit(): void
    {
        $this->authorizeAccess();
        $this->resetValidation();
        $this->name = $this->collection->name;
        $this->description = (string) $this->collection->description;
        $this->editing = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizeAccess();
        $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000']], attributes: ['name' => __('nom')]);

        $this->collection->fill(['name' => trim($this->name), 'description' => trim($this->description) ?: null]);

        if ($this->collection->isDirty()) {
            $this->collection->save();
            $audit->record('editorial.collection_updated', $this->collection, ['name' => $this->collection->name]);
        }

        $this->editing = false;
        $this->dispatch('toast', message: __('Collection enregistrée.'));
    }

    /**
     * Supprime la collection (les relais eux-mêmes ne sont pas touchés).
     */
    public function destroy(AuditLogger $audit): mixed
    {
        $this->authorizeAccess();
        $audit->record('editorial.collection_deleted', $this->collection, ['name' => $this->collection->name, 'relais' => $this->collection->relais()->count()]);
        $this->collection->delete();

        session()->flash('toast', ['message' => __('Collection supprimée.'), 'tone' => 'success']);

        return $this->redirectRoute('console.collections', navigate: true);
    }

    public function remove(string $uuid, AuditLogger $audit): void
    {
        $this->authorizeAccess();

        $relais = RelaisItem::query()->where('uuid', $uuid)->first();

        if ($relais !== null) {
            $this->collection->relais()->detach($relais->id);
            $audit->record('editorial.collection_removed', $relais, ['collection' => $this->collection->name]);
            unset($this->items);
        }
    }

    /**
     * @return Collection<int, RelaisItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->collection->relais()
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->orderByPivot('created_at', 'desc')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.console.collections.show')->title($this->collection->name);
    }

    /**
     * Revérifié à chaque action : un droit retiré prend effet même sur un onglet resté ouvert.
     */
    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::RelaisArbitrate->value) === true, 403);
    }
}
