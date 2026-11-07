<?php

namespace App\Livewire\Console\Candidatures;

use App\Domain\Candidature\Actions\DecideCandidature;
use App\Domain\Candidature\Actions\FindCandidatureDuplicates;
use App\Domain\Candidature\Actions\StartCandidatureReview;
use App\Domain\Candidature\Data\DuplicateMatch;
use App\Domain\Candidature\Enums\CandidatureDecision;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Formation\Actions\GradeQuizAttempt;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Dossier complet d'un candidat et décision du coordonnateur (COO-01 à COO-03).
 *
 * @property-read list<DuplicateMatch> $duplicates
 * @property-read Collection<int, RejectionReason> $reasons
 * @property-read int|null $quizScore
 */
class Show extends Component
{
    #[Locked]
    public Candidature $candidature;

    public bool $rejecting = false;

    public bool $complementing = false;

    public bool $confirmingAccept = false;

    public bool $confirmingHold = false;

    public string $reason = '';

    public string $comment = '';

    public function mount(Candidature $candidature, StartCandidatureReview $review): void
    {
        Gate::authorize('view', $candidature);

        if (Gate::allows('decide', $candidature)) {
            $review->handle($candidature, $this->user());
        }

        $this->candidature = $candidature->load(['commune', 'localite', 'lieuType', 'documents', 'user', 'decider', 'rejectionReason']);
    }

    public function openReject(): void
    {
        $this->reset('reason', 'comment');
        $this->resetErrorBag();
        $this->rejecting = true;
    }

    public function openComplement(): void
    {
        $this->reset('comment');
        $this->resetErrorBag();
        $this->complementing = true;
    }

    public function accept(DecideCandidature $decide): void
    {
        $this->decide($decide, CandidatureDecision::Accept);
    }

    public function hold(DecideCandidature $decide): void
    {
        $this->decide($decide, CandidatureDecision::Hold);
    }

    public function reject(DecideCandidature $decide): void
    {
        $this->validate([
            'reason' => ['required', 'uuid'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], ['reason.required' => __('candidature.decision.reason_required')]);

        $reason = $this->reasons->firstWhere('uuid', $this->reason);

        if ($reason === null) {
            $this->addError('reason', __('candidature.decision.reason_required'));

            return;
        }

        $this->decide($decide, CandidatureDecision::Reject, $reason);
    }

    public function requestComplement(DecideCandidature $decide): void
    {
        $this->validate(['comment' => ['required', 'string', 'min:10', 'max:1000']], ['comment.required' => __('candidature.decision.comment_required')]);

        $this->decide($decide, CandidatureDecision::RequestComplement);
    }

    /**
     * @return list<DuplicateMatch>
     */
    #[Computed]
    public function duplicates(): array
    {
        return app(FindCandidatureDuplicates::class)->handle($this->candidature);
    }

    /**
     * @return Collection<int, RejectionReason>
     */
    #[Computed]
    public function reasons(): Collection
    {
        return RejectionReason::query()->activeFor(RejectionTarget::Candidature)->get();
    }

    #[Computed]
    public function quizScore(): ?int
    {
        return GradeQuizAttempt::bestScore($this->candidature->user);
    }

    public function render(): View
    {
        return view('livewire.console.candidatures.show')
            ->title($this->candidature->fullName() ?: __('Dossier de candidature'));
    }

    private function decide(DecideCandidature $decide, CandidatureDecision $decision, ?RejectionReason $reason = null): void
    {
        Gate::authorize('decide', $this->candidature);

        try {
            $decide->handle($this->candidature, $decision, $this->user(), $reason, $this->comment === '' ? null : $this->comment);
        } catch (InvalidArgumentException $exception) {
            $this->addError($decision === CandidatureDecision::Reject ? 'reason' : 'comment', $exception->getMessage());

            return;
        } catch (CandidatureLocked) {
            $this->dispatch('toast', message: __('Cette candidature a déjà été traitée.'), tone: 'error');

            return;
        }

        $this->reset('rejecting', 'complementing', 'confirmingAccept', 'confirmingHold');
        $this->candidature->refresh()->load(['decider', 'rejectionReason']);

        $this->dispatch('toast', message: __('Décision enregistrée : :status. Le candidat est prévenu.', ['status' => mb_strtolower($this->candidature->status->label())]), tone: 'success');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
