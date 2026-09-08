<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category and Order Format can change every few inquiry item lines.
 * Keep inquiry header fields as defaults / summary; real values live on items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiry_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('inquiry_id')
                ->constrained('categories')->nullOnDelete();
            $table->foreignId('document_format_id')->nullable()->after('category_id')
                ->constrained('document_formats')->nullOnDelete();
        });

        // Backfill from the inquiry header so existing rows keep working.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(
                'UPDATE inquiry_items
                 SET category_id = (
                     SELECT inquiries.category_id FROM inquiries WHERE inquiries.id = inquiry_items.inquiry_id
                 ),
                 document_format_id = (
                     SELECT inquiries.document_format_id FROM inquiries WHERE inquiries.id = inquiry_items.inquiry_id
                 )
                 WHERE category_id IS NULL OR document_format_id IS NULL'
            );
        } else {
            DB::table('inquiry_items')
                ->join('inquiries', 'inquiries.id', '=', 'inquiry_items.inquiry_id')
                ->whereNull('inquiry_items.category_id')
                ->update([
                    'inquiry_items.category_id' => DB::raw('inquiries.category_id'),
                ]);

            DB::table('inquiry_items')
                ->join('inquiries', 'inquiries.id', '=', 'inquiry_items.inquiry_id')
                ->whereNull('inquiry_items.document_format_id')
                ->update([
                    'inquiry_items.document_format_id' => DB::raw('inquiries.document_format_id'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('inquiry_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_format_id');
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
