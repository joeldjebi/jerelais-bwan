<?php

namespace App\Livewire\Relais;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidature\Actions\DeleteCandidatureDocument;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Actions\StoreCandidatureDocument;
use App\Domain\Candidature\Actions\SubmitCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Exceptions\CandidatureIncomplete;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Support\CandidatureChecklist;
use App\Domain\Candidature\Support\StepRules;
use App\Domain\Contribution\Models\Category;
use App\Domain\Formation\Actions\GradeQuizAttempt;
use App\Domain\Formation\Models\FormationModule;
use App\Domain\Formation\Models\QuizAttempt;
use App\Domain\Formation\Models\QuizQuestion;
use App\Domain\Legal\Actions\AcceptLegalDocuments;
use App\Domain\Legal\Models\Consent;
use App\Domain\Legal\Models\LegalDocument;
use App\Domain\Media\Exceptions\InfectedFile;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Localite;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Dossier de candidature sur le site (WEB-02 = MOB-02 à MOB-06), enregistré automatiquement champ par champ.
 *
 * @property-read Candidature $candidature
 * @property-read array<string, array{label: string, complete: bool}> $checklist
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, Localite> $localites
 * @property-read Collection<int, LieuType> $lieuTypes
 * @property-read Collection<int, Category> $categories
 * @property-read array<string, LegalDocument> $legalDocuments
 * @property-read array<string, Consent> $consents
 * @property-read Collection<int, FormationModule> $modules
 * @property-read Collection<int, QuizQuestion> $questions
 * @property-read QuizAttempt|null $lastAttempt
 * @property-read RelaisProfile|null $relaisProfile
 */
#[Layout('layouts::relais')]
#[Title('Ma candidature')]
class Dossier extends Component
{
    use WithFileUploads;

    public const DOCUMENTS = 'documents';

    public const CHARTE = 'charte';

    public const FORMATION = 'formation';

    public const RECAP = 'recapitulatif';

    #[Url(as: 'etape')]
    public string $step = 'identite';

    /** @var array{last_name: string, first_names: string, birth_date: string, gender: string, email: string} */
    public array $identite = ['last_name' => '', 'first_names' => '', 'birth_date' => '', 'gender' => '', 'email' => ''];

    /** @var array{commune: string, localite: string, lieu_type: string} */
    public array $territoire = ['commune' => '', 'localite' => '', 'lieu_type' => ''];

    /** @var array{occupation: string, languages: list<string>, interests: list<string>, motivation: string} */
    public array $profil = ['occupation' => '', 'languages' => [], 'interests' => [], 'motivation' => ''];

    /** @var array<string, bool> textes cochés avant acceptation, par type */
    public array $accepted = ['charte' => false, 'conditions' => false, 'confidentialite' => false];

    /** Formation : « fiches », « quiz » ou « resultat ». */
    public string $formationView = 'fiches';

    public int $questionIndex = 0;

    /** @var array<string, string> uuid de la question => uuid de la réponse */
    public array $quizAnswers = [];

    #[Locked]
    public ?string $attemptUuid = null;

    /** @var array<string, TemporaryUploadedFile|null> pièce en cours d'envoi, par type */
    public array $uploads = [];

    #[Locked]
    public ?string $savedAt = null;

    /** @var array<string, string> */
    #[Locked]
    public array $missing = [];

    public function mount(StartCandidature $start): void
    {
        $user = $this->user();
        abort_if($user->isStaff(), 403);

        if ($user->hasPermissionTo(Permission::CandidatureApply->value)) {
            $candidature = $start->handle($user);
        } else {
            $candidature = Candidature::withoutCommuneRestriction()->where('user_id', $user->id)->first();
            abort_if($candidature === null, 403);
        }

        $candidature->load(['commune', 'localite', 'lieuType']);

        $this->identite = [
            'last_name' => (string) $candidature->last_name,
            'first_names' => (string) $candidature->first_names,
            'birth_date' => (string) $candidature->birth_date?->toDateString(),
            'gender' => (string) $candidature->gender?->value,
            'email' => (string) $candidature->email,
        ];
        $this->territoire = [
            'commune' => (string) $candidature->commune?->uuid,
            'localite' => (string) $candidature->localite?->uuid,
            'lieu_type' => (string) $candidature->lieuType?->uuid,
        ];
        $this->profil = [
            'occupation' => (string) $candidature->occupation?->value,
            'languages' => $candidature->languages ?? [],
            'interests' => $candidature->interests ?? [],
            'motivation' => (string) $candidature->motivation,
        ];

        if (! in_array($this->step, $this->stepKeys(), true)) {
            $this->step = CandidatureStep::Identite->value;
        }
    }

