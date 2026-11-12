<?php

namespace App\Http\Requests\Api\V1\Identity;

use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Support\StepRules;
use App\Domain\Identity\Actions\SaveIdentityDetails;
use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use App\Http\Requests\Concerns\DescribesIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Profil pour les commissions, en mise à jour partielle : identité, commune ou localité, type de pièce.
 * Seuls les champs présents dans la requête sont modifiés. Une localité suffit : sa commune est déduite.
 */
class UpdateIdentityRequest extends FormRequest
{
    use DescribesIdentity;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBirthDate();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $identity = app(StepRules::class)->for(CandidatureStep::Identite);

        return [
            'last_name' => array_values($identity['last_name']),
            'first_names' => array_values($identity['first_names']),
            'gender' => array_values($identity['gender']),
            'birth_date' => array_values($identity['birth_date']),
            'commune' => ['bail', 'nullable', 'uuid', Rule::exists('communes', 'uuid')->where('is_active', true)],
            'localite' => ['bail', 'nullable', 'uuid', Rule::exists('localites', 'uuid')],
            'document_type' => ['nullable', Rule::enum(IdentityDocumentType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['birth_date.date_format' => __('La date de naissance doit être au format JJ/MM/AAAA, par exemple 26/04/2000.')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'last_name' => __('nom'),
            'first_names' => __('prénoms'),
            'gender' => __('sexe'),
            'birth_date' => __('date de naissance'),
        ];
    }

    /**
     * Champs envoyés, prêts pour SaveIdentityDetails (les absents ne sont pas modifiés).
     *
     * @return array{commune?: Commune|null, localite?: Localite|null, document_type?: IdentityDocumentType|null, last_name?: string|null, first_names?: string|null, gender?: string|null, birth_date?: string|null}
     */
    public function details(): array
    {
        $details = [];

        foreach (SaveIdentityDetails::IDENTITY_FIELDS as $field) {
            if ($this->exists($field)) {
                $value = $this->input($field);
                $details[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }
        }

        if ($this->exists('commune') || $this->exists('localite')) {
            $commune = $this->commune();
            $localite = $this->localite($commune);
            $details['commune'] = $commune;
            $details['localite'] = $localite;
        }

        if ($this->exists('document_type')) {
            $details['document_type'] = IdentityDocumentType::tryFrom($this->string('document_type')->toString());
        }

        return $details;
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

        // Une localité d'une autre commune que celle choisie est ignorée : la commune choisie prime.
        return $localite !== null && ($commune === null || $localite->commune_id === $commune->id) ? $localite : null;
    }
}
