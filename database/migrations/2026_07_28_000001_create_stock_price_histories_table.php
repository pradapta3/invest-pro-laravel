<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-year daily OHLCV per ticker, used only by the backtesting
     * engine (BacktestEngine). Separate from stock_prices — that table is
     * a *current snapshot* (today's OHLC + indicators), while a backtest
     * needs to reconstruct indicator values at every historical date, so
     * it needs the actual daily bar series, not just the last ~60 closes
     * stock_prices.history_json keeps for sparklines.
     */
    public function up(): void
    {
        Schema::create('stock_price_histories', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 10);
            $table->foreign('ticker')->references('ticker')->on('stock_refs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('open', 15, 2)->default(0);
            $table->decimal('high', 15, 2)->default(0);
            $table->decimal('low', 15, 2)->default(0);
            $table->decimal('close', 15, 2)->default(0);
            $table->bigInteger('volume')->default(0);
            $table->timestamps();

            $table->unique(['ticker', 'date']);
            $table->index(['ticker', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_price_histories');
    }
};
