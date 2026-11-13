<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Models\IdentityDocumentType;

/**
 * POST /identity : envoyer la vérification d'identité en une seule requête (multipart). Tout est obligatoire :
 * identité, commune (ou localité), type de pièce, faces demandées par cette pièce (recto et verso, ou page) et selfie.
 */
class SubmitIdentityRequest extends UpdateIdentityRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $sides = $this->documentType()?->sides() ?? [];

        foreach (['last_name', 'first_names', 'gender', 'birth_date', 'document_type', 'selfie'] as $field) {
            $rules[$field] = ['required', ...array_values(array_filter($rules[$field], fn (mixed $rule): bool => $rule !== 'nullable'))];
        }

        $rules['commune'] = ['required_without:localite', ...$rules['commune']];

        foreach ([IdentityFileKind::Recto, IdentityFileKind::Verso, IdentityFileKind::Page] as $side) {
            if (in_array($side, $sides, true)) {
                $rules[$side->value] = ['required', ...array_values(array_filter($rules[$side->value], fn (mixed $rule): bool => $rule !== 'nullable'))];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'last_name.required' => __('identity.errors.last_name'),
            'first_names.required' => __('identity.errors.first_names'),
            'gender.required' => __('identity.errors.gender'),
            'birth_date.required' => __('identity.errors.birth_date'),
            'commune.required_without' => __('identity.errors.commune'),
            'document_type.required' => __('identity.errors.document_type'),
        ];
    }

    /**
     * Pour un premier envoi, seule la pièce envoyée compte (pas celle d'un brouillon précédent).
     */
    protected function documentType(): ?IdentityDocumentType
    {
        $slug = $this->input('document_type');

        return is_string($slug) && $slug !== '' ? parent::documentType() : null;
    }
}
