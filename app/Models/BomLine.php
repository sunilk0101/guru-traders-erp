<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BomLine extends Model
{
    public $timestamps = false;

    /** @var array<string, string>|null */
    private static ?array $trimThumbs = null;

    protected $fillable = [
        'bom_id',
        'sort_order',
        'component_name',
        'size',
        'qty',
        'rate',
        'unit',
        'remarks',
        'image_path',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'qty'        => 'decimal:4',
            'rate'       => 'decimal:4',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function lineTotal(): float
    {
        return round((float) $this->qty * (float) ($this->rate ?? 0), 4);
    }

    /**
     * Own photo first; otherwise the Trim / Accessory catalog photo for the same name.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            if ($this->image_path) {
                return Storage::disk('public')->url($this->image_path);
            }

            $name = strtolower(trim((string) $this->component_name));

            return $name === '' ? null : (self::trimThumbs()[$name] ?? null);
        });
    }

    /**
     * @return array<string, string>
     */
    private static function trimThumbs(): array
    {
        return self::$trimThumbs ??= TrimAccessory::query()
            ->whereNotNull('image_path')
            ->get(['name', 'image_path'])
            ->mapWithKeys(fn (TrimAccessory $row) => [strtolower($row->name) => $row->image_url])
            ->filter()
            ->all();
    }
}
