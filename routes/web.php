<?php

use App\Http\Controllers\Console\HomeController;
use App\Http\Controllers\Console\SecurityController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

/*
 * Console REFLET TV et back-office coordonnateur : équipe uniquement,
 * double authentification obligatoire (sauf pour la page qui permet de l'activer).
 */
Route::middleware(['auth', 'staff'])->prefix('console')->name('console.')->group(function () {
    Route::get('securite', SecurityController::class)->name('security');

    Route::middleware('two-factor')->group(function () {
        Route::get('/', HomeController::class)->name('home');
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
