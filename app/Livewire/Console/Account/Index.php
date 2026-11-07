<?php

namespace App\Livewire\Console\Account;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mon compte : profil, mot de passe et sécurité du membre connecté.
 *
 * @property-read User $user
 * @property-read string $roleLabel
 */
#[Title('Mon compte')]
class Index extends Component
{
    public string $name = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $this->name = (string) $this->user->name;
    }

    #[Computed]
    public function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    #[Computed]
    public function roleLabel(): string
    {
        $role = $this->user->getRoleNames()->first();

        return $role ? Role::from($role)->label() : '';
    }

    public function saveProfile(AuditLogger $audit): void
    {
        $this->validate(['name' => ['required', 'string', 'max:120']], attributes: ['name' => __('nom')]);

        $this->user->forceFill(['name' => $this->name])->save();
        $audit->record('users.profile_updated', $this->user, actor: $this->user);

        $this->dispatch('toast', message: __('Profil enregistré.'));
    }

    public function savePassword(AuditLogger $audit): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'same:passwordConfirmation', Password::defaults()],
        ], attributes: [
            'currentPassword' => __('mot de passe actuel'),
            'password' => __('nouveau mot de passe'),
        ]);

        $this->user->forceFill(['password' => Hash::make($this->password)])->save();
        $audit->record('auth.password.changed', $this->user, actor: $this->user);

        $this->reset('currentPassword', 'password', 'passwordConfirmation');
        $this->dispatch('toast', message: __('Mot de passe modifié.'));
    }

    public function render(): View
    {
        return view('livewire.console.account.index');
    }
}
