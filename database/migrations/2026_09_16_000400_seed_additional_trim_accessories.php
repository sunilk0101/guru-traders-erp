<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the component names introduced by completing the "Standard garment
 * BOM" preset to match the full worked example in "BOM and Inquiry Format
 * final.xlsx" (BOM Format sheet). "Buttons" and "Zip" already exist from the
 * original seed (2026_09_16_000300) and are matched by name only, so their
 * size/remarks variants don't need separate catalog rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'Pocketing Fabric',
            'Pipping Fabric',
            'Elastic',
            'Ribit',
            'Velcrow',
            'Fold Tag',
            'Main Tag',
            'Tag Pin',
            'Drawchord',
            'Barcode',
            'Polybag',
            'Carton',
            'Carton Plate',
            'Shrink wrap',
        ];

        $now = now();

        DB::table('trim_accessories')->insertOrIgnore(array_map(fn (string $name) => [
            'name' => $name,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ], $names));
    }

    public function down(): void
    {
        // Intentionally left as no-op: these rows may already be referenced
        // by BOM lines saved on inquiries, so we don't remove them.
    }
};
