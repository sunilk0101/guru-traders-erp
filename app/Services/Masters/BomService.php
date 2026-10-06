<?php

namespace App\Services\Masters;

use App\Models\Bom;
use App\Models\TrimAccessory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BomService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Bom
    {
        return DB::transaction(function () use ($data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $data['key'] = $this->uniqueKey($data['name']);

            $bom = Bom::create($data);
            $this->syncLines($bom, $lines, []);

            return $bom->refresh()->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Bom $bom, array $data): Bom
    {
        return DB::transaction(function () use ($bom, $data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if (blank($bom->key)) {
                $data['key'] = $this->uniqueKey($data['name'] ?? $bom->name, $bom->id);
            }

            $previousPaths = $bom->lines()->pluck('image_path')->filter()->all();

            $bom->update($data);
            $this->syncLines($bom, $lines, $previousPaths);

            return $bom->refresh()->load('lines');
        });
    }

    public function delete(Bom $bom): void
    {
        $paths = $bom->lines()->pluck('image_path')->filter()->all();
        $bom->delete();

        foreach ($paths as $path) {
            $this->deleteOwnedImage($path);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  list<string>  $previousPaths
     */
    private function syncLines(Bom $bom, array $lines, array $previousPaths): void
    {
        $bom->lines()->delete();
        $kept = [];

        foreach (array_values($lines) as $index => $row) {
            if (blank($row['component_name'] ?? null)) {
                continue;
            }

            $path = $this->resolveLineImage($row);

            $bom->lines()->create([
                'sort_order'     => $index,
                'component_name' => $row['component_name'],
                'size'           => $row['size'] ?? null,
                'qty'            => $row['qty'] ?? 1,
                'rate'           => ($row['rate'] ?? '') === '' ? null : $row['rate'],
                'unit'           => $row['unit'] ?? null,
                'remarks'        => $row['remarks'] ?? null,
                'image_path'     => $path,
            ]);

            if ($path) {
                $kept[] = $path;
            }
        }

        foreach (array_diff($previousPaths, $kept) as $stale) {
            $this->deleteOwnedImage($stale);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveLineImage(array $row): ?string
    {
        $upload = $row['image'] ?? null;
        if ($upload instanceof UploadedFile) {
            return $upload->store('boms', 'public');
        }

        if (! empty($row['remove_image'])) {
            return null;
        }

        $existing = $row['existing_image_path'] ?? null;

        return filled($existing) ? (string) $existing : null;
    }

    private function deleteOwnedImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (TrimAccessory::query()->where('image_path', $path)->exists()) {
            return;
        }

        if (Bom::query()->whereHas('lines', fn ($q) => $q->where('image_path', $path))->exists()) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function uniqueKey(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'bom';
        $key = $base;
        $i = 2;

        while (
            Bom::withTrashed()
                ->where('key', $key)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $key = $base.'-'.$i;
            $i++;
        }

        return $key;
    }
}
