<?php

use App\Domain\Access\Enums\Permission;
use App\Http\Controllers\Console\CandidatureDocumentController;
use App\Http\Controllers\Console\ExportController;
use App\Http\Controllers\Console\MediaController as ConsoleMediaController;
use App\Http\Controllers\Console\SecurityController;
use App\Http\Controllers\Public\BadgeVerificationController;
use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Public\PublicPagesController;
use App\Http\Controllers\Public\ShowcaseController;
use App\Http\Controllers\Relais\BadgePhotoController;
use App\Http\Controllers\Relais\DocumentPreviewController;
use App\Http\Controllers\Relais\LogoutController as RelaisLogoutController;
use App\Http\Controllers\Relais\WebMediaController;
use App\Livewire\Console\Account\Index as AccountPage;
use App\Livewire\Console\Appels\Index as AppelsPage;
use App\Livewire\Console\Appels\Show as AppelPage;
use App\Livewire\Console\Audit\Index as AuditPage;
use App\Livewire\Console\Candidatures\Index as CandidaturesPage;
use App\Livewire\Console\Candidatures\Show as CandidaturePage;
use App\Livewire\Console\Collections\Index as CollectionsPage;
use App\Livewire\Console\Collections\Show as CollectionPage;
use App\Livewire\Console\Communes\Index as CommunesPage;
use App\Livewire\Console\Dashboard;
use App\Livewire\Console\Editorial\Index as EditorialPage;
use App\Livewire\Console\Flux\Index as FluxPage;
use App\Livewire\Console\Legal\Index as LegalPage;
use App\Livewire\Console\Map\Index as MapPage;
use App\Livewire\Console\Messagerie\Index as MessageriePage;
use App\Livewire\Console\Newsroom\Index as NewsroomPage;
use App\Livewire\Console\Relais\Index as RelaisPage;
use App\Livewire\Console\Relais\Show as RelaisProfilePage;
use App\Livewire\Console\Settings\Index as SettingsPage;
use App\Livewire\Console\Team\Index as TeamPage;
use App\Livewire\Console\Validation\Index as ValidationPage;
use App\Livewire\Console\Validation\Show as ValidationShowPage;
use App\Livewire\Public\Contact as ContactPage;
use App\Livewire\Relais\Appels as RelaisAppelsPage;
use App\Livewire\Relais\Dossier;
use App\Livewire\Relais\Envoyer as RelaisSendPage;
use App\Livewire\Relais\MesRelais as RelaisItemsPage;
use App\Livewire\Relais\Messages as RelaisMessagesPage;
use App\Livewire\Relais\Rejoindre;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');
Route::get('textes/{type}', LegalPageController::class)->name('legal.show');
Route::get('vu-sur-reflet-tv', [ShowcaseController::class, 'index'])->name('showcase');
Route::get('vu-sur-reflet-tv/{relais}/image', [ShowcaseController::class, 'image'])->name('showcase.image');
Route::get('reseau', [PublicPagesController::class, 'network'])->name('network');
Route::get('mentions-legales', [PublicPagesController::class, 'legalNotice'])->name('legal-notice');
Route::get('cookies', [PublicPagesController::class, 'cookies'])->name('cookies');
Route::get('sitemap.xml', [PublicPagesController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', fn () => response(
    "User-agent: *\nDisallow: /console\nDisallow: /espace-relais\nDisallow: /badge\nDisallow: /badge-photo\nDisallow: /livewire\nDisallow: /api\n\nSitemap: ".route('sitemap')."\n",
    200,
    ['Content-Type' => 'text/plain'],
))->name('robots');
Route::livewire('contact', ContactPage::class)->name('contact');
Route::get('badge/{token}', BadgeVerificationController::class)->middleware('throttle:30,1')->name('badge.verify');
Route::get('badge-photo/{profile}', BadgePhotoController::class)->middleware('signed')->name('relais.badge.photo');

/*
 * Devenir Relais et espace candidat sur le site (WEB-02).
 */
Route::livewire('devenir-relais', Rejoindre::class)->name('relais.join');

Route::middleware('auth')->prefix('espace-relais')->name('relais.')->group(function () {
    Route::livewire('candidature', Dossier::class)->name('candidature');
    Route::get('candidature/pieces/{document}', DocumentPreviewController::class)->name('documents.show');
    Route::post('deconnexion', RelaisLogoutController::class)->name('logout');

    // Espace Relais (WEB-03, WEB-04)
    Route::livewire('envoyer', RelaisSendPage::class)->name('send');
    Route::livewire('mes-relais', RelaisItemsPage::class)->name('items');
    Route::livewire('appels', RelaisAppelsPage::class)->name('appels');
    Route::livewire('messages', RelaisMessagesPage::class)->name('messages');
    Route::post('relais/{relais}/medias', [WebMediaController::class, 'store'])->middleware('throttle:120,1')->name('medias.store');
    Route::get('medias/{media}', [WebMediaController::class, 'show'])->name('medias.show');
    Route::post('medias/{media}/complete', [WebMediaController::class, 'complete'])->name('medias.complete');
});

