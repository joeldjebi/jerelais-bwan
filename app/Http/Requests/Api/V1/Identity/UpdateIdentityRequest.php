<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Commune ou localité, et type de pièce d'identité. Une localité suffit : sa commune est déduite.
 */
class UpdateIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'commune' => ['bail', 'nullable', 'uuid', Rule::exists('communes', 'uuid')->where('is_active', true)],
            'localite' => ['bail', 'nullable', 'uuid', Rule::exists('localites', 'uuid')],
            'document_type' => ['nullable', Rule::enum(IdentityDocumentType::class)],
        ];
    }

    public function commune(): ?Commune
    {
        $uuid = $this->input('commune');

        return is_string($uuid) ? Commune::query()->where('uuid', $uuid)->first() : null;
    }

    public function localite(): ?Localite
    {
        $uuid = $this->input('localite');
        $localite = is_string($uuid) ? Localite::query()->where('uuid', $uuid)->first() : null;
        $commune = $this->commune();

        // Une localité d'une autre commune que celle choisie est ignorée : la commune choisie prime.
        return $localite !== null && ($commune === null || $localite->commune_id === $commune->id) ? $localite : null;
    }

    public function documentType(): ?IdentityDocumentType
    {
        return IdentityDocumentType::tryFrom($this->string('document_type')->toString());
    }
}
