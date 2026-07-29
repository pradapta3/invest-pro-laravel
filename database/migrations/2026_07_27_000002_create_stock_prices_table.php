<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Latest OHLCV + derived technical indicators for one ticker, refreshed
     * by the UpdateMarketData / UpdateRealtimeQuotes console commands.
     *
     * prev_close, sentiment_score and last_news_title were only added at
     * runtime by the legacy scripts via ad-hoc ALTER TABLE calls on every
     * page load (see UpdateMarketData/UpdateRealtimeQuotes in the legacy
     * codebase) — they belong in the schema from day one instead.
     */
    public function up(): void
    {
        Schema::create('stock_prices', function (Blueprint $table) {
            $table->string('ticker', 10)->primary();
            $table->foreign('ticker')->references('ticker')->on('stock_refs')->cascadeOnUpdate()->cascadeOnDelete();

            $table->decimal('open_price', 15, 2)->default(0);
            $table->decimal('high_price', 15, 2)->default(0);
            $table->decimal('low_price', 15, 2)->default(0);
            $table->decimal('close_price', 15, 2)->default(0);
            $table->decimal('prev_close', 15, 2)->default(0);
            $table->bigInteger('volume')->default(0);

            $table->decimal('ma20', 15, 2)->default(0);
            $table->decimal('rsi_14', 10, 2)->default(0);
            $table->decimal('stoch_k', 10, 2)->default(0);
            $table->decimal('macd_hist', 10, 5)->default(0);
            $table->boolean('is_breakout')->default(false);

            $table->bigInteger('vol_avg_20')->default(0);
            $table->decimal('value_transaction', 20, 2)->default(0);
            $table->decimal('vwap', 15, 2)->default(0);
            $table->decimal('buying_pressure', 15, 2)->default(0);
            $table->decimal('selling_pressure', 15, 2)->default(0);

            // Trailing ~60 daily closes used for sparklines, the Prophet
            // linear-regression forecast, and Ghost Pattern correlation.
            $table->json('history_json')->nullable();

            $table->tinyInteger('sentiment_score')->default(0);
            $table->string('last_news_title')->nullable();

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('value_transaction');
            $table->index('volume');
            $table->index(['close_price', 'ma20']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_prices');
    }
};
