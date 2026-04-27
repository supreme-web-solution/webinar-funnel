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
use App\Http\Controllers\MentionController;
use App\Http\Controllers\UserManagementController;

$reservedPublicPrefix = '^(?!(dashboard|templates|funnels|integrations|settings|users|mentions|login|register|password|verification|confirm-password|logout|sanctum|api|storage|up|leads)$)[A-Za-z0-9_-]+';

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
        Route::post('{funnel}/unpublish', [FunnelController::class, 'unpublish'])->name('funnels.unpublish');
        Route::post('{funnel}/archive', [FunnelController::class, 'archive'])->name('funnels.archive');
        Route::delete('{funnel}', [FunnelController::class, 'destroy'])->name('funnels.destroy');
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

    // Brand Mentions
    Route::prefix('mentions')->name('mentions.')->group(function () {
        Route::get('/', [MentionController::class, 'index'])->name('index');
        Route::post('keywords', [MentionController::class, 'storeKeyword'])->name('keywords.store');
        Route::patch('keywords/{keyword}', [MentionController::class, 'updateKeyword'])->name('keywords.update');
        Route::delete('keywords/{keyword}', [MentionController::class, 'destroyKeyword'])->name('keywords.destroy');
        Route::post('keywords/{keyword}/fetch', [MentionController::class, 'fetchNow'])->name('keywords.fetch');
    });
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/settings.php';

Route::post('/{username}/{slug}/optin', [LeadController::class, 'capture'])
    ->where('username', $reservedPublicPrefix)
    ->where('slug', '[A-Za-z0-9-]+')
    ->middleware('throttle:public-optin')
    ->name('public.optin.submit');

Route::get('/{username}/{slug}/webinar', [PublicFunnelController::class, 'webinar'])
    ->where('username', $reservedPublicPrefix)
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('public.webinar');

Route::get('/{username}/{slug}/webinar/chat/messages', [ChatController::class, 'publicMessages'])
    ->where('username', $reservedPublicPrefix)
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('public.chat.messages');

Route::post('/{username}/{slug}/webinar/chat/messages', [ChatController::class, 'publicSend'])
    ->where('username', $reservedPublicPrefix)
    ->where('slug', '[A-Za-z0-9-]+')
    ->middleware('throttle:30,1')
    ->name('public.chat.send');

Route::get('/{username}/{slug}', [PublicFunnelController::class, 'optin'])
    ->where('username', $reservedPublicPrefix)
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('public.optin');
