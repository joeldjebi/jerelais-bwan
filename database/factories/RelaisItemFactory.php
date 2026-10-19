<?php

namespace Database\Factories;

use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Enums\PeopleConsent;
use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Received;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Relais reçu dans la file du coordonnateur de sa commune.
 *
 * @extends Factory<RelaisItem>
 */
class RelaisItemFactory extends Factory
{
    protected $model = RelaisItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'state' => Received::class,
            'urgency' => Urgency::Normal,
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'has_minors' => false,
            'people_consent' => PeopleConsent::NotApplicable,
            'credit_mode' => CreditMode::Anonymous,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RelaisItem $relais): void {
            $relais->client_uuid ??= (string) Str::uuid();

            if ($relais->getAttribute('user_id') === null) {
                $profile = RelaisProfile::factory()->create();
                $relais->user()->associate($profile->user);
                $relais->relaisProfile()->associate($profile);
                $relais->commune_id ??= $profile->commune_id;
            }

            $relais->category_id ??= Category::query()->value('id') ?? Category::query()->create(['name' => 'Vie de quartier', 'slug' => 'vie-de-quartier-'.Str::random(4)])->id;
            $relais->routed_by ??= 'gps';
            $relais->submitted_at ??= now()->subMinutes(5);
            $relais->received_at ??= now()->subMinutes(5);
            $relais->sla_due_at ??= now()->addMinutes(235);
        });
    }

    public function urgent(): static
    {
        return $this->state(['urgency' => Urgency::Urgent])->afterMaking(fn (RelaisItem $relais) => $relais->forceFill(['sla_due_at' => now()->addMinutes(25)]));
    }

    public function withMinors(): static
    {
        return $this->state(['has_minors' => true, 'people_consent' => PeopleConsent::No]);
    }
}
