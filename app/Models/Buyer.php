<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Buyer master — Buyer Master sheet. Column mapping is in the 000800 migration.
 *
 * `display_code` is NOT fillable, same treatment as `Category::$code`: it is
 * assigned server-side from the 'buyer' number series (BUY01, BUY02, ...) in
 * BuyerService::create(), precisely so a crafted POST cannot supply its own.
 * The sheet originally asked for a typed code with a duplicate warning; the
 * client has since asked for auto-generated codes instead.
 */
class Buyer extends Model
{
    use Filterable, HasAuditColumns, SoftDeletes;

    /**
     * The carton-mark lines the sheet draws, in print order. Lines 1–3 are
     * starred on the sheet; 4 and 5 are labelled optional.
     *
     * Seeded onto a new buyer as a starting point — the labels are editable and
     * more lines can be added, which is why they are rows and not columns.
     *
     * @var array<int, array{label: string, is_required: bool, placeholder: string}>
     */
    public const DEFAULT_CARTON_LINES = [
        ['label' => 'BUYER NAME',  'is_required' => true,  'placeholder' => 'ABC CORP'],
        ['label' => 'DESTINATION', 'is_required' => true,  'placeholder' => 'LONDON'],
        ['label' => 'ORDER REF',   'is_required' => true,  'placeholder' => 'C/NO'],
        ['label' => 'MADE IN',     'is_required' => false, 'placeholder' => 'MADE IN INDIA'],
        ['label' => 'GROSS WT',    'is_required' => false, 'placeholder' => 'GROSS WT:'],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_name',
        'name_on_export_invoice',
        'contact_person',
        'contact_designation_id',
        'email',
        'mobile',
        'gst_vat_no',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'pincode',
        'port_id',
        'agent_id',
        'agent_commission_type',
        'agent_commission_value',
        'payment_term_id',
        'advance_percent',
        'sight_percent',
        'incoterm_id',
        'shipment_method_id',
        'currency_id',
        'bank_name',
        'account_number',
        'swift_code',
        'status',
        'remarks',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'agent_commission_value' => 'decimal:4',
            'advance_percent'        => 'decimal:2',
            'sight_percent'          => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** Col D — "allow multiple selection ... connected from the category master". */
    public function categories(): BelongsToMany
    {
        // No withTimestamps() — the pivot has none. See the 000800 migration.
        return $this->belongsToMany(Category::class, 'buyer_category');
    }

    /** Col X. Ordered here so callers never have to remember to sort. */
    public function cartonMarkings(): HasMany
    {
        return $this->hasMany(BuyerCartonMarking::class)->orderBy('line_no');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }

    /** Col E's designation — same lookup Supplier/Jobber contacts use. */
    public function contactDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'contact_designation_id');
    }

    /** Change request #3 — up to 3 additional contacts, beyond cols E–G above. */
    public function contacts(): HasMany
    {
        return $this->hasMany(BuyerContact::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function incoterm(): BelongsTo
    {
        return $this->belongsTo(Incoterm::class);
    }

    public function shipmentMethod(): BelongsTo
    {
        return $this->belongsTo(ShipmentMethod::class);
    }

    /**
     * The default currency — the one pre-selected on a new order and shown on
     * the list screen. `currencies()` is the full set this buyer may be
     * invoiced in. See the 000200 expand migration for why both exist.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accepted sets
    |--------------------------------------------------------------------------
    | A buyer invoiced in USD on one order and AED on the next is one buyer.
    | The BelongsTo above each of these is the default within the set, not a
    | competing answer.
    */

    public function currencies(): BelongsToMany
    {
        // No withTimestamps() — the pivots have none, same as buyer_category.
        return $this->belongsToMany(Currency::class, 'buyer_currency');
    }

    public function incoterms(): BelongsToMany
    {
        return $this->belongsToMany(Incoterm::class, 'buyer_incoterm');
    }

    public function shipmentMethods(): BelongsToMany
    {
        return $this->belongsToMany(ShipmentMethod::class, 'buyer_shipment_method');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Col P rendered for display — "2.5%" or "1.5000 USD".
     *
     * The type and the value are stored separately so settlement maths never
     * parses a label; this is the label, built back from them.
     */
    protected function agentCommissionLabel(): Attribute
    {
        return Attribute::get(function () {
            if (blank($this->agent_commission_value) || blank($this->agent_commission_type)) {
                return null;
            }

            $value = rtrim(rtrim(number_format((float) $this->agent_commission_value, 4, '.', ''), '0'), '.');

            return $this->agent_commission_type === 'percent'
                ? "{$value}%"
                : trim("{$value} ".($this->currency?->iso_code ?? ''));
        });
    }

    /** What the carton marks actually print as, one line per row. */
    protected function cartonMarkingPreview(): Attribute
    {
        return Attribute::get(
            fn () => $this->cartonMarkings
                ->filter(fn (BuyerCartonMarking $line) => filled($line->value))
                ->pluck('value')
                ->implode("\n")
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Filtering
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, string>
     */
    public function searchable(): array
    {
        return ['display_code', 'company_name', 'name_on_export_invoice', 'contact_person', 'email'];
    }

    /**
     * City is not sortable any more — it lives on a related table, and
     * Filterable::scopeSort() puts the column straight into ORDER BY without a
     * join. Sorting by city needs a join, so it is left out rather than
     * silently ordering by an id.
     *
     * @return array<int, string>
     */
    public function sortable(): array
    {
        return ['id', 'display_code', 'company_name', 'status', 'created_at'];
    }

    /**
     * Filterable::scopeSearch only knows about columns on this table. City moved
     * to its own table when the country/state/city dropdowns were made to
     * cascade, and the list screen has always offered it — so the relation is
     * folded into the same OR group rather than the search box quietly getting
     * narrower.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            foreach ($this->searchable() as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }

            $q->orWhereHas('city', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"));
            $q->orWhereHas('state', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"));
        });
    }

    /** "ABC Fashion Ltd (BUY01)" — for dropdowns on downstream screens. */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => "{$this->company_name} ({$this->display_code})");
    }
}
