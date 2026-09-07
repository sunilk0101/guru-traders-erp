<?php

namespace App\Services\Masters;

use App\Models\DocumentFormat;
use App\Models\DocumentFormatColumn;
use App\Models\DocumentFormatUnit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentFormatService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DocumentFormat
    {
        return DB::transaction(function () use ($data) {
            $format = DocumentFormat::create($this->columns($data));

            $this->syncUnits($format, $data['units'] ?? []);
            $this->syncColumns($format, $data);
            $this->syncImages($format, $data);

            return $format;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DocumentFormat $format, array $data): DocumentFormat
    {
        return DB::transaction(function () use ($format, $data) {
            $format->update($this->columns($data));

            $this->syncUnits($format, $data['units'] ?? []);
            $this->syncColumns($format, $data);
            $this->syncImages($format, $data);

            return $format->refresh();
        });
    }

    /**
     * A format a category still offers cannot be deleted — category_format is
     * restrictOnDelete on the format side, so the database would reject it
     * anyway. Checked here so the user gets a sentence instead of a 500.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function canDelete(DocumentFormat $format): array
    {
        $count = $format->categories()->count();

        if ($count > 0) {
            return [
                'allowed' => false,
                'reason'  => "This format is linked to {$count} category/categories. Remove it from them first.",
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Delete a format and the images it owns.
     *
     * The rows go with the cascade, but the files on disk do not — those have
     * to be removed explicitly or the public disk grows forever. Done before
     * the delete: if the delete then fails, an orphaned file is a smaller
     * problem than a row pointing at a file that is gone.
     */
    public function delete(DocumentFormat $format): void
    {
        DB::transaction(function () use ($format) {
            foreach ($format->images as $image) {
                Storage::disk('public')->delete($image->path);
            }

            $format->images()->delete();
            $format->delete();
        });
    }

    /**
     * Strip the keys that are children rather than columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        return array_diff_key($data, array_flip([
            'units', 'columns', 'column_order', 'images', 'keep_images',
        ]));
    }

    /**
     * Rewrite the unit list from the submitted chips.
     *
     * Deleted and re-inserted rather than diffed: sort_order is the order they
     * appear in, so removing a middle chip renumbers everything after it, and
     * nothing references a unit row by id. The request has already upper-cased
     * and de-duplicated them.
     *
     * @param  array<int, string>  $units
     */
    private function syncUnits(DocumentFormat $format, array $units): void
    {
        $format->units()->delete();

        foreach (array_values($units) as $index => $name) {
            $format->units()->create([
                'name'       => $name,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Rewrite the column list.
     *
     * Order now comes from the posted `column_order` — the list of column
     * keys in the sequence the user last dropped them into by dragging rows
     * in the builder — rather than always following
     * DocumentFormatColumn::STANDARD's fixed order. Each entry is either a
     * standard key (kept as-is, `print_only` still wins from STANDARD
     * regardless of what was posted) or a custom row (its real key is always
     * re-derived from its current label — the temporary id the browser gave
     * it while it was being built client-side is never trusted as storage).
     *
     * A disabled standard column is still written, with is_enabled false — the
     * label the user typed against it survives being switched off and back on.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncColumns(DocumentFormat $format, array $data): void
    {
        $format->columns()->delete();

        $posted = (array) ($data['columns'] ?? []);
        $order  = (array) ($data['column_order'] ?? array_keys($posted));

        $used      = [];
        $sortOrder = 0;

        foreach ($order as $clientKey) {
            $row = (array) ($posted[$clientKey] ?? []);
            $isStandard = array_key_exists($clientKey, DocumentFormatColumn::STANDARD);

            if ($isStandard) {
                $defaults = DocumentFormatColumn::STANDARD[$clientKey];
                $key        = $clientKey;
                $label      = filled($row['label'] ?? null) ? trim($row['label']) : $defaults['label'];
                $printOnly  = $defaults['print_only'];
                $isCustom   = false;
            } elseif (filled($row['is_custom'] ?? null)) {
                $label = trim((string) ($row['label'] ?? ''));

                if ($label === '') {
                    continue;
                }

                $key       = $this->uniqueKey($label, $used);
                $printOnly = false;
                $isCustom  = true;
            } else {
                // Neither a recognized standard key nor flagged as custom —
                // not something our own form posts; the request already
                // rejects this, this is just a second line of defence.
                continue;
            }

            $used[] = $key;

            $format->columns()->create([
                'key'          => $key,
                'label'        => $label,
                'is_enabled'   => filled($row['enabled'] ?? null),
                'is_mandatory' => filled($row['mandatory'] ?? null),
                'is_custom'    => $isCustom,
                'print_only'   => $printOnly,
                // Only meaningful on the `size` key — every renderer keys off
                // that column specifically, same as the prototype it mirrors.
                'sub_columns'  => $this->parseSubColumns($row['sub_columns'] ?? null),
                'sort_order'   => $sortOrder++,
            ]);
        }

        // A standard column the posted order somehow left out (a stale cached
        // form, mainly) still gets written — disabled — so a format can never
        // silently lose a standard column just because the page didn't know
        // about it.
        foreach (DocumentFormatColumn::STANDARD as $key => $defaults) {
            if (in_array($key, $used, true)) {
                continue;
            }

            $format->columns()->create([
                'key'          => $key,
                'label'        => $defaults['label'],
                'is_enabled'   => false,
                'is_mandatory' => false,
                'is_custom'    => false,
                'print_only'   => $defaults['print_only'],
                'sub_columns'  => null,
                'sort_order'   => $sortOrder++,
            ]);
        }
    }

    /**
     * The Size row's sub-columns arrive as one comma-separated string built
     * client-side from its chip list. Split, trim, de-duplicate, drop empties
     * — a blank chip list posts as an empty string, which becomes null so a
     * format with no size breakdown stores nothing rather than `[]` noise.
     */
    private function parseSubColumns(?string $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        $tags = collect(explode(',', $raw))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $tags ?: null;
    }

    /**
     * A slug for a custom column, guaranteed not to collide with a standard
     * key or with another custom column on the same format — the table has a
     * unique index on (format, key) and two columns both called "Remarks"
     * would otherwise fail the insert rather than the validation.
     *
     * @param  array<int, string>  $used
     */
    private function uniqueKey(string $label, array $used): string
    {
        $base = str($label)->slug('_')->limit(30, '')->toString();
        $base = $base !== '' ? $base : 'column';

        $key = $base;
        $n   = 2;

        while (in_array($key, $used, true)) {
            $key = "{$base}_{$n}";
            $n++;
        }

        return $key;
    }

    /**
     * Add newly uploaded images, drop the ones the user removed.
     *
     * Unlike units and columns this is a genuine diff, not a rewrite: the rows
     * point at files on disk, and deleting and re-inserting them would mean
     * re-uploading every image on every save.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncImages(DocumentFormat $format, array $data): void
    {
        // Absent means "the form did not offer the field", not "remove
        // everything" — only treat it as a removal list when it was posted.
        if (array_key_exists('keep_images', $data)) {
            $keep = array_map('intval', (array) ($data['keep_images'] ?? []));

            foreach ($format->images()->whereNotIn('id', $keep ?: [0])->get() as $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }
        }

        // Captions on images that remain.
        foreach ((array) ($data['image_captions'] ?? []) as $imageId => $caption) {
            $format->images()->whereKey((int) $imageId)->update([
                'caption' => filled($caption) ? trim((string) $caption) : null,
            ]);
        }

        $order = (int) $format->images()->max('sort_order');
        $newCaptions = array_values((array) ($data['new_image_captions'] ?? []));
        $newIndex = 0;

        foreach ((array) ($data['images'] ?? []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $caption = $newCaptions[$newIndex] ?? null;
            $newIndex++;

            $format->images()->create([
                // store() generates the name, so a file called "../../x.png"
                // cannot decide where it lands.
                'path'          => $file->store('order-formats', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'caption'       => filled($caption) ? trim((string) $caption) : null,
                'sort_order'    => ++$order,
            ]);
        }
    }

    /**
     * What a brand-new format starts with, so the create form is not a blank
     * grid the user has to fill in from nothing. Matches the prototype: every
     * standard column on, and the six default unit chips.
     *
     * @return array{columns: array<string, array{label: string, enabled: bool, mandatory: bool, print_only: bool, sub_columns: array}>, units: array<int, string>}
     */
    public function defaults(): array
    {
        $columns = [];

        foreach (DocumentFormatColumn::STANDARD as $key => $meta) {
            $columns[$key] = [
                'label'       => $meta['label'],
                'enabled'     => true,
                'mandatory'   => false,
                'print_only'  => $meta['print_only'],
                'sub_columns' => [],
            ];
        }

        return ['columns' => $columns, 'units' => DocumentFormatUnit::DEFAULTS];
    }
}
