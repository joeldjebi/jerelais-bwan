<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityDocumentType;
use App\Domain\Identity\Models\IdentityVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Vérification d'identité en une seule requête (multipart) : tous les champs sont obligatoires,
 * avec les fichiers demandés par la pièce choisie (recto et verso, ou page), le selfie et la photo de profil.
 */
class SubmitIdentityRequest extends UpdateIdentityRequest
{
    /**
     * Dossier déjà en vérification ou vérifié : 409 avant toute validation.
     */
    protected function prepareForValidation(): void
    {
        $locked = IdentityVerification::query()
            ->where('user_id', $this->user()?->getAuthIdentifier())
            ->whereIn('status', [IdentityStatus::Submitted->value, IdentityStatus::Verified->value])
            ->exists();

        if ($locked) {
            throw new ConflictHttpException(__('identity.errors.locked'));
        }

        parent::prepareForValidation();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $identity = $this->identityRules();
        $sides = $this->documentType()?->sides() ?? [];
        $maxKb = (int) config('jerelais.candidature.document_max_kb');
        $document = 'mimetypes:'.implode(',', IdentityFileKind::Recto->mimeTypes());
        $image = 'mimetypes:'.implode(',', IdentityFileKind::Selfie->mimeTypes());
        $twoSided = in_array(IdentityFileKind::Recto, $sides, true);
        $onePage = in_array(IdentityFileKind::Page, $sides, true);

        return [
            /** Nom. */
            'last_name' => ['required', ...$this->withoutNullable($identity['last_name'])],
            /** Prénoms (`first_name` est aussi accepté). */
            'first_names' => ['required', ...$this->withoutNullable($identity['first_names'])],
            /** `femme` ou `homme`. */
            'gender' => ['required', ...$this->withoutNullable($identity['gender'])],
            /** Date de naissance : `26/04/2000` ou `2000-04-26`. */
            'birth_date' => ['required', ...$this->withoutNullable($identity['birth_date'])],
            /** uuid de `GET /communes` (obligatoire sauf si `localite` est envoyée). */
            'commune' => ['bail', 'required_without:localite', 'nullable', 'uuid', Rule::exists('communes', 'uuid')->where('is_active', true)],
            /** uuid de `GET /communes/{commune}/localites` (facultatif, de la même commune). */
            'localite' => ['bail', 'nullable', 'uuid', Rule::exists('localites', 'uuid')],
            /** Une `value` de `GET /auth/register/options`. */
            'document_type' => ['required', 'string', Rule::exists('identity_document_types', 'slug')->where('is_active', true)],
            /** Recto de la pièce (si `sides` contient `recto`) : image ou PDF, 10 Mo maximum. */
            'recto' => [Rule::requiredIf($twoSided), Rule::prohibitedIf(! $twoSided), 'nullable', 'file', 'max:'.$maxKb, $document],
            /** Verso de la pièce (si `sides` contient `verso`) : image ou PDF, 10 Mo maximum. */
            'verso' => [Rule::requiredIf($twoSided), Rule::prohibitedIf(! $twoSided), 'nullable', 'file', 'max:'.$maxKb, $document],
            /** Page de la pièce (si `sides` contient `page` : passeport, attestation) : image ou PDF, 10 Mo maximum. */
            'page' => [Rule::requiredIf($onePage), Rule::prohibitedIf(! $onePage), 'nullable', 'file', 'max:'.$maxKb, $document],
            /** Selfie de la personne tenant sa pièce : image, 10 Mo maximum. */
            'selfie' => ['required', 'file', 'max:'.$maxKb, $image],
            /** Photo de profil : image, 10 Mo maximum. */
            'profile_photo' => ['required', 'file', 'max:'.$maxKb, $image],
        ];
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    private function withoutNullable(array $rules): array
    {
        return array_values(array_filter($rules, fn (mixed $rule): bool => $rule !== 'nullable'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            ...parent::messages(),
            'last_name.required' => __('identity.errors.last_name'),
            'first_names.required' => __('identity.errors.first_names'),
            'gender.required' => __('identity.errors.gender'),
            'birth_date.required' => __('identity.errors.birth_date'),
            'commune.required_without' => __('identity.errors.commune'),
            'document_type.required' => __('identity.errors.document_type'),
        ];

        foreach (IdentityFileKind::cases() as $kind) {
            $messages[$kind->value.'.required'] = __('identity.errors.file_missing', ['kind' => $kind->label()]);
            $messages[$kind->value.'.prohibited'] = __('identity.errors.file_not_needed', ['kind' => $kind->label()]);
            $messages[$kind->value.'.mimetypes'] = __('identity.errors.file_refused', [
                'kind' => $kind->label(),
                'formats' => $kind->isDocumentSide() ? 'JPEG, PNG, HEIC, PDF' : 'JPEG, PNG, HEIC',
            ]);
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = parent::attributes();

        foreach (IdentityFileKind::cases() as $kind) {
            $attributes[$kind->value] = mb_strtolower($kind->label());
        }

        return [...$attributes, 'commune' => __('commune'), 'document_type' => __('type de pièce')];
    }

    /**
     * Fichiers envoyés, par type (seulement ceux demandés par la pièce).
     *
     * @return array<string, UploadedFile>
     */
    public function identityFiles(): array
    {
        $files = [];

        foreach (IdentityFileKind::cases() as $kind) {
            $file = $this->file($kind->value);

            if ($file instanceof UploadedFile) {
                $files[$kind->value] = $file;
            }
        }

        return $files;
    }

    private function documentType(): ?IdentityDocumentType
    {
        $slug = $this->input('document_type');

        return is_string($slug) ? IdentityDocumentType::query()->where('slug', $slug)->where('is_active', true)->first() : null;
    }
}
