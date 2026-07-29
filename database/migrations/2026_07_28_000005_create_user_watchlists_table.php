<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user watchlist, replacing stock_refs.is_watchlist — a single
     * boolean column shared by the whole application, which only made
     * sense back when the app was single-user. See the migration that
     * drops that column.
     */
    public function up(): void
    {
        Schema::create('user_watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticker', 10);
            $table->foreign('ticker')->references('ticker')->on('stock_refs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'ticker']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_watchlists');
    }
};
