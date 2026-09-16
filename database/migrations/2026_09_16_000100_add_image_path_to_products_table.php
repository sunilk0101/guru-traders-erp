<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Small reference photo per product — "I need a small image against every
 * line" (09-Sep call): staff pick a product on the Inquiry item table and
 * want a thumbnail next to it (and mirrored in the BOM trims panel) to
 * confirm it's the right one before typing 200+ lines. Stored the same way
 * DocumentFormatImage stores its reference images — public disk, path only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
