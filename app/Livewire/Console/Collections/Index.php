<?php

namespace App\Livewire\Console\Collections;

use App\Domain\Audit\AuditLogger;
use App\Domain\Editorial\Models\RelaisCollection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Collections éditoriales (ADM-15) : relais regroupés par sujet ou par événement.
 *
 * @property-read Collection<int, RelaisCollection> $collections
 */
#[Title('Collections')]
class Index extends Component
{
    public bool $creating = false;

    public string $name = '';

    public string $description = '';

    public function create(AuditLogger $audit): mixed
    {
        $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000']], attributes: ['name' => __('nom')]);

        /** @var User $user */
        $user = Auth::user();
        $collection = new RelaisCollection(['name' => trim($this->name), 'description' => trim($this->description) ?: null]);
        $collection->creator()->associate($user);
        $collection->save();
        $audit->record('editorial.collection_created', $collection, ['name' => $collection->name], actor: $user);

        return $this->redirectRoute('console.collections.show', $collection, navigate: true);
    }

    /**
     * @return Collection<int, RelaisCollection>
     */
    #[Computed]
    public function collections(): Collection
    {
        return RelaisCollection::query()->withCount('relais')->with('creator:id,name')->latest('updated_at')->get();
    }

    public function render(): View
    {
        return view('livewire.console.collections.index');
    }
}
