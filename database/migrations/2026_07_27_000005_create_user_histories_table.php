<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable ledger of executed BUY/SELL trades, used for the
     * transaction history table and the win-rate statistic on the
     * portfolio dashboard. Ticker is intentionally NOT a foreign key here
     * (unlike user_portfolios) so history survives a ticker being delisted
     * and removed from stock_refs.
     */
    public function up(): void
    {
        Schema::create('user_histories', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 10);
            $table->enum('type', ['BUY', 'SELL']);
            $table->decimal('price', 15, 2);
            $table->integer('lots');
            $table->decimal('value', 20, 2);
            $table->decimal('profit_loss', 15, 2)->default(0);
            $table->timestamp('date')->useCurrent();

            $table->index(['ticker', 'date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_histories');
    }
};
