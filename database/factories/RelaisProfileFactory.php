<?php

namespace Database\Factories;

use App\Domain\Access\Enums\Role;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Relais recruté (candidature acceptée) et actif.
 *
 * @extends Factory<RelaisProfile>
 */
class RelaisProfileFactory extends Factory
{
    protected $model = RelaisProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->firstName().' '.Str::upper(fake()->randomLetter()).'.',
            'status' => RelaisStatus::Active,
            'activated_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RelaisProfile $profile): void {
            if ($profile->getAttribute('candidature_id') === null) {
                $candidature = Candidature::factory()->completed()->create(['status' => CandidatureStatus::Accepted]);
                $candidature->user->syncRoles([Role::Relais->value]);
                $profile->candidature()->associate($candidature);
                $profile->user()->associate($candidature->user);
                $profile->commune_id ??= $candidature->commune_id;
            }

            $profile->badge_token ??= Str::random(40);
            $profile->number ??= 'JR-'.fake()->unique()->numerify('######');
        });
    }

    public function suspended(): static
    {
        return $this->state(['status' => RelaisStatus::Suspended, 'suspended_at' => now()]);
    }

    public function forUser(User $user): static
    {
        return $this->afterMaking(fn (RelaisProfile $profile) => $profile->user()->associate($user));
    }
}
