<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Trim / accessory catalog — "I need a small image against every line
 * which will be added while making the bom cost ... a separate photo per
 * trim type" (16-Sep call). One row per trim type (Main Label, Size Label,
 * Zip, ...); the Inquiry BOM trims panel matches each typed line name
 * against this table (case-insensitive) to show its reference photo.
 */
class TrimAccessory extends Model
{
    use Filterable, HasAuditColumns, SoftDeletes;

    protected $fillable = [
        'name',
        'image_path',
        'status',
        'remarks',
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null);
    }

    public function searchable(): array
    {
        return ['name', 'remarks'];
    }

    public function sortable(): array
    {
        return ['id', 'name', 'status', 'created_at'];
    }
}
