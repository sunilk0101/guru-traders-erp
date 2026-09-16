<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds the Trim / Accessory catalog from the component names already
     * used across the BOM cost templates (config/inquiry_bom.php), so the
     * master isn't empty on first load. Staff attach a reference photo to
     * each from Masters -> Trim / Accessories. insertOrIgnore keeps this
     * safe to re-run.
     */
    public function up(): void
    {
        $names = [
            'Fabric', 'Labour', 'Embroidery', 'Print', 'Transport', 'Pattern',
            'Main Label', 'Size Label', 'Pocket Label', 'Washcare1', 'Washcare2',
            'Style No labels', 'Belt Lining', 'Zip', 'Buttons',
        ];

        $now = now();

        DB::table('trim_accessories')->insertOrIgnore(
            collect($names)->unique()->map(fn (string $name) => [
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        // Data-only seed; nothing to reverse.
    }
};
