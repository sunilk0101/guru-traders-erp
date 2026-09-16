<?php

namespace App\Services\Masters;

use App\Models\TrimAccessory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Same image-handling shape as ProductService — 'image' upload / 'remove_image'
 * checkbox in, 'image_path' column out, stored on the public disk.
 */
class TrimAccessoryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TrimAccessory
    {
        return TrimAccessory::create($this->applyImage($data, null));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TrimAccessory $trimAccessory, array $data): TrimAccessory
    {
        $trimAccessory->update($this->applyImage($data, $trimAccessory));

        return $trimAccessory->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyImage(array $data, ?TrimAccessory $existing): array
    {
        $removeRequested = (bool) ($data['remove_image'] ?? false);
        $upload = $data['image'] ?? null;
        unset($data['image'], $data['remove_image']);

        if ($upload instanceof UploadedFile) {
            if ($existing?->image_path) {
                Storage::disk('public')->delete($existing->image_path);
            }
            $data['image_path'] = $upload->store('trim-accessories', 'public');

            return $data;
        }

        if ($removeRequested) {
            if ($existing?->image_path) {
                Storage::disk('public')->delete($existing->image_path);
            }
            $data['image_path'] = null;
        }

        return $data;
    }
}
