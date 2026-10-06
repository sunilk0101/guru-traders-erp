<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_confirmation_item_id',
        'sort_order',
        'design_no',
        'description',
        'product_id',
        'unit',
        'cost_price',
        'qty',
        'amount',
        'remarks',
        'image_path',
        'custom_values',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'    => 'integer',
            'cost_price'    => 'decimal:2',
            'qty'           => 'integer',
            'amount'        => 'decimal:2',
            'custom_values' => 'array',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** The OC line this was raised from — read-only reference for the OC FOB price shown alongside. */
    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(OrderConfirmationItem::class, 'order_confirmation_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function colours(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemColour::class)->orderBy('sort_order');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null);
    }
}
