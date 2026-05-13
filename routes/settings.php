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

    Route::get('settings/social-traffic/reddit/redirect', [SocialTrafficController::class, 'redditRedirect'])->name('settings.social-traffic.reddit.redirect');
    Route::get('settings/social-traffic/youtube/redirect', [SocialTrafficController::class, 'youtubeRedirect'])->name('settings.social-traffic.youtube.redirect');
    Route::get('settings/social-traffic/x/redirect', [SocialTrafficController::class, 'xRedirect'])->name('settings.social-traffic.x.redirect');
});

// OAuth callbacks must be accessible without the verified middleware
// (user may not yet have a verified e-mail).
Route::middleware(['auth'])->group(function () {
    Route::get('settings/social-traffic/reddit/callback', [SocialTrafficController::class, 'redditCallback'])->name('settings.social-traffic.reddit.callback');
    Route::get('settings/social-traffic/youtube/callback', [SocialTrafficController::class, 'youtubeCallback'])->name('settings.social-traffic.youtube.callback');
    Route::get('settings/social-traffic/x/callback', [SocialTrafficController::class, 'xCallback'])->name('settings.social-traffic.x.callback');
});
