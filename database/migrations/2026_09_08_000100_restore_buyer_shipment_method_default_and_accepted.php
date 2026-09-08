<?php

use App\Models\ShipmentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restore Buyer col S as Default + Accepted shipment methods (same shape as
 * Inco Terms / Currencies). Reverses the free-text simplification in
 * 2026_08_05_000100 — the client wants two modes again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->foreignId('shipment_method_id')->nullable()->after('incoterm_id')
                ->constrained('shipment_methods')->nullOnDelete();
        });

        Schema::create('buyer_shipment_method', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_method_id')->constrained('shipment_methods')->restrictOnDelete();

            $table->unique(['buyer_id', 'shipment_method_id']);
        });

        if (Schema::hasColumn('buyers', 'shipment_method')) {
            $rows = DB::table('buyers')
                ->whereNotNull('shipment_method')
                ->where('shipment_method', '!=', '')
                ->get(['id', 'shipment_method']);

            foreach ($rows as $row) {
                $name = trim((string) $row->shipment_method);
                if ($name === '') {
                    continue;
                }

                $method = ShipmentMethod::query()->firstOrCreate(
                    ['name' => $name],
                    ['status' => 'active']
                );

                DB::table('buyers')->where('id', $row->id)->update([
                    'shipment_method_id' => $method->id,
                ]);

                DB::table('buyer_shipment_method')->insertOrIgnore([
                    'buyer_id'           => $row->id,
                    'shipment_method_id' => $method->id,
                ]);
            }

            Schema::table('buyers', function (Blueprint $table) {
                $table->dropColumn('shipment_method');
            });
        }
    }

    public function down(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->string('shipment_method', 120)->nullable()->after('incoterm_id');
        });

        $rows = DB::table('buyers')
            ->whereNotNull('shipment_method_id')
            ->get(['id', 'shipment_method_id']);

        foreach ($rows as $row) {
            $name = DB::table('shipment_methods')->where('id', $row->shipment_method_id)->value('name');
            DB::table('buyers')->where('id', $row->id)->update([
                'shipment_method' => $name,
            ]);
        }

        Schema::dropIfExists('buyer_shipment_method');

        Schema::table('buyers', function (Blueprint $table) {
            $table->dropForeign(['shipment_method_id']);
            $table->dropColumn('shipment_method_id');
        });
    }
};
