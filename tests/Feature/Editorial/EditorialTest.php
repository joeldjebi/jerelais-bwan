<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Rejected;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Sensitive;
use App\Domain\Contribution\States\Validated;
use App\Domain\Editorial\Actions\ExportRelaisMedias;
use App\Domain\Editorial\Models\ExportLog;
use App\Domain\Editorial\Models\RelaisCollection;
use App\Domain\Editorial\Support\CreditLine;
use App\Livewire\Console\Collections\Index as Collections;
use App\Livewire\Console\Editorial\Index as Editorial;
use App\Livewire\Console\Validation\Show;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->sms = fakeSms();
    fakeMultipart();
    $this->redacteur = User::factory()->withTwoFactor()->withRole(Role::RedacteurEnChef)->create(['name' => 'Rédac Chef']);
    $this->journaliste = User::factory()->withTwoFactor()->withRole(Role::Journaliste)->create(['name' => 'Jean Journaliste']);
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
});

function readyRelais(string $state = Validated::class, array $attributes = []): RelaisItem
{
    $relais = RelaisItem::factory()->create(['state' => $state, 'title' => 'Inondation à Niangon', ...$attributes]);
    $media = attachReadyMedia($relais);
    $media->forceFill(['mime' => 'image/jpeg', 'sha256' => str_repeat('d', 64)])->save();
    Storage::disk('medias')->put($media->path, 'contenu original');

    return $relais;
}

it('arbitre un relais sensible avec un motif, tracé dans l’audit [§8.2]', function () {
    $relais = readyRelais(Sensitive::class);

    Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $relais])
        ->call('openEditorial', 'arbitrate-validate')
        ->call('arbitrate', true)
        ->assertHasErrors('editorial')
        ->set('editorReason', 'Images floutées, sujet d’intérêt public.')
        ->call('arbitrate', true)
        ->assertHasNoErrors();

    expect($relais->fresh()->state)->toBeInstanceOf(Validated::class)
        ->and(AuditLog::query()->where('action', 'relais.arbitrated')->sole()->payload)->toMatchArray(['from' => 'sensitive', 'to' => 'validated']);
});

it('permet à la rédaction de revenir sur une validation [§8.2]', function () {
    $relais = readyRelais();

    Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $relais])
        ->call('openEditorial', 'arbitrate-reject')
        ->set('editorReason', 'Contenu déjà publié ailleurs.')
        ->call('arbitrate', false);

    expect($relais->fresh()->state)->toBeInstanceOf(Rejected::class);
});

it('sélectionne un relais validé et l’affecte à un journaliste et une émission [ADM-10]', function () {
    $relais = readyRelais();

    Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $relais])
        ->call('openEditorial', 'select')
        ->set('journalist', $this->journaliste->uuid)
        ->set('programme', 'Journal de 20 h')
        ->call('selectRelais')
        ->assertHasNoErrors();

    $relais->refresh();
    expect($relais->state)->toBeInstanceOf(Selected::class)
        ->and($relais->assigned_to)->toBe($this->journaliste->id)
        ->and($relais->programme)->toBe('Journal de 20 h')
        ->and($this->sms->sent[0]['message'])->toContain('la rédaction a retenu');

    Livewire::actingAs($this->journaliste)->test(Editorial::class)
        ->set('tab', 'selection')->set('mine', true)
        ->assertSee('Inondation à Niangon');
});

it('marque un relais diffusé avec date, émission et lien, et prévient le Relais [ADM-13]', function () {
    $relais = readyRelais(Selected::class);

    Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $relais])
        ->call('openEditorial', 'broadcast')
        ->set('broadcastProgramme', 'Journal de 20 h')
        ->set('broadcastUrl', 'pas un lien')
        ->call('markBroadcast')
        ->assertHasErrors('broadcastUrl')
        ->set('broadcastUrl', 'https://reflet.tv/replay/20h')
        ->call('markBroadcast')
        ->assertHasNoErrors();

    $relais->refresh();
    expect($relais->state)->toBeInstanceOf(Broadcast::class)
        ->and($relais->broadcast_programme)->toBe('Journal de 20 h')
        ->and($relais->broadcast_url)->toBe('https://reflet.tv/replay/20h')
        ->and(collect($this->sms->sent)->last()['message'])->toContain('est passé sur REFLET TV');
});