    /**
     * Sauvegarde automatique : chaque modification d'un champ enregistre son étape.
     */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'uploads.')) {
            $this->storeUpload(substr($property, strlen('uploads.')));

            return;
        }

        $group = explode('.', $property)[0];
        $step = CandidatureStep::tryFrom($group);

        if ($step === null) {
            return;
        }

        if ($property === 'territoire.commune') {
            $this->territoire['localite'] = '';
            unset($this->localites);
        }

        $this->autosave($step);
    }

    public function goTo(string $step): void
    {
        if (in_array($step, $this->stepKeys(), true)) {
            $this->step = $step;
            $this->dispatch('step-changed');
        }
    }

    public function next(): void
    {
        $keys = $this->stepKeys();
        $index = array_search($this->step, $keys, true);
        $this->goTo($keys[min(count($keys) - 1, (int) $index + 1)]);
    }

    public function previous(): void
    {
        $keys = $this->stepKeys();
        $index = array_search($this->step, $keys, true);
        $this->goTo($keys[max(0, (int) $index - 1)]);
    }

    public function submit(SubmitCandidature $submit): void
    {
        $this->missing = [];

        try {
            $submit->handle($this->candidature);
        } catch (CandidatureIncomplete $exception) {
            $this->missing = $exception->missing;

            return;
        } catch (CandidatureLocked) {
            // Déjà déposé : la page affiche simplement le suivi.
        }

        unset($this->candidature);
        $this->dispatch('candidature-submitted');
    }

    public function removeDocument(string $type, DeleteCandidatureDocument $delete): void
    {
        $documentType = DocumentType::tryFrom($type);

        if ($documentType === null) {
            return;
        }

        try {
            $delete->handle($this->candidature, $documentType);
        } catch (CandidatureLocked) {
            return;
        }

        unset($this->candidature, $this->checklist);
    }

    /**
     * Acceptation horodatée de la charte, des conditions et de la politique de confidentialité (MOB-04).
     */
    public function acceptLegal(AcceptLegalDocuments $accept): void
    {
        $this->resetErrorBag('accepted');
        $documents = $this->legalDocuments;

        foreach (array_keys($documents) as $type) {
            if (! ($this->accepted[$type] ?? false)) {
                $this->addError('accepted', __('legal.must_accept_all'));

                return;
            }
        }

        try {
            $accept->handle(
                $this->user(),
                array_values(array_map(fn (LegalDocument $document): string => $document->uuid, $documents)),
                'web',
                request()->ip(),
                request()->userAgent(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('accepted', $exception->getMessage());

            return;
        }

        unset($this->checklist, $this->legalDocuments);
        $this->savedAt = now()->format('H:i');
    }

    public function startQuiz(): void
    {
        $this->quizAnswers = [];
        $this->questionIndex = 0;
        $this->attemptUuid = null;
        $this->formationView = 'quiz';
        $this->dispatch('step-changed');
    }

    public function answer(string $questionUuid, string $choiceUuid): void
    {
        $question = $this->questions->firstWhere('uuid', $questionUuid);

        if ($question === null || $question->choices->firstWhere('uuid', $choiceUuid) === null) {
            return;
        }

        $this->quizAnswers[$questionUuid] = $choiceUuid;
    }

    public function nextQuestion(): void
    {
        $this->questionIndex = min($this->questions->count() - 1, $this->questionIndex + 1);
    }

    public function previousQuestion(): void
    {
        $this->questionIndex = max(0, $this->questionIndex - 1);
    }

    public function submitQuiz(GradeQuizAttempt $grade): void
    {
        $this->resetErrorBag('quiz');

        try {
            $attempt = $grade->handle($this->user(), $this->quizAnswers);
        } catch (InvalidArgumentException $exception) {
            $this->addError('quiz', $exception->getMessage());

            return;
        }

        $this->attemptUuid = $attempt->uuid;
        $this->formationView = 'resultat';
        unset($this->checklist, $this->lastAttempt);
        $this->dispatch('step-changed');
    }

    public function showFiches(): void
    {
        $this->formationView = 'fiches';
        $this->dispatch('step-changed');
    }

    /**
     * @return Collection<int, FormationModule>
     */
    #[Computed(persist: true)]
    public function modules(): Collection
    {
        return FormationModule::query()->published()->get();
    }

    /**
     * @return Collection<int, QuizQuestion>
     */
    #[Computed]
    public function questions(): Collection
    {
        return QuizQuestion::query()->published()->with('choices')->get();
    }

    #[Computed]
    public function lastAttempt(): ?QuizAttempt
    {
        $query = $this->user()->quizAttempts()->latest('id');

        return $this->attemptUuid !== null ? $query->where('uuid', $this->attemptUuid)->first() : $query->first();
    }

    /**
     * @return array<string, LegalDocument>
     */
    #[Computed]
    public function legalDocuments(): array
    {
        return LegalDocument::currentSet();
    }

    /**
     * Acceptations des versions en vigueur, par type de texte.
     *
     * @return array<string, Consent>
     */
    #[Computed]
    public function consents(): array
    {
        $ids = array_map(fn (LegalDocument $document): int => $document->id, $this->legalDocuments);

        return Consent::query()->with('legalDocument')->where('user_id', $this->user()->id)->whereIn('legal_document_id', array_values($ids))->get()
            ->keyBy(fn (Consent $consent): string => $consent->legalDocument->type->value)
            ->all();
    }

    #[Computed]
    public function relaisProfile(): ?RelaisProfile
    {
        return RelaisProfile::withoutCommuneRestriction()->with(['commune', 'candidature.documents'])->where('user_id', $this->user()->id)->first();
    }

    #[Computed]
    public function candidature(): Candidature
    {
        return Candidature::withoutCommuneRestriction()
            ->with(['commune', 'localite', 'lieuType', 'rejectionReason', 'documents'])
            ->where('user_id', $this->user()->id)
            ->firstOrFail();
    }

    /**
     * @return array<string, array{label: string, complete: bool}>
     */
    #[Computed]
    public function checklist(): array
    {
        return app(CandidatureChecklist::class)->items($this->candidature);
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed(persist: true)]
    public function communes(): Collection
    {
        return Commune::query()->active()->with('region:id,name')->orderBy('name')->get(['id', 'uuid', 'name', 'region_id']);
    }

    /**
     * @return Collection<int, Localite>
     */
    #[Computed]
    public function localites(): Collection
    {
        $communeId = Str::isUuid($this->territoire['commune'])
            ? Commune::query()->active()->where('uuid', $this->territoire['commune'])->value('id')
            : null;

        return Localite::query()->where('commune_id', $communeId ?? 0)->orderBy('name')->get(['uuid', 'name', 'type']);
    }

    /**
     * @return Collection<int, LieuType>
     */
    #[Computed(persist: true)]
    public function lieuTypes(): Collection
    {
        return LieuType::query()->orderBy('name')->get(['uuid', 'name']);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed(persist: true)]
    public function categories(): Collection
    {
        return Category::query()->active()->get(['slug', 'name']);
    }

    /**
     * @return list<string>
     */
    public function stepKeys(): array
    {
        return [
            ...array_map(fn (CandidatureStep $step): string => $step->value, CandidatureStep::cases()),
            self::DOCUMENTS,
            self::CHARTE,
            ...(array_key_exists(self::FORMATION, $this->checklist) ? [self::FORMATION] : []),
            self::RECAP,
        ];
    }

    public function render(): View
    {
        return view('livewire.relais.dossier');
    }

    /**
     * Valide l'étape et enregistre les champs valides ; les champs en erreur restent affichés sans bloquer les autres.
     */
    private function autosave(CandidatureStep $step): void
    {
        if (! $this->candidature->status->isEditable()) {
            return;
        }

        /** @var array<string, mixed> $values */
        $values = $this->{$step->value};
        $data = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $values);

        $validator = Validator::make(
            $data,
            app(StepRules::class)->for($step, $this->territoire['commune'] ?: null),
            attributes: __('candidature.attributes'),
        );

        $failed = array_unique(array_map(fn (string $key): string => explode('.', $key)[0], $validator->errors()->keys()));

        foreach ($step->fields() as $field) {
            $this->resetErrorBag("{$step->value}.{$field}");
        }

        foreach ($validator->errors()->messages() as $key => $messages) {
            $this->addError($step->value.'.'.explode('.', $key)[0], $messages[0]);
        }

        $valid = array_diff_key($data, array_flip($failed));

        if ($valid === []) {
            return;
        }

        app(SaveCandidatureStep::class)->handle($this->candidature, $step, $valid);
        unset($this->candidature, $this->checklist);
        $this->missing = [];
        $this->savedAt = now()->format('H:i');
    }

    /**
     * Pièce choisie : contrôle du type réel et de la taille, antivirus, puis stockage privé.
     */
    private function storeUpload(string $type): void
    {
        $documentType = DocumentType::tryFrom($type);
        $file = $this->uploads[$type] ?? null;

        if ($documentType === null || ! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $this->resetErrorBag("uploads.{$type}");

        $validator = Validator::make(['file' => $file], ['file' => [
            'file',
            'max:'.(int) config('jerelais.candidature.document_max_kb'),
            'mimetypes:'.implode(',', $documentType->mimeTypes()),
        ]], attributes: ['file' => $documentType->label()]);

        if ($validator->fails()) {
            $this->addError("uploads.{$type}", (string) $validator->errors()->first('file'));
        } else {
            try {
                app(StoreCandidatureDocument::class)->handle($this->candidature, $documentType, $file);
                $this->savedAt = now()->format('H:i');
            } catch (InfectedFile $exception) {
                $this->addError("uploads.{$type}", $exception->getMessage());
            } catch (VirusScanUnavailable) {
                $this->addError("uploads.{$type}", __('media.scan_unavailable'));
            } catch (CandidatureLocked|InvalidArgumentException $exception) {
                $this->addError("uploads.{$type}", __('candidature.document_not_expected'));
            }
        }

        $file->delete();
        unset($this->uploads[$type], $this->candidature, $this->checklist);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
