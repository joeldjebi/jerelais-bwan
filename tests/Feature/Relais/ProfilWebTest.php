<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Relais\Actions\EnrolAppRelais;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Relais\Profil;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('identity');
    $this->relais = User::factory()->withRole(Role::Relais)->create();
    app(EnrolAppRelais::class)->handle($this->relais);
    $this->commune = Commune::factory()->create();
});

it('complète le profil sur le site comme dans l’application, puis l’envoie pour vérification', function () {
    $component = Livewire::actingAs($this->relais)->test(Profil::class)
        ->assertSee('Mon profil')
        ->set('identite.last_name', 'Traoré')
        ->set('identite.first_names', 'Awa')
        ->set('identite.gender', 'femme')
        ->set('identite.birth_date', '2001-04-17')
        ->set('commune', $this->commune->uuid)
        ->set('documentType', 'cni')
        ->call('save')
        ->assertHasNoErrors();

    foreach (['recto', 'verso', 'selfie'] as $kind) {
        $component->set("uploads.{$kind}", UploadedFile::fake()->image("{$kind}.jpg"))->assertHasNoErrors();
    }

    // Le dernier fichier complète le dossier : il part tout seul en vérification (le bouton reste sans effet).
    $component->call('submit')->assertHasNoErrors();

    $verification = IdentityVerification::query()->where('user_id', $this->relais->id)->sole();

    expect($verification->status)->toBe(IdentityStatus::Submitted)
        ->and($verification->files()->count())->toBe(3)
        ->and($this->relais->refresh()->name)->toBe('Awa Traoré');
});

it('refuse l’envoi d’un profil incomplet, sans bloquer le reste de l’espace Relais', function () {
    Livewire::actingAs($this->relais)->test(Profil::class)
        ->call('submit')
        ->assertHasErrors('form');

    $this->actingAs($this->relais)->get(route('relais.send'))->assertOk();
});

it('affiche « Mon profil » dans la navigation de l’espace Relais', function () {
    $this->actingAs($this->relais)->get(route('relais.profile'))->assertOk()->assertSee('Mon profil')->assertSee('Envoyer pour vérification');
});

it('laisse un Relais recruté par candidature compléter sa commune et sa pièce sans toucher à son identité vérifiée', function () {
    $candidature = Candidature::withoutCommuneRestriction()->where('user_id', $this->relais->id)->sole();
    $candidature->forceFill(['last_name' => 'Bamba', 'first_names' => 'Mariam', 'status' => CandidatureStatus::Accepted])->save();

    Livewire::actingAs($this->relais)->test(Profil::class)
        ->assertSet('identite.last_name', 'Bamba')
        ->set('commune', $this->commune->uuid)
        ->set('documentType', 'passeport')
        ->call('save')
        ->assertHasNoErrors();

    expect(IdentityVerification::query()->where('user_id', $this->relais->id)->first()->document_type)->toBe('passeport');
});
