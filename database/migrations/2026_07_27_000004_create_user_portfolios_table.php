<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Open positions. One row per held ticker (unique constraint), holding
     * the weighted-average buy price and lot count maintained by
     * PortfolioService.
     */
    public function up(): void
    {
        Schema::create('user_portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 10);
            $table->foreign('ticker')->references('ticker')->on('stock_refs')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('avg_price', 15, 2)->default(0);
            $table->integer('lots')->default(0);
            $table->decimal('target_price', 15, 2)->default(0);
            $table->decimal('stop_loss', 15, 2)->default(0);
            $table->timestamps();

            $table->unique('ticker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_portfolios');
    }
};
