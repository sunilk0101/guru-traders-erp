<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a Product BOM line on an inquiry item.
 * Qty is per finished piece.
 */
class InquiryItemBomLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inquiry_item_id',
        'sort_order',
        'component_name',
        'size',
        'qty',
        'rate',
        'unit',
        'is_custom',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'qty'        => 'decimal:4',
            'rate'       => 'decimal:4',
            'is_custom'  => 'boolean',
        ];
    }

    /** Consumption × Rate for one finished piece. */
    public function lineTotal(): float
    {
        return round((float) $this->qty * (float) ($this->rate ?? 0), 4);
    }

    public function inquiryItem(): BelongsTo
    {
        return $this->belongsTo(InquiryItem::class);
    }
}
