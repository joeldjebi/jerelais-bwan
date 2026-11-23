<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Editorial\Enums\ShowcaseVisual;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Enums\MediaStatus;
use App\Livewire\Console\Validation\Show;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Visuel public d'un relais sur la vitrine (WEB-05, ADM-17) : image d'origine, floutée automatiquement, ou version
 * floutée déposée par la rédaction. Les originaux restent privés.
 */

beforeEach(function () {
    fakeSms();
    fakeMultipart();
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            return null;
        }
    });
    $this->redacteur = User::factory()->withTwoFactor()->withRole(Role::RedacteurEnChef)->create();
});

function showcaseJpegBytes(int $width = 640, int $height = 480): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x += 20) {
        imagefilledrectangle($image, $x, 0, $x + 9, $height, imagecolorallocate($image, 255, 255, 255));
    }
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

/**
 * Relais diffusé avec une photo contrôlée et sa vignette.
 */
function broadcastRelaisWithThumbnail(array $attributes = []): RelaisItem
{
    $relais = RelaisItem::factory()->create(['state' => Broadcast::class, 'title' => 'Marché de Niangon', ...$attributes]);
    $media = attachReadyMedia($relais);
    $path = 'relais/'.$relais->uuid.'/vignettes/'.$media->uuid.'.jpg';
    Storage::disk('medias')->put($path, showcaseJpegBytes());
    $media->variants()->create(['kind' => 'thumbnail', 'disk' => 'medias', 'path' => $path, 'mime' => 'image/jpeg', 'size' => 10, 'width' => 640, 'height' => 480]);

    return $relais;
}

it('publie une image floutée automatiquement quand un floutage est demandé, jamais l’originale', function () {
    $relais = broadcastRelaisWithThumbnail(['has_minors' => true]);

    Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $relais])
        ->call('openEditorial', 'showcase')
        ->assertSet('showcaseVisual', 'blurred')
        ->set('showcaseVisual', 'original')
        ->call('showcase')
        ->assertHasErrors('editorial')
        ->set('showcaseVisual', 'blurred')
        ->call('showcase')
        ->assertHasNoErrors();

    $relais->refresh();
    expect($relais->showcase_visual)->toBe(ShowcaseVisual::Blurred)
        ->and($relais->showcase_blurred_path)->toStartWith('vitrine/'.$relais->uuid.'/')
        ->and(Storage::disk('medias')->exists($relais->showcase_blurred_path))->toBeTrue();

    $original = Storage::disk('medias')->get($relais->medias()->first()->variants()->first()->path);
    $public = $this->get(route('showcase.image', $relais))->assertOk()->streamedContent();

    expect($public)->not->toBe($original);
    $this->getJson(route('api.v1.diffusions.index'))->assertJsonPath('data.0.image_url', route('showcase.image', $relais))->assertJsonPath('data.0.visual', 'blurred');
});

it('publie la version floutée déposée par la rédaction (photo), et son image publique', function () {
    $relais = broadcastRelaisWithThumbnail(['blur_required' => true]);

    Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $relais])
        ->call('openEditorial', 'showcase')
        ->set('showcaseVisual', 'edited')
        ->call('showcase')
        ->assertHasErrors('editedFile')
        ->set('editedFile', UploadedFile::fake()->createWithContent('floute.jpg', showcaseJpegBytes(2000, 1500)))
        ->call('showcase')
        ->assertHasNoErrors();

    $relais->refresh();
    expect($relais->showcase_visual)->toBe(ShowcaseVisual::Edited)
        ->and($relais->showcase_media_mime)->toBe('image/jpeg')
        ->and(Storage::disk('medias')->exists($relais->showcase_poster_path))->toBeTrue();

    $poster = imagecreatefromstring($this->get(route('showcase.image', $relais))->assertOk()->streamedContent());
    expect(imagesx($poster))->toBe(1280);
    $this->get(route('showcase.video', $relais))->assertNotFound();
});

it('n’expose jamais d’image tant qu’aucun fichier n’a fini son contrôle, puis toujours la même', function () {
    $relais = RelaisItem::factory()->create(['state' => Broadcast::class, 'showcased_at' => now(), 'showcase_title' => 'Titre', 'showcase_with_image' => true, 'showcase_visual' => 'original']);
    $processing = attachReadyMedia($relais, 'video');
    $processing->forceFill(['status' => MediaStatus::Processing])->save();

    $this->getJson(route('api.v1.diffusions.index'))->assertJsonPath('data.0.image_url', null);
    $this->get(route('showcase.image', $relais))->assertNotFound();

    $video = attachReadyMedia($relais, 'video');
    $video->variants()->create(['kind' => 'thumbnail', 'disk' => 'medias', 'path' => 'v.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'width' => 1, 'height' => 1]);
    $photo = attachReadyMedia($relais);
    $photo->variants()->create(['kind' => 'thumbnail', 'disk' => 'medias', 'path' => 'p.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'width' => 1, 'height' => 1]);
    Storage::disk('medias')->put('p.jpg', 'photo');
    Storage::disk('medias')->put('v.jpg', 'video');

    expect($this->get(route('showcase.image', $relais))->assertOk()->streamedContent())->toBe('photo');
});
