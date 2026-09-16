<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "I need a small image against every line which will be added while
     * making the bom cost ... a separate photo per trim type" (16-Sep call).
     * One row per trim/accessory type (Main Label, Zip, Buttons, ...), each
     * with its own optional reference photo — mirrors products' image_path.
     */
    public function up(): void
    {
        Schema::create('trim_accessories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('image_path')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trim_accessories');
    }
};
