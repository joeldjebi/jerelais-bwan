<?php

namespace App\Livewire\Relais;

use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\AppelRelais\Support\AppelAudience;
use App\Livewire\Relais\Concerns\InteractsWithRelaisSpace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Appels à relais de ma zone (MOB-23, WEB-03).
 *
 * @property-read Collection<int, AppelRelais> $appels
 */
#[Layout('layouts::relais')]
#[Title('Appels à relais')]
class Appels extends Component
{
    use InteractsWithRelaisSpace;

    /**
     * @return Collection<int, AppelRelais>
     */
    #[Computed]
    public function appels(): Collection
    {
        return AppelAudience::openFor($this->profile());
    }

    public function render(): View
    {
        return view('livewire.relais.appels');
    }
}
