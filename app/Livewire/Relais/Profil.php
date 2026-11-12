<?php

namespace App\Livewire\Relais;

use App\Domain\Candidature\Models\Candidature;
use App\Domain\Identity\Actions\DeleteIdentityFile;
use App\Domain\Identity\Actions\SaveIdentityDetails;
use App\Domain\Identity\Actions\StoreIdentityFile;
use App\Domain\Identity\Actions\SubmitIdentityVerification;
use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Exceptions\IdentityIncomplete;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Identity\Support\IdentityChecklist;
use App\Domain\Media\Exceptions\InfectedFile;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Domain\Relais\Actions\EnrolAppRelais;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use App\Http\Requests\Api\V1\Identity\UpdateIdentityRequest;
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
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * « Mon profil » sur le site (même fonctionnement que l'application, GET/PUT /identity) : badge du Relais,
 * puis identité, commune, pièce d'identité, selfie et photo de profil à faire vérifier par l'administration.
 * Obligatoire seulement pour recevoir ses commissions : rien ici ne bloque l'envoi de relais.
 *
 * @property-read IdentityVerification $verification
 * @property-read RelaisProfile|null $relaisProfile
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, Localite> $localites
 */
#[Layout('layouts::relais')]
#[Title('Mon profil')]
class Profil extends Component
{
    use WithFileUploads;

    /** @var array{last_name: string, first_names: string, gender: string, birth_date: string} */
    public array $identite = ['last_name' => '', 'first_names' => '', 'gender' => '', 'birth_date' => ''];

    public string $commune = '';

    public string $localite = '';

    public string $documentType = '';

    /** @var array<string, TemporaryUploadedFile|null> fichier en cours d'envoi, par type */
    public array $uploads = [];

    #[Locked]
    public ?string $savedAt = null;

    public function mount(EnrolAppRelais $enrol): void
    {
        $user = $this->user();
        abort_if($user->isStaff(), 403);
        $enrol->ensure($user);

        $identity = Candidature::withoutCommuneRestriction()->where('user_id', $user->id)->first();
        $verification = $this->verification;

        $this->identite = [
            'last_name' => (string) $identity?->last_name,
            'first_names' => (string) $identity?->first_names,
            'gender' => (string) $identity?->gender?->value,
            'birth_date' => (string) $identity?->birth_date?->toDateString(),
        ];
        $this->commune = (string) $verification->commune?->uuid;
        $this->localite = (string) $verification->localite?->uuid;
        $this->documentType = (string) $verification->document_type?->value;
    }

    public function updatedCommune(): void
    {
        $this->localite = '';
        unset($this->localites);
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'uploads.')) {
            $this->storeUpload(substr($property, strlen('uploads.')));
        }
    }

    /**
     * Enregistre l'identité, la commune et le type de pièce (règles de PUT /identity).
     */
    public function save(SaveIdentityDetails $save): void
    {
        $this->resetErrorBag();

        $data = [
            ...$this->identite,
            'commune' => $this->commune !== '' ? $this->commune : null,
            'localite' => $this->localite !== '' ? $this->localite : null,
            'document_type' => $this->documentType !== '' ? $this->documentType : null,
        ];

        $request = new UpdateIdentityRequest;
        $validator = Validator::make($data, $request->rules(), [], $request->attributes());

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError(in_array($field, ['commune', 'localite', 'document_type'], true) ? Str::camel($field) : "identite.{$field}", $messages[0]);
            }

            return;
        }

        $commune = Str::isUuid($this->commune) ? Commune::query()->where('uuid', $this->commune)->first() : null;
        $localite = Str::isUuid($this->localite) ? Localite::query()->where('uuid', $this->localite)->first() : null;

        // Seuls les champs d'identité modifiés sont envoyés : l'identité d'un Relais recruté par candidature est verrouillée.
        $stored = Candidature::withoutCommuneRestriction()->where('user_id', $this->user()->id)->first();
        $current = [
            'last_name' => (string) $stored?->last_name,
            'first_names' => (string) $stored?->first_names,
            'gender' => (string) $stored?->gender?->value,
            'birth_date' => (string) $stored?->birth_date?->toDateString(),
        ];
        $changedIdentity = [];

        foreach ($this->identite as $key => $value) {
            if (trim($value) !== $current[$key]) {
                $changedIdentity[$key] = trim($value) !== '' ? trim($value) : null;
            }
        }

        try {
            $save->handle($this->verification, [
                ...$changedIdentity,
                'commune' => $commune,
                'localite' => $localite !== null && ($commune === null || $localite->commune_id === $commune->id) ? $localite : null,
                'document_type' => IdentityDocumentType::tryFrom($this->documentType),
            ]);
        } catch (IdentityLocked $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->savedAt = now()->format('H:i');
        unset($this->verification);
    }

    public function removeFile(string $kind, DeleteIdentityFile $delete): void
    {
        $fileKind = IdentityFileKind::tryFrom($kind);

        if ($fileKind === null) {
            return;
        }

        try {
            $delete->handle($this->verification, $fileKind);
        } catch (IdentityLocked $exception) {
            $this->addError('form', $exception->getMessage());
        }

        unset($this->verification);
    }

    public function submit(SubmitIdentityVerification $submit): void
    {
        $this->resetErrorBag();

        try {
            $submit->handle($this->verification);
        } catch (IdentityIncomplete $exception) {
            $this->addError('form', __('Il manque encore : :items', ['items' => implode(' · ', $exception->missing)]));

            return;
        } catch (IdentityLocked $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        unset($this->verification);
        $this->dispatch('toast', message: __('Profil envoyé : l’équipe JE RELAIS va vérifier votre identité.'));
    }

    #[Computed]
    public function verification(): IdentityVerification
    {
        $user = $this->user();
        $verification = IdentityVerification::query()->where('user_id', $user->id)->first();

        if ($verification === null) {
            $verification = new IdentityVerification;
            $verification->user()->associate($user);
            $verification->save();
        }

        return $verification->load(['commune', 'localite', 'files']);
    }

    #[Computed]
    public function relaisProfile(): ?RelaisProfile
    {
        return RelaisProfile::withoutCommuneRestriction()->with('commune')->where('user_id', $this->user()->id)->first();
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed(persist: true)]
    public function communes(): Collection
    {
        return Commune::query()->active()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /**
     * @return Collection<int, Localite>
     */
    #[Computed]
    public function localites(): Collection
    {
        $communeId = Str::isUuid($this->commune) ? Commune::query()->active()->where('uuid', $this->commune)->value('id') : null;

        return Localite::query()->where('commune_id', $communeId ?? 0)->orderBy('name')->get(['uuid', 'name']);
    }

    /**
     * @return array<string, string>
     */
    public function missing(): array
    {
        return $this->verification->status->isEditable() ? IdentityChecklist::missing($this->verification) : [];
    }

    public function render(): View
    {
        return view('livewire.relais.profil');
    }

    private function storeUpload(string $kind): void
    {
        $fileKind = IdentityFileKind::tryFrom($kind);
        $file = $this->uploads[$kind] ?? null;

        if ($fileKind === null || ! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $this->resetErrorBag("uploads.{$kind}");

        $validator = Validator::make(['file' => $file], ['file' => [
            'file',
            'max:'.(int) config('jerelais.candidature.document_max_kb'),
            'mimetypes:'.implode(',', $fileKind->mimeTypes()),
        ]], attributes: ['file' => $fileKind->label()]);

        if ($validator->fails()) {
            $this->addError("uploads.{$kind}", (string) $validator->errors()->first('file'));
        } else {
            try {
                app(StoreIdentityFile::class)->handle($this->verification, $fileKind, $file);
                $this->savedAt = now()->format('H:i');
            } catch (InfectedFile $exception) {
                $this->addError("uploads.{$kind}", $exception->getMessage());
            } catch (VirusScanUnavailable) {
                $this->addError("uploads.{$kind}", __('media.scan_unavailable'));
            } catch (IdentityLocked|InvalidArgumentException $exception) {
                $this->addError("uploads.{$kind}", $exception->getMessage());
            }
        }

        $file->delete();
        unset($this->uploads[$kind], $this->verification);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
