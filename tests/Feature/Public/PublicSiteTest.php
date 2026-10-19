<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Editorial\Actions\PublishToShowcase;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Domain\Public\Mail\ContactMessage;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Validation\Show;
use App\Livewire\Public\Contact;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    fakeSms();
    fakeMultipart();
    $this->commune = Commune::factory()->create(['name' => 'Yopougon']);
    $this->redacteur = User::factory()->withTwoFactor()->withRole(Role::RedacteurEnChef)->create();
});

function broadcastRelais(array $attributes = []): RelaisItem
{
    $relais = RelaisItem::factory()->create(['commune_id' => test()->commune->id, 'state' => Broadcast::class, 'broadcast_programme' => 'Journal de 20 h', 'broadcast_at' => now(), ...$attributes]);
    $media = attachReadyMedia($relais);
    $variant = $media->variants()->create(['kind' => 'thumbnail', 'disk' => 'medias', 'path' => 'relais/'.$relais->uuid.'/vignettes/t.jpg', 'mime' => 'image/jpeg', 'size' => 10]);
    Storage::disk('medias')->put($variant->path, 'vignette');

    return $relais;
}

it('publie un relais diffusé sur la vitrine, avec un texte rédigé par la rédaction [WEB-05, ADM-17]', function () {
    $relais = broadcastRelais(['title' => 'Titre interne']);

    Livewire::actingAs($this->redacteur)->test(Show::class, ['relais' => $relais])
        ->call('openEditorial', 'showcase')
        ->set('showcaseTitle', 'Les habitants nettoient le marché')
        ->set('showcaseText', 'Au lendemain de l’incendie, la solidarité.')
        ->call('showcase')
        ->assertHasNoErrors();

    $this->get(route('showcase'))->assertOk()->assertSee('Les habitants nettoient le marché')->assertSee('Yopougon')->assertDontSee('Titre interne');
    $this->get(route('showcase.image', $relais))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get(route('home'))->assertSee('Vu sur REFLET TV.');
});

it('refuse de publier l’image d’un relais à flouter, et ne sert jamais une image non publiée', function () {
    $relais = broadcastRelais(['blur_required' => true]);

    expect(fn () => app(PublishToShowcase::class)->publish($relais, $this->redacteur, 'Titre', '', true))->toThrow(EditorialRefused::class);
    $this->get(route('showcase.image', $relais))->assertNotFound();

    app(PublishToShowcase::class)->publish($relais, $this->redacteur, 'Titre', '', false);
    $this->get(route('showcase.image', $relais))->assertNotFound();
});

it('publie la carte du réseau sans aucune donnée personnelle [WEB-06, §9.4]', function () {
    $profile = RelaisProfile::factory()->create(['commune_id' => $this->commune->id, 'display_name' => 'Awa K.']);
    RelaisProfile::factory()->suspended()->create(['commune_id' => $this->commune->id]);

    $this->get(route('network'))->assertOk()->assertSee('Yopougon')->assertSee('Relais actifs')->assertDontSee('Awa K.')->assertDontSee($profile->number);
});

it('sert les pages légales, cookies, plan du site et robots [WEB-07, WEB-08]', function () {
    $this->get(route('legal-notice'))->assertOk()->assertSee('Mentions légales');
    $this->get(route('cookies'))->assertOk()->assertSee('Aucun bandeau de consentement');
    $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(route('showcase'), false);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /console')->assertSee('Sitemap: '.route('sitemap'));
    $this->get(route('home'))->assertSee('<link rel="canonical"', false);
    $this->get(route('login'))->assertSee('noindex, nofollow', false);
});

it('transmet le formulaire de contact, avec limite et piège à robots [WEB-01]', function () {
    Mail::fake();

    Livewire::test(Contact::class)
        ->set('name', 'Jean')->set('email', 'jean@example.com')->set('message', 'Bonjour, comment devenir coordonnateur ?')
        ->call('send')->assertSet('sent', true);
    Mail::assertQueued(ContactMessage::class, fn (ContactMessage $mail) => $mail->senderEmail === 'jean@example.com');

    Livewire::test(Contact::class)
        ->set('name', 'Robot')->set('email', 'bot@example.com')->set('message', 'Spam spam spam spam')->set('website', 'http://spam')
        ->call('send')->assertSet('sent', true);
    Mail::assertQueuedCount(1);

    foreach (range(1, 2) as $attempt) {
        Livewire::test(Contact::class)->set('name', 'X')->set('email', 'x@example.com')->set('message', 'Encore un message.')->call('send');
    }
    Livewire::test(Contact::class)->set('name', 'X')->set('email', 'x@example.com')->set('message', 'Encore un message.')->call('send')->assertHasErrors('message');
});
