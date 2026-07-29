<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_refs', function (Blueprint $table) {
            $table->dropIndex(['is_watchlist']);
            $table->dropColumn('is_watchlist');
        });
    }

    public function down(): void
    {
        Schema::table('stock_refs', function (Blueprint $table) {
            $table->boolean('is_watchlist')->default(false);
            $table->index('is_watchlist');
        });
    }
};
