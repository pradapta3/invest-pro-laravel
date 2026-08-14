<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Api\DataUpdateController;
use App\Http\Controllers\Api\LiveQuoteController;
use App\Http\Controllers\Api\PortfolioChartController;
use App\Http\Controllers\Api\StockAnalysisController;
use App\Http\Controllers\Api\TelegramBroadcastController;
use App\Http\Controllers\BacktestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SeasonalityController;
use App\Http\Controllers\SimilarityController;
use App\Http\Controllers\StockDetailController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TelegramLinkController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TitanScanController;
use App\Http\Controllers\ToolsController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

// Authenticated per-request by VerifyTelegramWebhookSecret (see
// bootstrap/app.php for the matching CSRF exemption) rather than a
// session — Telegram calls this directly, there is no browser here.
Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.webhook')
    ->name('telegram.webhook');

Route::middleware('auth')->group(function () {
    Route::get('/subscription/pending', [SubscriptionController::class, 'pending'])->name('subscription.pending');
});

// The actual app — every route here needs a logged-in user with an
// active subscription (admins bypass the subscription check). See
// EnsureSubscriptionActive.
Route::middleware(['auth', 'subscription.active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/stocks/{ticker}/toggle-watchlist', [DashboardController::class, 'toggleWatchlist'])
        ->name('dashboard.toggle-watchlist');

    Route::get('/stocks/{ticker}', [StockDetailController::class, 'show'])->name('stocks.show');

    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::post('/portfolio/trade', [PortfolioController::class, 'trade'])->name('portfolio.trade');
    Route::post('/portfolio/deposit', [PortfolioController::class, 'deposit'])->name('portfolio.deposit');

    Route::get('/scanner/quant', [ScannerController::class, 'quant'])->middleware('plan:scanner')->name('scanner.quant');
    Route::get('/scanner/titan', [TitanScanController::class, 'index'])->middleware('plan:scanner')->name('scanner.titan');

    Route::get('/heatmap', [HeatmapController::class, 'index'])->middleware('plan:heatmap')->name('heatmap.index');
    Route::get('/seasonality', [SeasonalityController::class, 'show'])->middleware('plan:pattern')->name('seasonality.show');
    Route::get('/similarity', [SimilarityController::class, 'show'])->middleware('plan:pattern')->name('similarity.show');
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/tools', [ToolsController::class, 'index'])->middleware('plan:tools')->name('tools.index');
    Route::get('/backtest', [BacktestController::class, 'index'])->middleware('plan:backtest')->name('backtest.index');

    Route::get('/telegram/link', [TelegramLinkController::class, 'show'])->middleware('plan:telegram')->name('telegram.link');
    Route::post('/telegram/link', [TelegramLinkController::class, 'generate'])->name('telegram.link.generate');
    Route::delete('/telegram/link', [TelegramLinkController::class, 'destroy'])->name('telegram.link.destroy');

    Route::get('/alerts', [PriceAlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts', [PriceAlertController::class, 'store'])->name('alerts.store');
    Route::delete('/alerts/{alert}', [PriceAlertController::class, 'destroy'])->name('alerts.destroy');

    // Moved here from routes/api.php: these are same-origin fetch() calls
    // from the Blade views that need the web session (to know *which*
    // user's portfolio/AI-analyze/signal request this is) — the `api`
    // middleware group doesn't start a session at all, so session-based
    // auth silently couldn't have worked there once the app went
    // multi-user. Route *names* are unchanged so no Blade/JS needed updating.
    Route::prefix('api')->group(function () {
        // Each call hits the Gemini API (a real cost); throttled per user/IP
        // so a scripted loop can't run up the bill.
        Route::post('/stocks/analyze', StockAnalysisController::class)
            ->name('api.stocks.analyze')
            ->middleware('throttle:20,1');
        Route::get('/portfolio/chart', PortfolioChartController::class)->name('api.portfolio.chart');

        // Polled by the dashboard so the page stops being a snapshot of
        // whenever it was opened. Reads its own tables and hits nothing
        // upstream, so the throttle is generous — it exists to stop a stuck
        // tab hammering the database, not to ration anything expensive.
        Route::get('/quotes/live', LiveQuoteController::class)
            ->name('api.quotes.live')
            ->middleware('throttle:120,1');

        // Sends to the caller's own linked Telegram chat (see
        // TelegramLinkController) — a per-user action, so this belongs
        // with the rest of the subscriber routes, not admin. The shared-
        // channel digest broadcast below stays admin-only.
        Route::post('/telegram/broadcast-signal', [TelegramBroadcastController::class, 'signal'])
            ->name('api.telegram.broadcast-signal')
            ->middleware('throttle:20,1');
        Route::post('/telegram/broadcast-titan', [TelegramBroadcastController::class, 'titanSignal'])
            ->name('api.telegram.broadcast-titan')
            ->middleware('throttle:20,1');
    });
});

// Admin-only.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/subscription/activate', [UserManagementController::class, 'activateSubscription'])
        ->name('users.subscription.activate');
    Route::post('/users/{user}/subscription/cancel', [UserManagementController::class, 'cancelSubscription'])
        ->name('users.subscription.cancel');

    Route::get('/plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [SubscriptionPlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [SubscriptionPlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [SubscriptionPlanController::class, 'destroy'])->name('plans.destroy');

    // Data-refresh jobs affect the whole shared dataset (every subscriber
    // sees the same stock_prices/stock_refs), not a per-user resource, so
    // triggering them is an operator action, not a customer one. Same
    // reasoning applies to the digest broadcast below — it posts to the
    // site's own shared channel (TELEGRAM_BROADCAST_CHAT_IDS), unlike
    // broadcast-signal/-titan (see the subscriber group above) which now
    // go to the calling user's own linked chat instead.
    Route::prefix('api')->group(function () {
        Route::get('/data-updates/jobs', [DataUpdateController::class, 'jobs'])->name('api.data-updates.jobs');
        Route::post('/data-updates/{key}', DataUpdateController::class)->name('api.data-updates.run');

        Route::post('/telegram/broadcast-digest', [TelegramBroadcastController::class, 'digest'])
            ->name('api.telegram.broadcast-digest');
    });
});
