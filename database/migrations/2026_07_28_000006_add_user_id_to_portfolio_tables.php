<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scopes the wallet/positions/trade-history tables to a user — the
     * app was single-user (one wallet, id=1) before this. All three
     * tables are still empty of real trading data at this point, so no
     * backfill/reassignment step is needed.
     */
    public function up(): void
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->unique('user_id');
        });

        // user_portfolios.ticker has a foreign key to stock_refs.ticker,
        // and MySQL always needs *some* index with ticker as its leftmost
        // column to back that constraint. Add a plain one before dropping
        // the unique index that currently serves that purpose, or the
        // DROP fails with "needed in a foreign key constraint" — a
        // composite (user_id, ticker) index doesn't count, ticker isn't
        // its leftmost column.
        Schema::table('user_portfolios', function (Blueprint $table) {
            $table->index('ticker', 'user_portfolios_ticker_fk_index');
        });
        Schema::table('user_portfolios', function (Blueprint $table) {
            $table->dropUnique(['ticker']);
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'ticker']);
        });

        Schema::table('user_histories', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('user_histories', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('user_portfolios', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'ticker']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropIndex('user_portfolios_ticker_fk_index');
            $table->unique('ticker');
        });

        Schema::table('user_wallets', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
