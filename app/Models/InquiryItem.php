<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of an inquiry's item table — what DocumentFormatColumn::STANDARD
 * describes the shape of. Rewritten wholesale by InquiryService on every
 * save, same as DocumentFormatColumn/DocumentFormatUnit — nothing outside the
 * inquiry references a row here by id yet.
 */
class InquiryItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sort_order',
        'category_id',
        'document_format_id',
        'design_no',
        'description',
        'product_id',
        'supplier_id',
        'unit',
        'fob_value_id',
        'price',
        'cost_price',
        'qty',
        'amount',
        'status',
        'remarks',
        'custom_values',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'    => 'integer',
            'price'         => 'decimal:2',
            'cost_price'    => 'decimal:2',
            'qty'           => 'integer',
            'amount'        => 'decimal:2',
            'custom_values' => 'array',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(DocumentFormat::class, 'document_format_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function fobValue(): BelongsTo
    {
        return $this->belongsTo(FobValue::class);
    }

    public function colours(): HasMany
    {
        return $this->hasMany(InquiryItemColour::class)->orderBy('sort_order');
    }

    public function bomLines(): HasMany
    {
        return $this->hasMany(InquiryItemBomLine::class)->orderBy('sort_order');
    }

    public function statusLabel(): string
    {
        return Inquiry::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return Inquiry::STATUS_COLORS[$this->status] ?? 'secondary';
    }
}
