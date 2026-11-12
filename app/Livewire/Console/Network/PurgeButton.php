<?php

namespace App\Livewire\Console\Network;

use App\Domain\Access\Enums\Role;
use App\Domain\Privacy\Actions\PurgeNetworkAccounts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Bouton « Tout vider » des pages Relais et Candidatures, réservé aux administrateurs :
 * supprime tous les comptes du réseau et leurs données après saisie de SUPPRIMER.
 */
class PurgeButton extends Component
{
    public bool $open = false;

    public string $confirmation = '';

    public function purge(PurgeNetworkAccounts $purge): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $actor->hasRole(Role::Admin->value), 403);

        try {
            $counts = $purge->handle($actor, trim($this->confirmation));
        } catch (InvalidArgumentException $exception) {
            $this->addError('confirmation', $exception->getMessage());

            return;
        }

        $this->reset('open', 'confirmation');
        $this->dispatch('toast', message: __(':accounts comptes, :relais relais, :medias médias et :candidatures candidatures supprimés.', $counts));
        $this->redirect(request()->header('Referer') ?: route('console.relais'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.console.network.purge-button', [
            'visible' => Auth::user()?->hasRole(Role::Admin->value) === true,
        ]);
    }
}
