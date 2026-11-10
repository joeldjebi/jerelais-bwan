<?php

namespace App\Livewire\Console\Identity;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Identity\Actions\DecideIdentityVerification;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Détail d'une vérification d'identité : identité déclarée à l'inscription, commune, pièce, selfie et photo de profil ;
 * décision de l'administration (MOB-01).
 *
 * @property-read Candidature|null $identity
 */
#[Title('Vérification d’identité')]
class Show extends Component
{
    public IdentityVerification $verification;

    public bool $confirmingVerify = false;

    public bool $rejecting = false;

    public string $comment = '';

    public function mount(IdentityVerification $verification): void
    {
        $this->authorizeAccess();
        $this->verification = $verification->load(['user', 'commune', 'localite', 'files']);
    }

    public function verify(DecideIdentityVerification $decide): void
    {
        $this->authorizeAccess();

        try {
            $decide->verify($this->verification, $this->actor());
        } catch (InvalidArgumentException $exception) {
            $this->confirmingVerify = false;
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->confirmingVerify = false;
        $this->dispatch('toast', message: __('Identité vérifiée : le Relais est éligible aux gains.'));
    }

    public function reject(DecideIdentityVerification $decide): void
    {
        $this->authorizeAccess();
        $this->validate(['comment' => ['required', 'string', 'max:1000']], attributes: ['comment' => __('commentaire')]);

        try {
            $decide->reject($this->verification, $this->actor(), $this->comment);
        } catch (InvalidArgumentException $exception) {
            $this->addError('comment', $exception->getMessage());

            return;
        }

        $this->rejecting = false;
        $this->reset('comment');
        $this->dispatch('toast', message: __('Vérification renvoyée au Relais pour correction.'));
    }

    /**
     * Identité déclarée à l'inscription (nom, prénoms, sexe, date de naissance).
     */
    #[Computed]
    public function identity(): ?Candidature
    {
        return Candidature::withoutCommuneRestriction()->where('user_id', $this->verification->user_id)->first();
    }

    public function canDecide(): bool
    {
        return $this->verification->status === IdentityStatus::Submitted;
    }

    public function render(): View
    {
        $this->verification->refresh()->load(['user', 'commune', 'localite', 'files']);

        return view('livewire.console.identity.show');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->hasPermissionTo(Permission::UsersManage->value) === true, 403);
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = Auth::user();

        return $actor;
    }
}
