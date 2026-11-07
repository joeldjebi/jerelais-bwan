<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\FakeMultipartStorage;
use App\Domain\Media\Support\PerceptualHash;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Settings\Settings;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->storage = fakeMultipart();
    $this->profile = RelaisProfile::factory()->create();
    Sanctum::actingAs($this->profile->user);

    $this->relais = $this->postJson(route('api.v1.relais.store'), [
        'client_uuid' => (string) Str::uuid(),
        'category' => Category::query()->active()->firstOrFail()->uuid,
    ])->json('data.uuid');
});

function jpeg(int $width = 800, int $height = 600, array $color = [242, 140, 40]): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 235, 238, 243));
    imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), imagecolorallocate($image, ...$color));
    ob_start();
    imagejpeg($image, null, 85);

    return (string) ob_get_clean();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function announce(string $content, array $overrides = []): TestResponse
{
    return test()->postJson(route('api.v1.relais.medias.store', test()->relais), [
        'client_uuid' => (string) Str::uuid(),
        'kind' => 'photo',
        'mime' => 'image/jpeg',
        'size' => strlen($content),
        'sha256' => hash('sha256', $content),
        'filename' => 'IMG_2041.jpg',
        ...$overrides,
    ]);
}

/**
 * Dépose tout le contenu en morceaux puis clôt l'envoi.
 */
function uploadAll(string $mediaUuid, string $content): TestResponse
{
    /** @var FakeMultipartStorage $storage */
    $storage = test()->storage;
    $media = Media::query()->where('uuid', $mediaUuid)->sole();
    $parts = [];

    foreach (str_split($content, $media->part_size) as $index => $chunk) {
        $parts[] = ['number' => $index + 1, 'etag' => $storage->putPart((string) $media->upload_id, $index + 1, $chunk)];
    }

    return test()->postJson(route('api.v1.medias.complete', $mediaUuid), ['parts' => $parts]);
}

it('annonce un média et renvoie les URL signées de ses morceaux [MOB-16]', function () {
    announce(jpeg())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.upload.part_count', 1)
        ->assertJsonPath('data.upload.missing_parts.0.number', 1)
        ->assertJsonPath('data.upload.missing_parts.0.url', fn (string $url) => str_contains($url, 'partNumber=1'))
        ->assertJsonMissingPath('data.path');
});

it('reprend l’envoi là où il s’est arrêté, sans tout renvoyer [MOB-16]', function () {
    app(Settings::class)->set('media.max_file_size_mb', 50);
    config(['jerelais.media.part_size_mb' => 5]);
    $content = random_bytes(12 * 1024 * 1024);
    $uuid = announce($content, ['kind' => 'video', 'mime' => 'video/mp4'])->assertJsonPath('data.upload.part_count', 3)->json('data.uuid');

    $media = Media::query()->sole();
    $this->storage->putPart((string) $media->upload_id, 1, substr($content, 0, 5 * 1024 * 1024));

    $this->getJson(route('api.v1.medias.show', $uuid))
        ->assertJsonCount(1, 'data.upload.uploaded_parts')
        ->assertJsonCount(2, 'data.upload.missing_parts')
        ->assertJsonPath('data.upload.missing_parts.0.number', 2);
});

it('est idempotent sur le client_uuid du média', function () {
    $payload = ['client_uuid' => (string) Str::uuid()];
    $content = jpeg();

    announce($content, $payload)->assertCreated();
    announce($content, $payload)->assertOk();

    expect(Media::query()->count())->toBe(1);
});

it('contrôle la photo reçue : type, empreinte, dimensions, vignette sans EXIF, empreinte perceptuelle [§10, COO-13]', function () {
    $content = jpeg();
    $uuid = announce($content)->json('data.uuid');

    uploadAll($uuid, $content)->assertOk()->assertJsonPath('data.status', 'ready');

    $media = Media::query()->with('variants')->sole();
    $thumbnail = $media->variants->sole();
    Storage::disk('medias')->assertExists($media->path);
    Storage::disk('medias')->assertExists($thumbnail->path);

    expect($media->sha256)->toBe(hash('sha256', $content))
        ->and($media->mime)->toBe('image/jpeg')
        ->and([$media->width, $media->height])->toBe([800, 600])
        ->and($media->phash)->toMatch('/^[0-9a-f]{16}$/')
        ->and($thumbnail->width)->toBe(640)
        ->and(AuditLog::query()->where('action', 'media.received')->exists())->toBeTrue();

    expect(@exif_read_data('data://image/jpeg;base64,'.base64_encode(Storage::disk('medias')->get($thumbnail->path))) ?: [])->not->toHaveKey('Make');
});

it('donne une empreinte perceptuelle proche à une image recompressée et éloignée à une autre image', function () {
    $hash = fn (string $jpeg) => PerceptualHash::fromImage(imagecreatefromstring($jpeg));

    $original = $hash(jpeg());
    $resized = $hash(jpeg(400, 300));
    $other = $hash(jpeg(800, 600, [20, 60, 200]));

    expect(PerceptualHash::distance($original, $resized))->toBeLessThanOrEqual(6)
        ->and(PerceptualHash::distance($original, $original))->toBe(0);
    expect($other)->toMatch('/^[0-9a-f]{16}$/');
});

it('refuse un fichier dont le contenu n’est pas le type annoncé, et le supprime [§10]', function () {
    $content = '<?php echo "pas une image";';
    $uuid = announce($content)->json('data.uuid');

    uploadAll($uuid, $content)->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection', 'Le contenu du fichier ne correspond pas à un format accepté.');

    Storage::disk('medias')->assertMissing(Media::query()->sole()->path);
});

