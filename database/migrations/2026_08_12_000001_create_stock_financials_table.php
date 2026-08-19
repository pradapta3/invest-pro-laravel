<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annual financial statements, one row per emiten per fiscal year.
 *
 * Every figure is nullable, unlike the rest of this schema. stock_prices and
 * stock_refs default their numeric columns to 0, which is what made "never
 * fetched" indistinguishable from a real zero and had the AI score paying out
 * for data that did not exist. Here the distinction matters even more — a
 * company genuinely can have no debt, and reporting standards differ on which
 * lines are broken out at all — so absent stays NULL and the views render it
 * as "-" rather than as zero rupiah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_financials', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20);
            $table->smallInteger('fiscal_year');
            $table->date('period_end')->nullable();

            // IDR, unscaled. Indonesian large caps report revenue in the tens
            // of trillions (1e13-1e14), so decimal(24,2) leaves headroom that
            // a bigint in cents would not.
            $table->decimal('revenue', 24, 2)->nullable();
            $table->decimal('gross_profit', 24, 2)->nullable();
            $table->decimal('operating_income', 24, 2)->nullable();
            $table->decimal('net_income', 24, 2)->nullable();

            $table->decimal('total_assets', 24, 2)->nullable();
            $table->decimal('total_liabilities', 24, 2)->nullable();
            $table->decimal('total_equity', 24, 2)->nullable();
            $table->decimal('total_debt', 24, 2)->nullable();
            $table->decimal('cash_and_equivalents', 24, 2)->nullable();

            $table->decimal('operating_cash_flow', 24, 2)->nullable();
            $table->decimal('investing_cash_flow', 24, 2)->nullable();
            $table->decimal('financing_cash_flow', 24, 2)->nullable();
            $table->decimal('capital_expenditure', 24, 2)->nullable();

            $table->decimal('eps', 16, 4)->nullable();

            $table->timestamps();

            // One row per year per ticker; the fetch command upserts on this.
            $table->unique(['ticker', 'fiscal_year']);
            // The detail page asks for one ticker's last five years, newest
            // first, and nothing else.
            $table->index(['ticker', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_financials');
    }
};
