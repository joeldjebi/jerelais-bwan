<?php

namespace App\Livewire\Console\Collections;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Models\RelaisCollection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Une collection : ses relais, retrait, téléchargement en lot (ADM-15, ADM-11).
 *
 * @property-read Collection<int, RelaisItem> $items
 */
class Show extends Component
{
    #[Locked]
    public RelaisCollection $collection;

    public function remove(string $uuid, AuditLogger $audit): void
    {
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
}
