<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('key', 80)->nullable()->unique();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('component_name', 200);
            $table->string('size', 60)->nullable();
            $table->decimal('qty', 12, 4)->default(1);
            $table->decimal('rate', 12, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('image_path', 255)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('bom_id')->nullable()->after('gst_rate_id')
                ->constrained('boms')->nullOnDelete();
        });

        Schema::table('inquiry_item_bom_lines', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable()->after('remarks');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable()->after('remarks');
        });

        $templates = config('inquiry_bom.templates', []);
        $now = now();

        foreach ($templates as $key => $template) {
            $bomId = DB::table('boms')->insertGetId([
                'name'       => $template['name'] ?? (string) $key,
                'key'        => (string) $key,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (array_values($template['lines'] ?? []) as $index => $line) {
                DB::table('bom_lines')->insert([
                    'bom_id'         => $bomId,
                    'sort_order'     => $index,
                    'component_name' => $line['component_name'] ?? '',
                    'size'           => $line['size'] ?? null,
                    'qty'            => $line['qty'] ?? 1,
                    'rate'           => $line['rate'] ?? null,
                    'unit'           => $line['unit'] ?? null,
                    'remarks'        => $line['remarks'] ?? null,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('inquiry_item_bom_lines', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bom_id');
        });

        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('boms');
    }
};