it('refuse un fichier altéré pendant l’envoi (empreinte SHA-256 différente)', function () {
    $content = jpeg();
    $uuid = announce($content, ['sha256' => hash('sha256', 'autre chose')])->json('data.uuid');

    uploadAll($uuid, $content)->assertJsonPath('data.status', 'rejected');
    expect(Media::query()->sole()->rejection)->toBe('checksum');
});

it('refuse et trace un fichier infecté [§10]', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            return 'Win.Test.EICAR_HDB-1';
        }
    });
    $content = jpeg();
    $uuid = announce($content)->json('data.uuid');

    uploadAll($uuid, $content)->assertJsonPath('data.status', 'rejected');

    expect(AuditLog::query()->where('action', 'media.rejected')->sole()->payload)->toMatchArray(['reason' => 'infected', 'threat' => 'Win.Test.EICAR_HDB-1']);
});

it('refuse un envoi incomplet à la clôture', function () {
    app(Settings::class)->set('media.max_file_size_mb', 50);
    config(['jerelais.media.part_size_mb' => 5]);
    $content = random_bytes(11 * 1024 * 1024);
    $uuid = announce($content, ['kind' => 'video', 'mime' => 'video/mp4'])->json('data.uuid');
    $media = Media::query()->sole();

    $etag = $this->storage->putPart((string) $media->upload_id, 1, substr($content, 0, 5 * 1024 * 1024));

    $this->postJson(route('api.v1.medias.complete', $uuid), ['parts' => [['number' => 1, 'etag' => $etag]]])
        ->assertJsonValidationErrors('parts');
});

it('limite la taille, le nombre et les formats annoncés [MOB-11]', function () {
    app(Settings::class)->set('media.max_medias_per_relais', 1);

    announce('x', ['size' => 600 * 1024 * 1024])->assertJsonValidationErrors(['size' => 'Ce fichier dépasse la taille maximale de 500 Mo.']);
    announce('x', ['mime' => 'application/pdf'])->assertJsonValidationErrors('mime');
    announce(jpeg())->assertCreated();
    announce(jpeg())->assertJsonValidationErrors(['relais' => 'Un relais contient au plus 1 médias.']);
});

it('lit la durée, la date et la position d’une vidéo, et en tire une vignette [MOB-12, MOB-13]', function () {
    $path = tempnam(sys_get_temp_dir(), 'video').'.mp4';
    exec('ffmpeg -v error -y -f lavfi -i testsrc=duration=2:size=320x240:rate=15 -metadata creation_time=2026-10-19T08:30:00Z -metadata location="+05.3410-004.0690/" -movflags use_metadata_tags -pix_fmt yuv420p '.escapeshellarg($path), result_code: $code);

    if ($code !== 0) {
        $this->markTestSkipped('FFmpeg indisponible.');
    }

    $content = (string) file_get_contents($path);
    $uuid = announce($content, ['kind' => 'video', 'mime' => 'video/mp4', 'is_imported' => true])->json('data.uuid');

    uploadAll($uuid, $content)->assertJsonPath('data.status', 'ready')->assertJsonPath('data.duration_s', 2);

    $media = Media::query()->with('variants')->sole();
    expect($media->captured_at?->toIso8601ZuluString())->toBe('2026-10-19T08:30:00Z')
        ->and($media->metadata['gps'])->toBe(['lat' => 5.341, 'lng' => -4.069])
        ->and($media->is_imported)->toBeTrue()
        ->and([$media->width, $media->height])->toBe([320, 240])
        ->and($media->variants)->toHaveCount(1);
});

it('refuse une vidéo plus longue que la durée maximale [MOB-11]', function () {
    app(Settings::class)->set('media.video_max_seconds', 1);
    $path = tempnam(sys_get_temp_dir(), 'video').'.mp4';
    exec('ffmpeg -v error -y -f lavfi -i testsrc=duration=3:size=160x120:rate=10 -pix_fmt yuv420p '.escapeshellarg($path), result_code: $code);

    if ($code !== 0) {
        $this->markTestSkipped('FFmpeg indisponible.');
    }

    $content = (string) file_get_contents($path);
    $uuid = announce($content, ['kind' => 'video', 'mime' => 'video/mp4'])->json('data.uuid');

    uploadAll($uuid, $content)->assertJsonPath('data.status', 'rejected');
    expect(Media::query()->sole()->rejection)->toBe('too_long');
});

it('n’accepte les médias que sur ses propres relais', function () {
    Sanctum::actingAs(RelaisProfile::factory()->create()->user);

    announce(jpeg())->assertForbidden();
    $this->getJson(route('api.v1.medias.show', attachReadyMedia(RelaisItem::query()->sole())))->assertNotFound();
});

it('refuse un fichier plus gros que la taille annoncée, avant tout téléchargement (revue sécurité)', function () {
    $uuid = announce('petit')->assertCreated()->json('data.uuid');
    $media = Media::query()->sole();
    $etag = $this->storage->putPart((string) $media->upload_id, 1, str_repeat('x', 4096));

    $this->postJson(route('api.v1.medias.complete', $uuid), ['parts' => [['number' => 1, 'etag' => $etag]]])
        ->assertJsonValidationErrors(['size' => 'La taille du fichier reçu ne correspond pas à celle annoncée : recommencez l’envoi.']);

    expect($media->fresh())->status->toBe(MediaStatus::Rejected)->upload_id->toBeNull();
});
