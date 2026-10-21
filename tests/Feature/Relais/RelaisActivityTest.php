<?php

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Archived;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Rejected;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Contribution\States\Validated;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Settings\Settings;
use Laravel\Sanctum\Sanctum;

function decidedRelais(RelaisProfile $profile, string $state, array $history, array $attributes = []): RelaisItem
{
    $relais = RelaisItem::factory()->create(['user_id' => $profile->user_id, 'relais_profile_id' => $profile->id, 'commune_id' => $profile->commune_id, 'state' => $state, 'submitted_at' => now(), ...$attributes]);

    foreach ($history as $to) {
        $relais->statusHistory()->create(['to' => $to, 'created_at' => now()]);
    }

    return $relais;
}

it('donne au Relais ses volumes, son taux de validation, son niveau et ses badges [MOB-24]', function () {
    $profile = RelaisProfile::factory()->create();
    app(Settings::class)->set('levels.silver_validated', 3);

    decidedRelais($profile, Validated::class, ['validated']);
    decidedRelais($profile, Broadcast::class, ['validated', 'selected', 'broadcast'], ['broadcast_at' => now()]);
    decidedRelais($profile, Archived::class, ['validated', 'archived']);
    decidedRelais($profile, Rejected::class, ['rejected']);
    RelaisItem::factory()->create(['user_id' => $profile->user_id, 'relais_profile_id' => $profile->id, 'state' => Uploading::class])->forceFill(['submitted_at' => null])->save();

    Sanctum::actingAs($profile->user);

    $response = $this->getJson(route('api.v1.me.stats'))->assertOk();

    expect($response->json('data'))
        ->sent->toBe(4)
        ->validated->toBe(3)
        ->broadcast->toBe(1)
        ->validation_rate->toBe(75)
        ->and($response->json('data.level.key'))->toBe('argent')
        ->and($response->json('data.level.next'))->toBe(['key' => 'or', 'label' => 'Relais Or', 'remaining' => 27])
        ->and(collect($response->json('data.badges'))->where('earned', true)->pluck('key')->all())->toBe(['first_relais', 'first_validated', 'first_broadcast']);
});

it('présente un nouveau Relais sans taux de validation [MOB-24]', function () {
    $profile = RelaisProfile::factory()->create();
    Sanctum::actingAs($profile->user);

    $this->getJson(route('api.v1.me.stats'))
        ->assertOk()
        ->assertJsonPath('data.sent', 0)
        ->assertJsonPath('data.validation_rate', null)
        ->assertJsonPath('data.level.key', 'debutant')
        ->assertJsonPath('data.level.next.key', 'bronze');
});

it('refuse le tableau de bord sans authentification', function () {
    $this->getJson(route('api.v1.me.stats'))->assertUnauthorized();
});
