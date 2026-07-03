<?php

use App\Http\Controllers\JVZooWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\FunnelAiSourceController;
use App\Http\Controllers\IntegrationCoachingController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PublicFunnelController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\MentionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\FunnelTrafficController;
use App\Http\Controllers\FunnelPromotionCalendarController;
use App\Http\Controllers\FunnelPromotionController;
use App\Http\Controllers\FunnelPromotionTopicController;
use App\Http\Controllers\FunnelAdCampaignController;
use App\Http\Controllers\PromotionCalendarController;
use Illuminate\Support\Facades\Auth;

Route::post('/ipn/jvzoo', [JVZooWebhookController::class, 'handle'])->name('ipn.jvzoo');

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

$reservedPublicPrefix = '^(?!(tutorial|dashboard|templates|funnels|integrations|settings|users|mentions|login|register|password|verification|confirm-password|logout|sanctum|api|storage|up|leads)$)[A-Za-z0-9_-]+';

// Route::inertia('/', 'Welcome', [
//     'canRegister' => Features::enabled(Features::registration()),
// ])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('tutorial', TutorialController::class)->name('tutorial');
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

        Route::prefix('{funnel}/traffic')->name('funnels.traffic.')->group(function () {
            Route::post('keywords', [FunnelTrafficController::class, 'storeKeyword'])->name('keywords.store');
            Route::patch('keywords/{keyword}', [FunnelTrafficController::class, 'updateKeyword'])->name('keywords.update');
            Route::delete('keywords/{keyword}', [FunnelTrafficController::class, 'destroyKeyword'])->name('keywords.destroy');
            Route::post('keywords/{keyword}/fetch', [FunnelTrafficController::class, 'fetchNow'])->name('keywords.fetch');
            Route::post('mentions/{mention}/draft-reply', [FunnelTrafficController::class, 'draftMentionReply'])->name('mentions.draft-reply');
        });

        Route::prefix('{funnel}/promotion')->name('funnels.promotion.')->group(function () {
            Route::get('posts', [FunnelPromotionController::class, 'index'])->name('posts.index');
            Route::post('posts', [FunnelPromotionController::class, 'store'])->name('posts.store');
            Route::post('posts/bulk', [FunnelPromotionController::class, 'bulk'])->name('posts.bulk');
            Route::get('posts/{post}', [FunnelPromotionController::class, 'show'])->name('posts.show');
            Route::patch('posts/{post}', [FunnelPromotionController::class, 'update'])->name('posts.update');
            Route::delete('posts/{post}', [FunnelPromotionController::class, 'destroy'])->name('posts.destroy');
            Route::post('posts/{post}/generate-assets', [FunnelPromotionController::class, 'generateAssets'])->name('posts.generate-assets');
            Route::patch('posts/{post}/schedule', [FunnelPromotionController::class, 'schedule'])->name('posts.schedule');
            Route::post('posts/{post}/publish', [FunnelPromotionController::class, 'publish'])->name('posts.publish');
            Route::post('posts/{post}/duplicate', [FunnelPromotionController::class, 'duplicate'])->name('posts.duplicate');

            Route::get('calendar', [FunnelPromotionCalendarController::class, 'index'])->name('calendar.index');
            Route::patch('calendar/posts/{post}/move', [FunnelPromotionCalendarController::class, 'move'])->name('calendar.move');

            Route::get('topics', [FunnelPromotionTopicController::class, 'index'])->name('topics.index');
            Route::post('topics/generate', [FunnelPromotionTopicController::class, 'generate'])->name('topics.generate');
            Route::post('scripts/generate', [FunnelPromotionController::class, 'generateScript'])->name('scripts.generate');
        });

        Route::prefix('{funnel}/ads')->name('funnels.ads.')->middleware('paid-ads')->group(function () {
            Route::get('/', [FunnelAdCampaignController::class, 'index'])->name('index');
            Route::post('/', [FunnelAdCampaignController::class, 'store'])->name('store');
            Route::patch('{campaign}', [FunnelAdCampaignController::class, 'update'])->name('update');
            Route::post('{campaign}/duplicate', [FunnelAdCampaignController::class, 'duplicate'])->name('duplicate');
            Route::delete('{campaign}', [FunnelAdCampaignController::class, 'destroy'])->name('destroy');

            Route::post('{campaign}/research', [FunnelAdCampaignController::class, 'research'])->name('research');
            Route::post('{campaign}/creatives/generate', [FunnelAdCampaignController::class, 'generateCreatives'])->name('creatives.generate');
            Route::post('{campaign}/creatives', [FunnelAdCampaignController::class, 'storeCreative'])->name('creatives.store');
            Route::patch('{campaign}/creatives/{creative}', [FunnelAdCampaignController::class, 'updateCreative'])->name('creatives.update');
            Route::delete('{campaign}/creatives/{creative}', [FunnelAdCampaignController::class, 'destroyCreative'])->name('creatives.destroy');
            Route::post('{campaign}/creatives/{creative}/image', [FunnelAdCampaignController::class, 'generateImage'])->name('creatives.image');
            Route::post('{campaign}/creatives/{creative}/toggle', [FunnelAdCampaignController::class, 'toggleCreativeStatus'])->name('creatives.toggle');

            Route::post('{campaign}/launch', [FunnelAdCampaignController::class, 'launch'])->name('launch');
            Route::post('{campaign}/sync', [FunnelAdCampaignController::class, 'syncPerformance'])->name('sync');
        });

        Route::prefix('{funnel}/ai-sources')->name('funnels.ai.sources.')->group(function () {
            Route::get('/', [FunnelAiSourceController::class, 'index'])->name('index');
            Route::post('/url', [FunnelAiSourceController::class, 'storeUrl'])->name('store-url');
            Route::post('/transcript', [FunnelAiSourceController::class, 'storeTranscript'])->name('store-transcript');
            Route::post('/file', [FunnelAiSourceController::class, 'storeFile'])->name('store-file');
            Route::post('/bulk-delete', [FunnelAiSourceController::class, 'bulkDelete'])->name('bulk-delete');
            Route::get('/{source}/chunks', [FunnelAiSourceController::class, 'chunks'])->name('chunks');
            Route::delete('/{source}', [FunnelAiSourceController::class, 'destroy'])->name('delete');
        });
    });

    Route::get('promotion/calendar', [PromotionCalendarController::class, 'index'])->name('promotion.calendar.index');
    Route::patch('promotion/calendar/posts/{post}/move', [PromotionCalendarController::class, 'move'])->name('promotion.calendar.move');

    Route::prefix('mentions')->name('mentions.')->group(function () {
        Route::get('/', [MentionController::class, 'index'])->name('index');
        Route::post('keywords', [MentionController::class, 'storeKeyword'])->name('keywords.store');
        Route::patch('keywords/{keyword}', [MentionController::class, 'updateKeyword'])->name('keywords.update');
        Route::delete('keywords/{keyword}', [MentionController::class, 'destroyKeyword'])->name('keywords.destroy');
        Route::post('keywords/{keyword}/fetch', [MentionController::class, 'fetchNow'])->name('keywords.fetch');
    });

    Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('integrations/coaching', IntegrationCoachingController::class)->name('integrations.coaching');
    Route::post('integrations', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::post('integrations/{integration}/test', [IntegrationController::class, 'test'])->name('integrations.test');
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');

    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
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

Route::post('/{username}/{slug}/webinar/stats', [PublicFunnelController::class, 'trackVideoStats'])
    ->where('username', $reservedPublicPrefix)
    ->where('slug', '[A-Za-z0-9-]+')
    ->middleware('throttle:120,1')
    ->name('public.webinar.stats');

Route::get('/{username}/{slug}', [PublicFunnelController::class, 'optin'])
    ->where('username', $reservedPublicPrefix)
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('public.optin');
