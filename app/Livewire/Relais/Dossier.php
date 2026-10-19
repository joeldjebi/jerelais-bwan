<?php

namespace App\Livewire\Relais;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Actions\SubmitCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Exceptions\CandidatureIncomplete;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Support\CandidatureChecklist;
use App\Domain\Candidature\Support\StepRules;
use App\Domain\Contribution\Models\Category;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Localite;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dossier de candidature sur le site (WEB-02 = MOB-02 à MOB-06), enregistré automatiquement champ par champ.
 *
 * @property-read Candidature $candidature
 * @property-read array<string, array{label: string, complete: bool}> $checklist
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, Localite> $localites
 * @property-read Collection<int, LieuType> $lieuTypes
 * @property-read Collection<int, Category> $categories
 */
#[Layout('layouts::relais')]
#[Title('Ma candidature')]
class Dossier extends Component
{
    public const RECAP = 'recapitulatif';

    #[Url(as: 'etape')]
    public string $step = 'identite';

    /** @var array{last_name: string, first_names: string, birth_date: string, gender: string, email: string} */
    public array $identite = ['last_name' => '', 'first_names' => '', 'birth_date' => '', 'gender' => '', 'email' => ''];

    /** @var array{commune: string, localite: string, lieu_type: string} */
    public array $territoire = ['commune' => '', 'localite' => '', 'lieu_type' => ''];

    /** @var array{occupation: string, languages: list<string>, interests: list<string>, motivation: string} */
    public array $profil = ['occupation' => '', 'languages' => [], 'interests' => [], 'motivation' => ''];

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

    #[Computed]
    public function candidature(): Candidature
    {
        return Candidature::withoutCommuneRestriction()
            ->with(['commune', 'localite', 'lieuType', 'rejectionReason'])
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
        return [...array_map(fn (CandidatureStep $step): string => $step->value, CandidatureStep::cases()), self::RECAP];
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

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
