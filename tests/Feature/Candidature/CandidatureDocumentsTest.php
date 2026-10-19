<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Models\CandidatureDocument;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('identity');
    $this->candidat = User::factory()->withRole(Role::Candidat)->create();
    Sanctum::actingAs($this->candidat);
});

function sendDocument(string $type, ?UploadedFile $file = null): TestResponse
{
    return test()->post(route('api.v1.candidature.documents.store'), [
        'type' => $type,
        'file' => $file ?? UploadedFile::fake()->image('piece.jpg', 800, 600),
    ], ['Accept' => 'application/json']);
}

it('stocke la pièce sur le disque privé, sous un nom aléatoire, avec son empreinte [MOB-03]', function () {
    $file = UploadedFile::fake()->image('ma-cni-kouassi.jpg', 800, 600);

    sendDocument('id_front', $file)
        ->assertCreated()
        ->assertJsonPath('data.type', 'id_front')
        ->assertJsonPath('data.mime', 'image/jpeg')
        ->assertJsonMissingPath('data.path')
        ->assertJsonMissingPath('data.disk');

    $document = CandidatureDocument::query()->sole();
    Storage::disk('identity')->assertExists($document->path);

    expect($document->path)->not->toContain('kouassi')
        ->and($document->sha256)->toBe(hash_file('sha256', $file->getRealPath()))
        ->and(AuditLog::query()->where('action', 'candidature.document.uploaded')->sole()->payload['sha256'])->toBe($document->sha256);
});

it('remplace la pièce précédente du même type et supprime l’ancien fichier', function () {
    sendDocument('selfie')->assertCreated();
    $first = CandidatureDocument::query()->sole();

    sendDocument('selfie')->assertCreated();

    Storage::disk('identity')->assertMissing($first->path);
    expect(CandidatureDocument::query()->count())->toBe(1);
});

it('vérifie le type réel du fichier, pas son extension [§10]', function () {
    // Vrai fichier (pas un faux de Laravel) : le type est lu dans le contenu.
    $path = tempnam(sys_get_temp_dir(), 'piece');
    file_put_contents($path, '<?php echo "pas une image";');
    $disguised = new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);

    sendDocument('id_front', $disguised)->assertUnprocessable()->assertJsonValidationErrors('file');
    sendDocument('id_front', UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf'))->assertJsonValidationErrors('file');
    expect(CandidatureDocument::query()->count())->toBe(0);
});

it('refuse les fichiers trop lourds', function () {
    sendDocument('id_front', UploadedFile::fake()->image('lourd.jpg')->size(11_000))->assertJsonValidationErrors('file');
});

it('refuse et trace un fichier infecté sans le stocker [§10]', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            return 'Win.Test.EICAR_HDB-1';
        }
    });

    sendDocument('id_front')->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Ce fichier a été refusé par l’antivirus.']);

    expect(CandidatureDocument::query()->count())->toBe(0)
        ->and(Storage::disk('identity')->allFiles())->toBe([])
        ->and(AuditLog::query()->where('action', 'candidature.document.infected')->sole()->payload['threat'])->toBe('Win.Test.EICAR_HDB-1');
});

it('refuse le fichier si l’antivirus ne répond pas (pas d’acceptation sans contrôle)', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            throw VirusScanUnavailable::because('clamd injoignable');
        }
    });

    sendDocument('id_front')->assertServiceUnavailable();
    expect(Storage::disk('identity')->allFiles())->toBe([]);
});

it('ne demande l’autorisation parentale qu’aux mineurs [§9.3]', function () {
    sendDocument('parental_authorization')->assertJsonValidationErrors('type');

    $this->getJson(route('api.v1.candidature.show'))->assertJsonCount(4, 'data.documents');

    Candidature::query()->where('user_id', $this->candidat->id)->update(['is_minor' => true]);

    $this->getJson(route('api.v1.candidature.show'))
        ->assertJsonCount(5, 'data.documents')
        ->assertJsonPath('data.documents.4.type', 'parental_authorization');

    sendDocument('parental_authorization', UploadedFile::fake()->create('autorisation.pdf', 200, 'application/pdf'))
        ->assertCreated();
});

it('retire une pièce tant que le dossier n’est pas déposé', function () {
    sendDocument('selfie')->assertCreated();
    $path = CandidatureDocument::query()->sole()->path;

    $this->deleteJson(route('api.v1.candidature.documents.destroy', 'selfie'))->assertNoContent();
    Storage::disk('identity')->assertMissing($path);

    Candidature::query()->where('user_id', $this->candidat->id)->update(['status' => CandidatureStatus::Submitted->value]);
    sendDocument('selfie')->assertConflict();
    $this->deleteJson(route('api.v1.candidature.documents.destroy', 'selfie'))->assertConflict();
});

it('réserve l’envoi de pièces aux candidats', function () {
    Sanctum::actingAs(User::factory()->withRole(Role::Relais)->create());

    sendDocument('selfie')->assertForbidden();
});
