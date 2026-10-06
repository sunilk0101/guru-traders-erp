<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use Filterable, HasAuditColumns, SoftDeletes;

    protected $fillable = [
        'name',
        'key',
        'status',
        'remarks',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function searchable(): array
    {
        return ['name', 'remarks'];
    }

    public function sortable(): array
    {
        return ['id', 'name', 'status', 'created_at'];
    }

    public function lineTotal(): float
    {
        return round($this->lines->sum(fn (BomLine $line) => $line->lineTotal()), 2);
    }

    /**
     * Shape the Inquiry BOM dropdown already consumes (key / name / total / lines).
     *
     * @return array{key: string, name: string, total: float, lines: array<int, array<string, mixed>>}
     */
    public function templatePayload(): array
    {
        $lines = $this->lines->map(fn (BomLine $line) => [
            'component_name' => $line->component_name,
            'size'           => $line->size,
            'qty'            => (float) $line->qty,
            'rate'           => $line->rate !== null ? (float) $line->rate : 0,
            'unit'           => $line->unit,
            'remarks'        => $line->remarks,
            'image_path'     => $line->image_path,
            'image_url'      => $line->image_url,
        ])->values()->all();

        return [
            'key'   => $this->key ?: (string) $this->id,
            'name'  => $this->name,
            'total' => $this->lineTotal(),
            'lines' => $lines,
        ];
    }
}
