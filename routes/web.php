<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Api\DataUpdateController;
use App\Http\Controllers\Api\PortfolioChartController;
use App\Http\Controllers\Api\StockAnalysisController;
use App\Http\Controllers\Api\TelegramBroadcastController;
use App\Http\Controllers\BacktestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SeasonalityController;
use App\Http\Controllers\SimilarityController;
use App\Http\Controllers\StockDetailController;
use App\Http\Controllers\SubscriptionController;
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

    Route::get('/scanner/quant', [ScannerController::class, 'quant'])->name('scanner.quant');
    Route::get('/scanner/titan', [TitanScanController::class, 'index'])->name('scanner.titan');

    Route::get('/heatmap', [HeatmapController::class, 'index'])->name('heatmap.index');
    Route::get('/seasonality', [SeasonalityController::class, 'show'])->name('seasonality.show');
    Route::get('/similarity', [SimilarityController::class, 'show'])->name('similarity.show');
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/tools', [ToolsController::class, 'index'])->name('tools.index');
    Route::get('/backtest', [BacktestController::class, 'index'])->name('backtest.index');

    // Moved here from routes/api.php: these are same-origin fetch() calls
    // from the Blade views that need the web session (to know *which*
    // user's portfolio/AI-analyze/broadcast request this is) — the `api`
    // middleware group doesn't start a session at all, so session-based
    // auth silently couldn't have worked there once the app went
    // multi-user. Route *names* are unchanged so no Blade/JS needed updating.
    Route::prefix('api')->group(function () {
        Route::post('/stocks/analyze', StockAnalysisController::class)->name('api.stocks.analyze');
        Route::get('/portfolio/chart', PortfolioChartController::class)->name('api.portfolio.chart');
        Route::post('/telegram/broadcast-signal', [TelegramBroadcastController::class, 'signal'])
            ->name('api.telegram.broadcast-signal');
        Route::post('/telegram/broadcast-titan', [TelegramBroadcastController::class, 'titanSignal'])
            ->name('api.telegram.broadcast-titan');
        Route::post('/telegram/broadcast-digest', [TelegramBroadcastController::class, 'digest'])
            ->name('api.telegram.broadcast-digest');
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
    // triggering them is an operator action, not a customer one.
    Route::prefix('api')->group(function () {
        Route::get('/data-updates/jobs', [DataUpdateController::class, 'jobs'])->name('api.data-updates.jobs');
        Route::post('/data-updates/{key}', DataUpdateController::class)->name('api.data-updates.run');
    });
});
