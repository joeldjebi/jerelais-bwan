<?php

namespace Database\Factories;

use App\Domain\Access\Enums\Role;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Enums\Gender;
use App\Domain\Candidature\Enums\Occupation;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidature>
 */
class CandidatureFactory extends Factory
{
    protected $model = Candidature::class;

    /**
     * Dossier vierge (brouillon) d'un candidat.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole(Role::Candidat),
            'status' => CandidatureStatus::Draft,
        ];
    }

    /**
     * Toutes les étapes du formulaire renseignées.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'commune_id' => Commune::factory(),
            'last_name' => fake()->lastName(),
            'first_names' => fake()->firstName(),
            'birth_date' => now()->subYears(25)->toDateString(),
            'gender' => Gender::Female,
            'occupation' => Occupation::Etudiant,
            'languages' => ['fr'],
            'interests' => [],
            'motivation' => 'Je veux montrer ce qui se passe dans mon quartier.',
        ])->afterCreating(function (Candidature $candidature): void {
            foreach (DocumentType::requiredFor($candidature) as $type) {
                $candidature->documents()->create([
                    'type' => $type,
                    'disk' => 'identity',
                    'path' => "candidatures/{$candidature->uuid}/{$type->value}.jpg",
                    'mime' => 'image/jpeg',
                    'size' => 120_000,
                    'sha256' => hash('sha256', $candidature->uuid.$type->value),
                    'scanned_at' => now(),
                ]);
            }
        });
    }

    public function submitted(): static
    {
        return $this->completed()->state(fn (): array => [
            'status' => CandidatureStatus::Submitted,
        ])->afterMaking(fn (Candidature $candidature) => $candidature->forceFill(['submitted_at' => now()]));
    }
}
