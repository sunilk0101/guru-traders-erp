<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L-03 (audit): the 08-Sep-2026 Excel import wrote its own provenance
 * straight into user-facing fields instead of anywhere hidden — e.g.
 * product 614's Remarks read "Group Code: GGHS | Drawback cap on:
 * Quantity...", its Comments read "Source: Final 08092026.xlsx row 605".
 * That's exactly the kind of internal bookkeeping a hidden column is for.
 *
 * This migration only adds the column — it does NOT touch existing
 * Remarks/Comments data. Moving today's import artifacts out of those
 * fields into here is a separate, deliberate data-cleanup pass (needs a
 * human to confirm which lines are safe to rewrite for all ~589 imported
 * products), not something to do blind inside a schema migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Free-form: which source file/row it came from, the original
            // group-code/drawback-cap notes, anything else the importer
            // wants to keep without it leaking into a field a buyer-facing
            // document might print. Never rendered in the UI.
            $table->json('import_meta')->nullable()->after('comments');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('import_meta');
        });
    }
};
