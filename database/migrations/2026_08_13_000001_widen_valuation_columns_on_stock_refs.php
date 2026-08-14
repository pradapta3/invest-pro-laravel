<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valuation ratios stored with enough precision to survive being stored.
 *
 * decimal(12,2) rounds at rest: a price-to-book of 0.004 — a real reading for
 * a heavily written-down company — became 0.00, and so did every EPS under
 * half a rupiah. Reading those back gave a metric that is exactly zero and no
 * way to tell it apart from "never fetched", which is the same conflation that
 * had the AI score paying out for missing data.
 *
 * Four decimals is enough for any ratio anyone quotes, and the integer part is
 * widened alongside because a price-to-earnings on a barely-profitable company
 * runs to thousands.
 *
 * eps joins them: it was displayed but never stored, so the only source was
 * the annual statements, and a company between filings had none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_refs', function (Blueprint $table) {
            $table->decimal('pe_ratio', 16, 4)->default(0)->change();
            $table->decimal('pb_ratio', 16, 4)->default(0)->change();
            $table->decimal('roe', 16, 4)->default(0)->change();
            $table->decimal('der', 16, 4)->default(0)->change();
            $table->decimal('div_yield', 16, 4)->default(0)->change();
            $table->decimal('eps', 16, 4)->nullable()->after('pb_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('stock_refs', function (Blueprint $table) {
            $table->decimal('pe_ratio', 12, 2)->default(0)->change();
            $table->decimal('pb_ratio', 12, 2)->default(0)->change();
            $table->decimal('roe', 10, 2)->default(0)->change();
            $table->decimal('der', 10, 2)->default(0)->change();
            $table->decimal('div_yield', 10, 2)->default(0)->change();
            $table->dropColumn('eps');
        });
    }
};
