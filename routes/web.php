<?php

use App\Domain\Access\Enums\Permission;
use App\Http\Controllers\Console\SecurityController;
use App\Livewire\Console\Account\Index as AccountPage;
use App\Livewire\Console\Audit\Index as AuditPage;
use App\Livewire\Console\Communes\Index as CommunesPage;
use App\Livewire\Console\Dashboard;
use App\Livewire\Console\Settings\Index as SettingsPage;
use App\Livewire\Console\Team\Index as TeamPage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

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
