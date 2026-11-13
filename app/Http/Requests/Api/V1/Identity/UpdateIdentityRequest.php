<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Domain\Identity\Actions\SaveIdentityDetails;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Models\IdentityDocumentType;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use App\Http\Requests\Concerns\DescribesIdentity;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * PUT /identity : modifier la vérification d'identité, tout est facultatif (champs et fichiers, multipart ou JSON).
 * Seuls les éléments envoyés et non vides changent : un champ vide laisse la valeur enregistrée. Les faces de la pièce doivent correspondre au type de pièce choisi
 * (celui envoyé, sinon celui déjà enregistré). Une localité suffit : sa commune est déduite.
 */
class UpdateIdentityRequest extends FormRequest
{
    use DescribesIdentity;

    /** Fichiers acceptés dans la requête. */
    public const array FILE_KINDS = ['recto', 'verso', 'page', 'selfie'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dossier déjà en vérification ou vérifié : 409 avant toute validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->verification()?->status->isEditable() === false) {
            throw new ConflictHttpException(__('identity.errors.locked'));
        }

        $this->normalizeBirthDate();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $identity = $this->identityRules();
        $maxKb = (int) config('jerelais.candidature.document_max_kb');
        $document = 'mimetypes:'.implode(',', IdentityFileKind::Recto->mimeTypes());
        $image = 'mimetypes:'.implode(',', IdentityFileKind::Selfie->mimeTypes());

        return [
            'last_name' => $identity['last_name'],
            'first_names' => $identity['first_names'],
            'gender' => $identity['gender'],
            'birth_date' => $identity['birth_date'],
            'commune' => ['bail', 'nullable', 'uuid', Rule::exists('communes', 'uuid')->where('is_active', true)],
            'localite' => ['bail', 'nullable', 'uuid', Rule::exists('localites', 'uuid')],
            'document_type' => ['nullable', 'string', Rule::in(self::documentTypeValues())],
            'recto' => ['nullable', 'file', 'max:'.$maxKb, $document],
            'verso' => ['nullable', 'file', 'max:'.$maxKb, $document],
            'page' => ['nullable', 'file', 'max:'.$maxKb, $document],
            'selfie' => ['nullable', 'file', 'max:'.$maxKb, $image],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'birth_date.date_format' => __('La date de naissance doit être au format JJ/MM/AAAA, par exemple 26/04/2000.'),
            'commune.exists' => __('Cette commune n’existe pas ou n’est pas ouverte : utilisez un uuid renvoyé par GET /communes.'),
            'localite.exists' => __('Cette localité n’existe pas : utilisez un uuid renvoyé par GET /communes/{commune}/localites.'),
            'document_type.in' => __('identity.errors.document_type_unknown', ['values' => implode(', ', self::documentTypeValues())]),
        ];

        foreach (IdentityFileKind::cases() as $kind) {
            $messages[$kind->value.'.required'] = __('identity.errors.file_missing', ['kind' => $kind->label()]);
            $messages[$kind->value.'.file'] = __('identity.errors.file_missing', ['kind' => $kind->label()]);
            $messages[$kind->value.'.mimetypes'] = __('identity.errors.file_refused', [
                'kind' => $kind->label(),
                'formats' => $kind->isDocumentSide() ? 'JPEG, PNG, HEIC, PDF' : 'JPEG, PNG, HEIC',
            ]);
        }

