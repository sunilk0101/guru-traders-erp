<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_format_images', function (Blueprint $table) {
            // Printed under the thumbnail on PDF / Excel — packing notes, label
            // instructions, marking call-outs. Optional per image.
            $table->string('caption', 500)->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('document_format_images', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