it('télécharge un ZIP des originaux avec une fiche PDF, et trace l’export [ADM-11]', function () {
    $relais = readyRelais(attributes: ['blur_required' => true]);

    $response = $this->actingAs($this->journaliste)->post(route('console.export.relais', $relais));
    $response->assertOk()->assertDownload('inondation-a-niangon.zip');

    $zipPath = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($index) => $zip->getNameIndex($index))->all();

    expect($names)->toContain('01-inondation-a-niangon/fiche.pdf')
        ->and($names)->toContain('01-inondation-a-niangon/01-photo.jpg')
        ->and(str_starts_with((string) $zip->getFromName('01-inondation-a-niangon/fiche.pdf'), '%PDF'))->toBeTrue()
        ->and($zip->getFromName('01-inondation-a-niangon/01-photo.jpg'))->toBe('contenu original')
        ->and(ExportLog::query()->sole()->derogation_reason)->toBeNull()
        ->and(AuditLog::query()->where('action', 'relais.exported')->exists())->toBeTrue();
});

it('bloque l’export d’un relais non validé, sauf dérogation motivée d’un administrateur [ADM-12]', function () {
    $relais = readyRelais(Sensitive::class);

    $this->actingAs($this->journaliste)->post(route('console.export.relais', $relais))->assertRedirect()->assertSessionHas('toast');
    expect(ExportLog::query()->count())->toBe(0);

    $this->actingAs($this->admin)->post(route('console.export.relais', $relais))->assertSessionHas('toast');
    expect(ExportLog::query()->count())->toBe(0);

    $this->actingAs($this->admin)->post(route('console.export.relais', $relais), ['derogation' => 'Demande de la justice'])->assertOk();

    expect(ExportLog::query()->sole()->derogation_reason)->toBe('Demande de la justice')
        ->and(AuditLog::query()->where('action', 'relais.export_derogation')->sole()->payload['reason'])->toBe('Demande de la justice');
});

it('réserve les originaux aux rôles habilités [Annexe A]', function () {
    $relais = readyRelais();
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($relais->commune)->create();

    $this->actingAs($coordo)->post(route('console.export.relais', $relais))->assertForbidden();
});

it('regroupe des relais en collection et les télécharge en lot [ADM-15]', function () {
    $relais = readyRelais();
    $autre = readyRelais(attributes: ['title' => 'Route coupée']);
    $nonValide = readyRelais(Sensitive::class, ['title' => 'À arbitrer']);

    Livewire::actingAs($this->redacteur)->test(Collections::class)
        ->set('name', 'Inondations de juin')
        ->call('create');
    $collection = RelaisCollection::query()->sole();

    foreach ([$relais, $autre, $nonValide] as $item) {
        Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $item])
            ->call('openEditorial', 'collection')
            ->set('collection', $collection->uuid)
            ->call('addToCollection');
    }

    expect($collection->relais()->count())->toBe(3);

    $response = $this->actingAs($this->journaliste)->post(route('console.export.collection', $collection))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $folders = collect(range(0, $zip->numFiles - 1))->map(fn ($index) => explode('/', (string) $zip->getNameIndex($index))[0])->unique()->values()->all();

    expect($folders)->toHaveCount(2);
});

it('produit une fiche avec le crédit choisi et la consigne de floutage', function () {
    $relais = readyRelais(attributes: ['blur_required' => true, 'credit_mode' => 'pseudonym', 'credit_name' => 'Le Veilleur']);

    $html = view('pdf.relais-fiche', ['relais' => $relais->load('medias', 'relaisProfile', 'commune', 'category'), 'credit' => CreditLine::for($relais), 'validator' => null])->render();

    expect($html)->toContain('Le Veilleur / JE RELAIS')->toContain('floutage nécessaire');
    expect(str_starts_with(app(ExportRelaisMedias::class)->fiche($relais), '%PDF'))->toBeTrue();
});
