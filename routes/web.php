<?php

use App\Domain\Access\Enums\Permission;
use App\Http\Controllers\Console\CandidatureDocumentController;
use App\Http\Controllers\Console\MediaController as ConsoleMediaController;
use App\Http\Controllers\Console\SecurityController;
use App\Http\Controllers\Public\BadgeVerificationController;
use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Relais\BadgePhotoController;
use App\Http\Controllers\Relais\DocumentPreviewController;
use App\Http\Controllers\Relais\LogoutController as RelaisLogoutController;
use App\Livewire\Console\Account\Index as AccountPage;
use App\Livewire\Console\Audit\Index as AuditPage;
use App\Livewire\Console\Candidatures\Index as CandidaturesPage;
use App\Livewire\Console\Candidatures\Show as CandidaturePage;
use App\Livewire\Console\Communes\Index as CommunesPage;
use App\Livewire\Console\Dashboard;
use App\Livewire\Console\Flux\Index as FluxPage;
use App\Livewire\Console\Legal\Index as LegalPage;
use App\Livewire\Console\Map\Index as MapPage;
use App\Livewire\Console\Newsroom\Index as NewsroomPage;
use App\Livewire\Console\Settings\Index as SettingsPage;
use App\Livewire\Console\Team\Index as TeamPage;
use App\Livewire\Console\Validation\Index as ValidationPage;
use App\Livewire\Console\Validation\Show as ValidationShowPage;
use App\Livewire\Relais\Dossier;
use App\Livewire\Relais\Rejoindre;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');
Route::get('textes/{type}', LegalPageController::class)->name('legal.show');
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
