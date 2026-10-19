<?php

namespace App\Domain\Candidature\Support;

use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Enums\Gender;
use App\Domain\Candidature\Enums\Occupation;
use App\Domain\Contribution\Models\Category;
use App\Domain\Territoire\Models\Commune;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Règles de validation de chaque étape du formulaire, communes à l'API et au site web (WEB-02 = MOB-02).
 * Tous les champs sont facultatifs à l'enregistrement (sauvegarde automatique d'un brouillon) ;
 * la complétude est contrôlée au dépôt du dossier.
 */
class StepRules
{
    public function __construct(private readonly AgeRequirement $age) {}

    /**
     * @param  string|null  $communeUuid  commune retenue pour filtrer les localités
     * @return array<string, array<int, string|ValidationRule|Closure|\Illuminate\Contracts\Validation\Rule|\Stringable>>
     */
    public function for(CandidatureStep $step, ?string $communeUuid = null): array
    {
        return match ($step) {
            CandidatureStep::Identite => [
                'last_name' => ['nullable', 'string', 'max:100'],
                'first_names' => ['nullable', 'string', 'max:150'],
                'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01', $this->ageRule()],
                'gender' => ['nullable', Rule::enum(Gender::class)],
                'email' => ['nullable', 'email:rfc', 'max:255'],
            ],
            CandidatureStep::Territoire => [
                'commune' => ['bail', 'nullable', 'uuid', Rule::exists('communes', 'uuid')->where('is_active', true)],
                'localite' => ['bail', 'nullable', 'uuid', Rule::exists('localites', 'uuid')->where('commune_id', $this->communeId($communeUuid))],
                'lieu_type' => ['bail', 'nullable', 'uuid', Rule::exists('lieu_types', 'uuid')],
            ],
            CandidatureStep::Profil => [
                'occupation' => ['nullable', Rule::enum(Occupation::class)],
                'languages' => ['nullable', 'array', 'max:8'],
                'languages.*' => ['string', Rule::in(array_keys(self::languages()))],
                'interests' => ['nullable', 'array', 'max:12'],
                'interests.*' => ['string', Rule::in(Category::query()->active()->pluck('slug')->all())],
                'motivation' => ['nullable', 'string', 'max:1500'],
            ],
        };
    }

    /**
     * Langues proposées (MOB-02).
     *
     * @return array<string, string>
     */
    public static function languages(): array
    {
        /** @var array<string, string> $languages */
        $languages = __('candidature.languages');

        return $languages;
    }

    /**
     * Identifiant de la commune choisie (0 si absente ou mal formée : aucune localité ne correspond).
     */
    private function communeId(?string $communeUuid): int
    {
        if ($communeUuid === null || ! Str::isUuid($communeUuid)) {
            return 0;
        }

        return (int) Commune::query()->where('uuid', $communeUuid)->value('id');
    }

    private function ageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $birthDate = is_string($value) ? CarbonImmutable::createFromFormat('Y-m-d', $value) : null;

            if ($birthDate instanceof CarbonImmutable && ! $this->age->isEligible($birthDate)) {
                $fail($this->age->refusalMessage());
            }
        };
    }
}
