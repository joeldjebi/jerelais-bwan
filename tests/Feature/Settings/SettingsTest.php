<?php

use App\Domain\Settings\Settings;
use App\Models\User;

it('retourne la valeur par défaut du cahier des charges [ADM-23]', function (string $key, mixed $expected) {
    expect(app(Settings::class)->get($key))->toBe($expected);
})->with([
    'vidéo 3 minutes (MOB-11)' => ['media.video_max_seconds', 180],
    'audio 10 minutes (MOB-11)' => ['media.audio_max_seconds', 600],
    '10 médias par relais (MOB-11)' => ['media.max_medias_per_relais', 10],
    '500 Mo par fichier (§10)' => ['media.max_file_size_mb', 500],
    'urgent sous 30 minutes (COO-17)' => ['validation.sla_urgent_minutes', 30],
    'normal sous 4 heures (COO-17)' => ['validation.sla_normal_minutes', 240],
    'verrou de 10 minutes (§8.2)' => ['validation.lock_minutes', 10],
]);

it('enregistre une modification de l’administrateur, la met en cache et l’audite [ADM-23, ADM-25]', function () {
    $admin = User::factory()->create();
    $settings = app(Settings::class);

    $settings->set('validation.sla_urgent_minutes', 20, $admin);

    expect($settings->get('validation.sla_urgent_minutes'))->toBe(20);
    $this->assertDatabaseHas('settings', ['key' => 'validation.sla_urgent_minutes', 'updated_by' => $admin->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'actor_id' => $admin->id]);
});

it('refuse un paramètre inconnu', function () {
    app(Settings::class)->get('inconnu.cle');
})->throws(InvalidArgumentException::class, 'Paramètre inconnu');

it('refuse une valeur du mauvais type', function () {
    app(Settings::class)->set('media.video_max_seconds', 'trois minutes');
})->throws(InvalidArgumentException::class);

it('n’expose à l’application que les paramètres publics', function () {
    $public = app(Settings::class)->public();

    expect($public)->toHaveKey('media.video_max_seconds')
        ->and($public)->not->toHaveKey('validation.sla_urgent_minutes')
        ->and($public)->not->toHaveKey('retention.broadcast_relais_days');
});
