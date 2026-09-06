<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupInvitationController;
use App\Http\Controllers\GroupMediaController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store']);

    Route::middleware('registration')->group(function () {
        Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    });

    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/nouveau-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/nouveau-mot-de-passe', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.store');
});

/*
 * Accès publics : le destinataire d'un lien n'a pas de compte. Le fichier servi
 * est le fichier d'origine, jamais une version.
 */
Route::get('/p/{token}', [PublicShareController::class, 'show'])->name('share.show');
Route::get('/p/{token}/telecharger', [PublicShareController::class, 'download'])->name('share.download');
Route::get('/p/{token}/apercu', [PublicShareController::class, 'thumbnail'])->name('share.thumbnail');

// Les liens d'invitation passent par l'inscription si besoin : pas de middleware auth.
Route::get('/g/{token}', [GroupInvitationController::class, 'join'])->name('groups.join');
Route::get('/invitations/{token}', [GroupInvitationController::class, 'show'])->name('invitations.show');

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Dépôt par morceaux — JSON.
    Route::post('/depots', [UploadController::class, 'store'])->name('uploads.store');
    Route::put('/depots/{upload:uuid}/morceaux/{index}', [UploadController::class, 'chunk'])
        ->whereNumber('index')->name('uploads.chunk');
    Route::post('/depots/{upload:uuid}/terminer', [UploadController::class, 'finish'])->name('uploads.finish');
    Route::delete('/depots/{upload:uuid}', [UploadController::class, 'destroy'])->name('uploads.destroy');

    Route::get('/fichiers/{media}/telecharger', [MediaController::class, 'download'])->name('media.download');
    Route::get('/fichiers/{media}/apercu', [MediaController::class, 'thumbnail'])->name('media.thumbnail');
    Route::put('/fichiers/{media}/tags', [MediaController::class, 'updateTags'])->name('media.tags');
    Route::delete('/fichiers/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::post('/fichiers/{media}/liens', [ShareLinkController::class, 'store'])->name('share-links.store');
    Route::delete('/liens/{shareLink}', [ShareLinkController::class, 'destroy'])->name('share-links.destroy');

    Route::post('/groupes', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groupes/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::delete('/groupes/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groupes/{group}/quitter', [GroupController::class, 'leave'])->name('groups.leave');
    Route::post('/groupes/{group}/lien', [GroupController::class, 'regenerateInviteLink'])->name('groups.invite-link.regenerate');
    Route::post('/groupes/{group}/invitations', [GroupInvitationController::class, 'store'])
        ->middleware('throttle:20,1')->name('groups.invitations.store');
    Route::post('/groupes/{group}/fichiers', [GroupMediaController::class, 'store'])->name('groups.media.store');
    Route::delete('/groupes/{group}/fichiers/{media}', [GroupMediaController::class, 'destroy'])->name('groups.media.destroy');
});
