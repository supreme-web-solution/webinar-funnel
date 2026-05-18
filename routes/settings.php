<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SocialTrafficController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/social-traffic', [SocialTrafficController::class, 'edit'])->name('settings.social-traffic.edit');
    Route::delete('settings/social-traffic/{socialAccount}', [SocialTrafficController::class, 'disconnect'])->name('settings.social-traffic.disconnect');

});

// Zernio OAuth (auth only — callback must match browser session after external OAuth).
Route::middleware(['auth'])->group(function () {
    Route::get('settings/social-traffic/reddit/redirect', [SocialTrafficController::class, 'redditRedirect'])->name('settings.social-traffic.reddit.redirect');
    Route::get('settings/social-traffic/youtube/redirect', [SocialTrafficController::class, 'youtubeRedirect'])->name('settings.social-traffic.youtube.redirect');
    Route::get('settings/social-traffic/x/redirect', [SocialTrafficController::class, 'xRedirect'])->name('settings.social-traffic.x.redirect');

    Route::get('settings/social-traffic/zernio/callback', [SocialTrafficController::class, 'zernioCallback'])
        ->name('settings.social-traffic.zernio.callback');
    Route::get('settings/social-traffic/reddit/callback', [SocialTrafficController::class, 'zernioCallback']);
    Route::get('settings/social-traffic/youtube/callback', [SocialTrafficController::class, 'zernioCallback']);
    Route::get('settings/social-traffic/x/callback', [SocialTrafficController::class, 'zernioCallback']);
});
