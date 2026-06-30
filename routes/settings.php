<?php

use App\Http\Controllers\Settings\AdAccountSettingsController;
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

    if (config('appearance.dark_mode_enabled')) {
        Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
    }

    Route::get('settings/social-traffic', [SocialTrafficController::class, 'edit'])->name('settings.social-traffic.edit');
    Route::delete('settings/social-traffic/{socialAccount}', [SocialTrafficController::class, 'disconnect'])->name('settings.social-traffic.disconnect');

    Route::middleware('paid-ads')->group(function () {
        Route::get('settings/ad-accounts', [AdAccountSettingsController::class, 'edit'])->name('settings.ad-accounts.edit');
        Route::patch('settings/ad-accounts', [AdAccountSettingsController::class, 'update'])->name('settings.ad-accounts.update');
    });

});

// Zernio OAuth (auth only — callback must match browser session after external OAuth).
Route::middleware(['auth'])->group(function () {
    $connectPlatforms = ['reddit', 'youtube', 'x', 'facebook', 'instagram', 'tiktok', 'linkedin', 'pinterest'];

    foreach ($connectPlatforms as $slug) {
        Route::get("settings/social-traffic/{$slug}/redirect", [SocialTrafficController::class, 'platformRedirect'])
            ->defaults('platform', $slug)
            ->name("settings.social-traffic.{$slug}.redirect");
        Route::get("settings/social-traffic/{$slug}/callback", [SocialTrafficController::class, 'zernioCallback']);
    }

    Route::get('settings/social-traffic/zernio/callback', [SocialTrafficController::class, 'zernioCallback'])
        ->name('settings.social-traffic.zernio.callback');

    Route::post('settings/social-traffic/zernio/link-profile', [SocialTrafficController::class, 'linkZernioProfile'])
        ->name('settings.social-traffic.zernio.link-profile');
});
