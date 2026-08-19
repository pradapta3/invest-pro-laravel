<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the daily-change baseline a provenance.
 *
 * prev_close has been a bare number with nothing to say which session it came
 * from, and that is the root of a bug this app has now had three times: a
 * baseline left over from an earlier session is indistinguishable from a
 * correct one, so "today's change" quietly becomes a two- or three-day move
 * and an emiten that gapped down and recovered a little shows green.
 *
 * Nullable rather than defaulted: rows written before this migration have a
 * baseline whose session genuinely is not known, and inventing a date for
 * them would defeat the point. StockPrice treats the null as "unverified but
 * usable" so existing installs are not blanked; the scheduled refreshes fill
 * it in on their next run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_prices', function (Blueprint $table) {
            $table->date('prev_close_date')->nullable()->after('prev_close');
        });
    }

    public function down(): void
    {
        Schema::table('stock_prices', function (Blueprint $table) {
            $table->dropColumn('prev_close_date');
        });
    }
};
