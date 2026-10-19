<?php

use App\Domain\Access\Enums\Permission;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordLoginController;
use App\Http\Controllers\Api\V1\Candidature\CandidatureController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ReferentielController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST v1 — consommée par l'application mobile (préfixe /api/v1)
|--------------------------------------------------------------------------
*/

Route::name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('otp', [OtpController::class, 'request'])->middleware('throttle:otp-request')->name('otp.request');
        Route::post('verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify')->name('otp.verify');
        Route::post('login', PasswordLoginController::class)->middleware('throttle:password-login')->name('login');
    });

    Route::get('communes', [ReferentielController::class, 'communes'])->name('communes.index');
    Route::get('categories', [ReferentielController::class, 'categories'])->name('categories.index');
    Route::get('settings', [ReferentielController::class, 'settings'])->name('settings.index');
    Route::get('communes/{commune}/localites', [CandidatureController::class, 'localites'])->name('communes.localites');
    Route::get('candidature/options', [CandidatureController::class, 'options'])->name('candidature.options');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::put('me/password', [MeController::class, 'updatePassword'])->name('me.password');
        Route::put('devices/current', [MeController::class, 'updateDevice'])->name('devices.current');

        Route::get('candidature', [CandidatureController::class, 'show'])->name('candidature.show');
        Route::put('candidature/steps/{step}', [CandidatureController::class, 'updateStep'])->name('candidature.steps.update');
        Route::post('candidature/submit', [CandidatureController::class, 'submit'])
            ->middleware('permission:'.Permission::CandidatureApply->value)->name('candidature.submit');
    });
});
