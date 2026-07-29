<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row cash wallet (id=1) backing the simulated portfolio. The
     * app is single-user/single-owner, mirroring the legacy dashboard —
     * see PortfolioService::wallet() for how the row is fetched/seeded.
     */
    public function up(): void
    {
        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->decimal('cash_balance', 20, 2)->default(100000000);
            $table->decimal('realized_pl', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallets');
    }
};
