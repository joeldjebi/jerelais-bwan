<?php

namespace App\Livewire\Console\Privacy;

use App\Domain\Access\Enums\Permission;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Privacy\Actions\PseudonymizeUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Traitement des demandes sur les données personnelles (§9.4) : retrouver un compte par téléphone ou e-mail,
 * exporter ses données, le supprimer (pseudonymisation) avec le motif de la demande.
 *
 * @property-read User|null $found
 */
#[Title('Données personnelles')]
class Index extends Component
{
    public string $query = '';

    public string $reason = '';

    public bool $confirming = false;

    public function search(): void
    {
        $this->authorizeAccess();

        $this->validate(['query' => ['required', 'string', 'max:255']], attributes: ['query' => __('téléphone ou e-mail')]);
        unset($this->found);
    }

    public function pseudonymize(PseudonymizeUser $pseudonymize): void
    {
        $this->authorizeAccess();

        $user = $this->found;
        abort_if($user === null || $user->isStaff(), 403);
        $this->validate(['reason' => ['required', 'string', 'max:500']], attributes: ['reason' => __('motif')]);

        /** @var User $actor */
        $actor = Auth::user();
        $pseudonymize->handle($user, $actor, $this->reason);

        $this->reset('confirming', 'reason');
        unset($this->found);
        $this->dispatch('toast', message: __('Compte supprimé et pseudonymisé.'));
    }

    #[Computed]
    public function found(): ?User
    {
        $term = trim($this->query);

        if ($term === '') {
            return null;
        }

        $phone = PhoneNumber::tryParse($term);

        return $phone !== null ? User::findByPhone($phone) : User::query()->where('email', mb_strtolower($term))->first();
    }

    public function render(): View
    {
        return view('livewire.console.privacy.index', ['deleted' => UserStatus::Deleted]);
    }

    /**
     * Revérifié à chaque action : un droit retiré prend effet même sur un onglet resté ouvert.
     */
    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::UsersManage->value) === true, 403);
    }
}
