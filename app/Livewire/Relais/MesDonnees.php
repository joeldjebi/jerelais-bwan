<?php

namespace App\Livewire\Relais;

use App\Domain\Privacy\Actions\PseudonymizeUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mes données (§9.4) : téléchargement et suppression du compte, pour un candidat comme pour un Relais.
 */
#[Layout('layouts::relais')]
#[Title('Mes données')]
class MesDonnees extends Component
{
    public string $confirm = '';

    public function delete(PseudonymizeUser $pseudonymize): mixed
    {
        $this->validate(['confirm' => ['required', 'in:SUPPRIMER']], ['confirm.in' => __('Tapez SUPPRIMER en majuscules pour confirmer.')]);

        $user = Auth::user();
        abort_unless($user instanceof User && ! $user->isStaff(), 403);

        $pseudonymize->handle($user, $user, __('Demande du titulaire du compte'));
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirectRoute('home');
    }

    public function render(): View
    {
        return view('livewire.relais.mes-donnees');
    }
}
