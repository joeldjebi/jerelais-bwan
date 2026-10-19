<?php

use App\Domain\Access\Enums\Permission;
use App\Http\Controllers\Api\V1\Appels\AppelController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordLoginController;
use App\Http\Controllers\Api\V1\Candidature\CandidatureController;
use App\Http\Controllers\Api\V1\Formation\FormationController;
use App\Http\Controllers\Api\V1\Legal\LegalController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Messagerie\ConversationController;
use App\Http\Controllers\Api\V1\ReferentielController;
use App\Http\Controllers\Api\V1\Relais\MediaController;
use App\Http\Controllers\Api\V1\Relais\RelaisItemController;
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
    Route::get('legal', [LegalController::class, 'index'])->name('legal.index');
    Route::get('formation', [FormationController::class, 'modules'])->name('formation.index');
    Route::get('candidature/options', [CandidatureController::class, 'options'])->name('candidature.options');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::put('me/password', [MeController::class, 'updatePassword'])->name('me.password');
        Route::get('me/badge', [MeController::class, 'badge'])->name('me.badge');
        Route::get('me/export', [MeController::class, 'export'])->middleware('throttle:5,60')->name('me.export');
        Route::delete('me', [MeController::class, 'destroy'])->name('me.destroy');
        Route::put('devices/current', [MeController::class, 'updateDevice'])->name('devices.current');

        Route::get('consents', [LegalController::class, 'consents'])->name('consents.index');
        Route::post('consents', [LegalController::class, 'accept'])->name('consents.store');

        Route::get('quiz', [FormationController::class, 'quiz'])->name('quiz.show');
        Route::post('quiz/attempts', [FormationController::class, 'attempt'])->middleware('throttle:quiz')->name('quiz.attempts.store');

        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [ConversationController::class, 'store'])->middleware('throttle:30,1')->name('conversations.store');
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'reply'])->middleware('throttle:30,1')->name('conversations.reply');

        Route::get('appels', [AppelController::class, 'index'])->name('appels.index');
        Route::get('appels/{appel}', [AppelController::class, 'show'])->name('appels.show');

        Route::get('relais', [RelaisItemController::class, 'index'])->name('relais.index');
        Route::post('relais', [RelaisItemController::class, 'store'])->middleware('throttle:60,1')->name('relais.store');
        Route::get('relais/{relais}', [RelaisItemController::class, 'show'])->name('relais.show');
        Route::put('relais/{relais}', [RelaisItemController::class, 'update'])->name('relais.update');
        Route::post('relais/{relais}/submit', [RelaisItemController::class, 'submit'])->name('relais.submit');
        Route::post('relais/{relais}/complement', [RelaisItemController::class, 'complement'])->name('relais.complement');
        Route::post('relais/{relais}/medias', [MediaController::class, 'store'])->middleware('throttle:120,1')->name('relais.medias.store');
        Route::get('medias/{media}', [MediaController::class, 'show'])->name('medias.show');
        Route::post('medias/{media}/complete', [MediaController::class, 'complete'])->name('medias.complete');

        Route::get('candidature', [CandidatureController::class, 'show'])->name('candidature.show');
        Route::put('candidature/steps/{step}', [CandidatureController::class, 'updateStep'])->name('candidature.steps.update');
        Route::post('candidature/documents', [CandidatureController::class, 'storeDocument'])
            ->middleware('throttle:30,1')->name('candidature.documents.store');
        Route::delete('candidature/documents/{type}', [CandidatureController::class, 'destroyDocument'])
            ->middleware('permission:'.Permission::CandidatureApply->value)->name('candidature.documents.destroy');
        Route::post('candidature/submit', [CandidatureController::class, 'submit'])
            ->middleware('permission:'.Permission::CandidatureApply->value)->name('candidature.submit');
    });
});
