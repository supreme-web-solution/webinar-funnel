<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PublicFunnelController;
use App\Http\Controllers\TemplateController;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');

    Route::prefix('funnels')->group(function () {
        Route::get('/', [FunnelController::class, 'index'])->name('funnels.index');
        Route::get('create', [FunnelController::class, 'create'])->name('funnels.create');
        Route::post('/', [FunnelController::class, 'store'])->name('funnels.store');
        Route::get('{funnel}/edit', [FunnelController::class, 'edit'])->name('funnels.edit');
        Route::patch('{funnel}/pages', [FunnelController::class, 'updatePage'])->name('funnels.pages.update');
        Route::patch('{funnel}/settings', [FunnelController::class, 'updateSettings'])->name('funnels.settings.update');
        Route::post('{funnel}/publish', [FunnelController::class, 'publish'])->name('funnels.publish');
        Route::get('{funnel}/chat', [ChatController::class, 'manage'])->name('funnels.chat.manage');
        Route::get('{funnel}/chat/conversations', [ChatController::class, 'ownerConversations'])->name('funnels.chat.conversations');
        Route::delete('{funnel}/chat/conversations', [ChatController::class, 'deleteConversation'])->name('funnels.chat.conversations.delete');
        Route::get('{funnel}/chat/messages', [ChatController::class, 'ownerMessages'])->name('funnels.chat.messages');
        Route::post('{funnel}/chat/messages', [ChatController::class, 'ownerSend'])->name('funnels.chat.send');
    });

    Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('integrations', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::post('integrations/{integration}/test', [IntegrationController::class, 'test'])->name('integrations.test');
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
});

require __DIR__.'/settings.php';

Route::post('/{username}/{slug}/optin', [LeadController::class, 'capture'])
    ->where('username', '[A-Za-z0-9_-]+')
    ->where('slug', '[A-Za-z0-9-]+')
    ->middleware('throttle:public-optin')
    ->name('public.optin.submit');

Route::get('/{username}/{slug}/webinar', [PublicFunnelController::class, 'webinar'])
    ->where('username', '^(?!dashboard$|templates$|funnels$|integrations$|settings$)[A-Za-z0-9_-]+')
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('public.webinar');

Route::get('/{username}/{slug}/webinar/chat/messages', [ChatController::class, 'publicMessages'])
    ->where('username', '^(?!dashboard$|templates$|funnels$|integrations$|settings$)[A-Za-z0-9_-]+')
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('public.chat.messages');

Route::post('/{username}/{slug}/webinar/chat/messages', [ChatController::class, 'publicSend'])
    ->where('username', '^(?!dashboard$|templates$|funnels$|integrations$|settings$)[A-Za-z0-9_-]+')
    ->where('slug', '[A-Za-z0-9-]+')
    ->middleware('throttle:30,1')
    ->name('public.chat.send');

Route::get('/{username}/{slug}', [PublicFunnelController::class, 'optin'])
    ->where('username', '^(?!dashboard$|templates$|funnels$|integrations$|settings$)[A-Za-z0-9_-]+')
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('public.optin');
