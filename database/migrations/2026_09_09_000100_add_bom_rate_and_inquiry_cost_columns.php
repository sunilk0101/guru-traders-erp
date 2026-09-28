<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiry_item_bom_lines', function (Blueprint $table) {
            $table->string('size', 60)->nullable()->after('component_name');
            $table->decimal('rate', 12, 4)->nullable()->after('qty');
        });

        Schema::table('inquiry_items', function (Blueprint $table) {
            $table->decimal('bom_cost', 12, 2)->nullable()->after('cost_price');
            $table->decimal('fob_unit', 12, 4)->nullable()->after('bom_cost');
        });
    }

    public function down(): void
    {
        Schema::table('inquiry_item_bom_lines', function (Blueprint $table) {
            $table->dropColumn(['size', 'rate']);
        });

        Schema::table('inquiry_items', function (Blueprint $table) {
            $table->dropColumn(['bom_cost', 'fob_unit']);
        });
    }
};
