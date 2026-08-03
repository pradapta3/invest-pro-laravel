<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticker');
            $table->enum('direction', ['above', 'below']);
            $table->decimal('target_price', 15, 2);
            // Null = still armed. One-shot: set once idx:check-price-alerts
            // sees the target crossed, never re-checked again.
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'triggered_at']);
            $table->index('ticker');
        });

        Schema::table('user_portfolios', function (Blueprint $table) {
            // target_price/stop_loss already existed but were never
            // populated by any code path — PortfolioService::buy() now
            // captures the trading plan at purchase time. These two track
            // whether idx:check-price-alerts has already notified the user
            // for the current position, so it doesn't re-send every run;
            // both reset to null whenever the position changes (see buy()).
            $table->timestamp('sl_alerted_at')->nullable()->after('stop_loss');
            $table->timestamp('tp_alerted_at')->nullable()->after('sl_alerted_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_portfolios', function (Blueprint $table) {
            $table->dropColumn(['sl_alerted_at', 'tp_alerted_at']);
        });

        Schema::dropIfExists('user_price_alerts');
    }
};