        return $messages;
    }

    /**
     * Localité de la commune envoyée ; faces de la pièce conformes au type de pièce.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Requête vide (aucun champ ni photo) : on le signale au lieu de répondre 200 sans rien changer.
                $fields = ['last_name', 'first_names', 'first_name', 'gender', 'birth_date', 'commune', 'localite', 'document_type'];

                if (! $this instanceof SubmitIdentityRequest && ! $this->anyFilled($fields) && $this->identityFiles() === []) {
                    $validator->errors()->add('identity', __('identity.errors.nothing_to_update'));
                }
            },
            function (Validator $validator): void {
                $commune = $this->input('commune');
                $localite = $this->input('localite');

                if ($validator->errors()->hasAny(['commune', 'localite']) || ! is_string($commune) || ! is_string($localite) || $commune === '' || $localite === '') {
                    return;
                }

                $belongs = Localite::query()->where('uuid', $localite)->whereHas('commune', fn ($query) => $query->where('uuid', $commune))->exists();

                if (! $belongs) {
                    $validator->errors()->add('localite', __('Cette localité n’appartient pas à la commune choisie.'));
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->has('document_type')) {
                    return;
                }

                $type = $this->documentType();

                foreach ([IdentityFileKind::Recto, IdentityFileKind::Verso, IdentityFileKind::Page] as $side) {
                    if (! $this->hasFile($side->value)) {
                        continue;
                    }

                    if ($type === null) {
                        $validator->errors()->add($side->value, __('identity.errors.document_type_first'));
                    } elseif (! in_array($side, $type->sides(), true)) {
                        $validator->errors()->add($side->value, __('identity.errors.file_not_needed_for', ['kind' => $side->label(), 'type' => $type->name]));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [...$this->identityAttributes(), 'commune' => __('commune'), 'localite' => __('localité'), 'document_type' => __('type de pièce')];

        foreach (IdentityFileKind::cases() as $kind) {
            $attributes[$kind->value] = mb_strtolower($kind->label());
        }

        return $attributes;
    }

    /**
     * Champs envoyés, prêts pour SaveIdentityDetails (les absents ne sont pas modifiés).
     *
     * @return array{commune?: Commune|null, localite?: Localite|null, document_type?: IdentityDocumentType|null, last_name?: string|null, first_names?: string|null, gender?: string|null, birth_date?: string|null}
     */
    public function details(): array
    {
        $details = [];

        // Un champ vide (ou absent) n'est pas modifié : les formulaires envoient souvent tous leurs champs, même vides.
        foreach (SaveIdentityDetails::IDENTITY_FIELDS as $field) {
            if ($this->filled($field)) {
                $details[$field] = trim($this->string($field)->toString());
            }
        }

        if ($this->filled('commune') || $this->filled('localite')) {
            $commune = $this->commune();
            $details['commune'] = $commune;
            $details['localite'] = $this->localite($commune);
        }

        if ($this->filled('document_type')) {
            $details['document_type'] = IdentityDocumentType::query()->where('slug', $this->string('document_type')->toString())->first();
        }

        return $details;
    }

    /**
     * Fichiers envoyés, par type.
     *
     * @return array<string, UploadedFile>
     */
    public function identityFiles(): array
    {
        $files = [];

        foreach (self::FILE_KINDS as $kind) {
            $file = $this->file($kind);

            if ($file instanceof UploadedFile) {
                $files[$kind] = $file;
            }
        }

        return $files;
    }

    /**
     * Valeurs acceptées pour document_type : pièces actives (console → Référentiels → Pièces d'identité).
     *
     * @return list<string>
     */
    public static function documentTypeValues(): array
    {
        return array_values(IdentityDocumentType::query()->active()->pluck('slug')->map(fn ($slug): string => (string) $slug)->all());
    }

    /**
     * Pièce visée : celle envoyée, sinon celle déjà enregistrée.
     */
    protected function documentType(): ?IdentityDocumentType
    {
        $slug = $this->input('document_type');

        if (is_string($slug) && $slug !== '') {
            return IdentityDocumentType::query()->active()->where('slug', $slug)->first();
        }

        return $this->verification()?->documentType;
    }

    protected function verification(): ?IdentityVerification
    {
        $userId = $this->user()?->getAuthIdentifier();

        return $userId === null ? null : IdentityVerification::query()->where('user_id', $userId)->first();
    }

    private function commune(): ?Commune
    {
        $uuid = $this->input('commune');

        return is_string($uuid) ? Commune::query()->where('uuid', $uuid)->first() : null;
    }

    private function localite(?Commune $commune): ?Localite
    {
        $uuid = $this->input('localite');
        $localite = is_string($uuid) ? Localite::query()->where('uuid', $uuid)->first() : null;

        return $localite !== null && ($commune === null || $localite->commune_id === $commune->id) ? $localite : null;
    }
}
