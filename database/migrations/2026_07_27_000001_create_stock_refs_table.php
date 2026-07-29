<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference/fundamental data for one IDX-listed stock. Ticker (e.g.
     * "BBCA.JK") is the natural primary key, matching the Yahoo Finance
     * symbol format used throughout the market-data integrations.
     */
    public function up(): void
    {
        Schema::create('stock_refs', function (Blueprint $table) {
            $table->string('ticker', 10)->primary();
            $table->string('nama_perusahaan')->nullable();
            $table->decimal('pe_ratio', 12, 2)->default(0);
            $table->decimal('pb_ratio', 12, 2)->default(0);
            $table->bigInteger('market_cap')->default(0);
            $table->decimal('roe', 10, 2)->default(0);
            $table->decimal('div_yield', 10, 2)->default(0);
            $table->boolean('is_watchlist')->default(false);
            $table->decimal('der', 10, 2)->default(0);
            $table->string('sector', 50)->default('Others');
            $table->timestamps();

            $table->index('sector');
            $table->index('is_watchlist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_refs');
    }
};
