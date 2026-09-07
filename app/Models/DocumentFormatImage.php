<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A reference image printed with the format — packing diagrams, label samples,
 * marking references. "Shown on print / PDF / Excel export only."
 */
class DocumentFormatImage extends Model
{
    protected $fillable = ['path', 'original_name', 'caption', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(DocumentFormat::class, 'document_format_id');
    }

    /** Public URL for the thumbnail on the form and show screens. */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->path));
    }
}