/*
 * Console REFLET TV et back-office coordonnateur : équipe uniquement,
 * double authentification obligatoire (sauf pour la page qui permet de l'activer).
 * Chaque page vérifie aussi sa permission (route + composant).
 */
Route::middleware(['auth', 'staff'])->prefix('console')->name('console.')->group(function () {
    Route::get('securite', SecurityController::class)->name('security');

    Route::middleware('two-factor')->group(function () {
        Route::livewire('/', Dashboard::class)->name('home');
        Route::livewire('compte', AccountPage::class)->name('account');
        Route::livewire('communes', CommunesPage::class)->middleware('permission:'.Permission::TerritoireManage->value)->name('communes');
        Route::livewire('equipe', TeamPage::class)->middleware('permission:'.Permission::UsersManage->value)->name('team');
        Route::livewire('parametres', SettingsPage::class)->middleware('permission:'.Permission::SettingsManage->value)->name('settings');
        Route::livewire('candidatures', CandidaturesPage::class)->middleware('permission:'.Permission::CandidaturesValidate->value)->name('candidatures');
        Route::livewire('candidatures/{candidature}', CandidaturePage::class)->middleware('permission:'.Permission::CandidaturesValidate->value)->name('candidatures.show');
        Route::get('candidatures/pieces/{document}', CandidatureDocumentController::class)->middleware('signed')->name('candidatures.document');
        Route::livewire('flux', FluxPage::class)->middleware('permission:'.Permission::RelaisViewPending->value)->name('flux');
        Route::livewire('carte', MapPage::class)->middleware('permission:'.Permission::RelaisViewPending->value)->name('map');
        Route::livewire('salle', NewsroomPage::class)->middleware('permission:'.Permission::RelaisViewPending->value)->name('newsroom');
        Route::livewire('validation', ValidationPage::class)->middleware('permission:'.Permission::RelaisViewPending->value)->name('validation');
        Route::livewire('validation/{relais}', ValidationShowPage::class)->middleware('permission:'.Permission::RelaisViewPending->value)->name('validation.show');
        Route::get('medias/{media}/{variant?}', ConsoleMediaController::class)->whereIn('variant', ['thumbnail'])->name('medias.show');
        Route::livewire('appels', AppelsPage::class)->middleware('permission:'.Permission::AppelsCreate->value.'|'.Permission::AppelsCreateLocal->value)->name('appels');
        Route::livewire('appels/{appel}', AppelPage::class)->middleware('permission:'.Permission::AppelsCreate->value.'|'.Permission::AppelsCreateLocal->value)->name('appels.show');
        Route::livewire('relais', RelaisPage::class)->middleware('permission:'.Permission::RelaisProfilesSuspend->value.'|'.Permission::RelaisValidate->value)->name('relais');
        Route::livewire('relais/{profile}', RelaisProfilePage::class)->middleware('permission:'.Permission::RelaisProfilesSuspend->value.'|'.Permission::RelaisValidate->value)->name('relais.show');
        Route::livewire('messagerie', MessageriePage::class)->middleware('permission:'.Permission::RelaisValidate->value)->name('messagerie');
        Route::livewire('redaction', EditorialPage::class)->middleware('permission:'.Permission::RelaisArbitrate->value)->name('editorial');
        Route::livewire('collections', CollectionsPage::class)->middleware('permission:'.Permission::RelaisArbitrate->value)->name('collections');
        Route::livewire('collections/{collection}', CollectionPage::class)->middleware('permission:'.Permission::RelaisArbitrate->value)->name('collections.show');
        Route::post('relais/{relais}/export', [ExportController::class, 'relais'])->name('export.relais');
        Route::post('collections/{collection}/export', [ExportController::class, 'collection'])->name('export.collection');
        Route::post('medias/{media}/original', [ExportController::class, 'original'])->name('export.original');
        Route::livewire('textes', LegalPage::class)->middleware('permission:'.Permission::SettingsManage->value)->name('legal');
        Route::livewire('journal', AuditPage::class)->middleware('permission:'.Permission::AuditView->value)->name('audit');
    });
});

/*
 * Maquettes de l'étape 0 (validation des écrans par REFLET TV).
 * Jamais servies en production.
 */
if (! app()->isProduction()) {
    Route::prefix('maquettes')->name('maquettes.')->group(function () {
        Route::view('/console', 'maquettes.console')->name('console');
        Route::view('/coordinateur', 'maquettes.coordinateur')->name('coordinateur');
    });
}
