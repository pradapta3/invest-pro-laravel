<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When idx:update-financials last asked Yahoo about this emiten.
 *
 * Distinct from stock_financials.updated_at, which records when a figure last
 * *changed*. Annual statements change a few times a year at most, so
 * updateOrCreate leaves the child rows untouched on almost every run and their
 * timestamps would mark an emiten as permanently stale. It also gives the
 * emiten Yahoo has no statements for — small caps, recent listings — somewhere
 * to record that the attempt happened, which rows that were never created
 * cannot do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_refs', function (Blueprint $table) {
            $table->timestamp('financials_fetched_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stock_refs', function (Blueprint $table) {
            $table->dropColumn('financials_fetched_at');
        });
    }
};
