<?php

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Sms\FakeSmsGateway;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\FakeMultipartStorage;
use App\Domain\Media\Storage\MultipartStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Tests fonctionnels : base PostgreSQL de test réinitialisée, rôles et permissions de l'Annexe A en place.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Remplace la passerelle SMS par une passerelle en mémoire et la retourne.
 */
function fakeSms(): FakeSmsGateway
{
    $gateway = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $gateway);

    return $gateway;
}

/**
 * Données d'appareil valides pour les connexions de l'application.
 *
 * @return array<string, string>
 */
function deviceFields(): array
{
    return ['device_name' => 'Tecno Spark 10', 'platform' => 'android', 'app_version' => '1.0.0'];
}

/**
 * Remplace l'envoi multipart S3 par une version en mémoire et la retourne.
 */
function fakeMultipart(): FakeMultipartStorage
{
    Storage::fake('medias');
    $storage = new FakeMultipartStorage;
    app()->instance(MultipartStorage::class, $storage);

    return $storage;
}

/**
 * Joint au relais un média déjà reçu et contrôlé.
 */
function attachReadyMedia(RelaisItem $relais, string $kind = 'photo'): Media
{
    $media = new Media([
        'client_uuid' => (string) Str::uuid(),
        'kind' => $kind,
        'status' => MediaStatus::Ready,
        'declared_mime' => 'image/jpeg',
        'size' => 1000,
        'declared_sha256' => str_repeat('a', 64),
        'disk' => 'medias',
        'path' => 'relais/'.$relais->uuid.'/'.Str::uuid().'.jpg',
        'part_size' => 8 * 1024 * 1024,
    ]);
    $relais->medias()->save($media);

    return $media;
}
