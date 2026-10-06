<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * L-03: the 08-Sep Excel import wrote provenance into Remarks/Comments.
 * Move those artefacts into import_meta and clear the user-facing fields.
 */
class ArchiveProductImportMeta extends Command
{
    protected $signature = 'products:archive-import-meta {--dry-run : Report without writing}';

    protected $description = 'Move Excel-import provenance out of product remarks/comments into import_meta';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $updated = 0;

        Product::query()->withTrashed()->orderBy('id')->chunkById(100, function ($products) use ($dry, &$updated) {
            foreach ($products as $product) {
                $meta = is_array($product->import_meta) ? $product->import_meta : [];
                $remarks = (string) $product->remarks;
                $comments = (string) $product->comments;
                $changed = false;

                if ($comments !== '' && (str_starts_with($comments, 'Source:') || str_contains($comments, '.xlsx row'))) {
                    $meta['source_comment'] = $comments;
                    $product->comments = null;
                    $changed = true;
                }

                if ($remarks !== '' && str_starts_with($remarks, 'Group Code:')) {
                    $meta['group_code_remark'] = $remarks;
                    $product->remarks = null;
                    $changed = true;
                }

                if (! $changed) {
                    continue;
                }

                $updated++;

                if ($dry) {
                    continue;
                }

                $product->import_meta = $meta;
                $product->save();
            }
        });

        $this->info(($dry ? 'Would update ' : 'Updated ').$updated.' product(s).');

        return self::SUCCESS;
    }
}
