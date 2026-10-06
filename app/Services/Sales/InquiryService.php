<?php

namespace App\Services\Sales;

use App\Models\Agent;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use App\Support\FinancialYear;
use Illuminate\Support\Facades\DB;

class InquiryService
{
    public function __construct(private readonly NumberSeriesService $numbers)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Inquiry
    {
        return DB::transaction(function () use ($data) {
            $financialYear = FinancialYear::current();

            // NumberSeriesSeeder only ever seeded `category`. Inquiry is the
            // first FY-scoped series, so a row has to be provisioned for
            // whatever year we're in rather than needing a seeder re-run
            // every April — firstOrCreate makes that safe under concurrent
            // requests the same way the unique(module, financial_year) index
            // already guards it.
            NumberSeries::firstOrCreate(
                ['module' => 'inquiry', 'financial_year' => $financialYear],
                ['prefix' => 'INQ/', 'padding' => 3, 'current_number' => 0, 'reset_yearly' => true]
            );

            $inquiry = new Inquiry($this->headerData($data));
            $inquiry->inquiry_no = $this->numbers->next('inquiry', $financialYear);
            $inquiry->financial_year = $financialYear;
            $inquiry->save();

            $this->syncItems($inquiry, $data['items'] ?? []);
            $this->syncFollowUps($inquiry, $data['followups'] ?? []);
            $this->syncHeaderFromItems($inquiry);

            return $inquiry;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Inquiry $inquiry, array $data): Inquiry
    {
        return DB::transaction(function () use ($inquiry, $data) {
            $inquiry->update($this->headerData($data));

            $this->syncItems($inquiry, $data['items'] ?? []);
            $this->syncFollowUps($inquiry, $data['followups'] ?? []);
            $this->syncHeaderFromItems($inquiry);

            return $inquiry->refresh();
        });
    }

    /**
     * Header category/format stay as the inquiry's summary (list, show, OC
     * conversion) — take them from the first item line that has them set.
     */
    private function syncHeaderFromItems(Inquiry $inquiry): void
    {
        $first = $inquiry->items()->orderBy('sort_order')->first();
        if (! $first) {
            return;
        }

        $inquiry->forceFill([
            'category_id'        => $first->category_id ?? $inquiry->category_id,
            'document_format_id' => $first->document_format_id ?? $inquiry->document_format_id,
        ])->save();
    }

    /**
     * Strip the keys that are children rather than columns on the header.
     * Commission always comes from Agent Master — never from a posted edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerData(array $data): array
    {
        $header = array_diff_key($data, array_flip(['items', 'followups']));

        $agentId = $header['agent_id'] ?? null;
        // If staff left Agent blank, snapshot the buyer's linked agent
        // (same as the form auto-fill) so older drafts catch up after Buyer Master is linked.
        if (blank($agentId) && filled($header['buyer_id'] ?? null)) {
            $agentId = Buyer::query()->whereKey($header['buyer_id'])->value('agent_id');
            $header['agent_id'] = $agentId;
        }

        if (blank($agentId)) {
            $header['agent_commission_type'] = null;
            $header['agent_commission_value'] = null;

            return $header;
        }

        $first = Agent::query()
            ->with('commissions')
            ->find($agentId)
            ?->commissions
            ->first();

        $header['agent_commission_type'] = $first
            ? ($first->commission_type === 'percent' ? 'percent' : 'flat')
            : null;
        $header['agent_commission_value'] = $first ? (float) $first->amount : null;

        return $header;
    }

    /**
     * Rewrite every item, colour and size row from what was posted.
     *
     * Delete-then-recreate, same call DocumentFormatService makes for units
     * and columns: nothing outside the inquiry references an item, colour or
     * size row by id, so diffing them would be pure ceremony. The DB-level
     * cascadeOnDelete on both child tables clears colours and sizes the
     * moment the item row above them goes.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Inquiry $inquiry, array $items): void
    {
        $inquiry->items()->delete();

        $sort = 0;
        foreach (array_values($items) as $itemData) {
            if ($this->itemRowIsEmpty($itemData)) {
                continue;
            }

            $item = $inquiry->items()->create([
                'sort_order'         => $sort++,
                'category_id'        => $itemData['category_id'] ?? $inquiry->category_id,
                'document_format_id' => $itemData['document_format_id'] ?? $inquiry->document_format_id,
                'design_no'          => $itemData['design_no'] ?? null,
                'description'        => $itemData['description'] ?? null,
                'product_id'         => $itemData['product_id'] ?? null,
                'supplier_id'        => $itemData['supplier_id'] ?? null,
                'unit'               => $itemData['unit'] ?? null,
                'fob_value_id'       => $itemData['fob_value_id'] ?? null,
                'price'              => $itemData['price'] ?? null,
                'cost_price'         => $itemData['cost_price'] ?? null,
                'bom_cost'           => $itemData['bom_cost'] ?? null,
                'fob_unit'           => $itemData['fob_unit'] ?? null,
                'status'             => $itemData['status'] ?? 'draft',
                'remarks'            => $itemData['remarks'] ?? null,
                'custom_values'      => array_filter((array) ($itemData['custom'] ?? []), fn ($v) => filled($v)) ?: null,
            ]);

            $qty = 0;
            $colours = $itemData['colours'] ?? [['colour' => null, 'sizes' => []]];

            foreach (array_values($colours) as $colourIndex => $colourData) {
                $colour = $item->colours()->create([
                    'colour'     => $colourData['colour'] ?? null,
                    'sort_order' => $colourIndex,
                ]);

                foreach (array_values($colourData['sizes'] ?? []) as $sizeIndex => $sizeData) {
                    $sizeQty = (int) ($sizeData['qty'] ?? 0);

                    if (blank($sizeData['size'] ?? null) && $sizeQty === 0) {
                        continue;
                    }

                    $colour->sizes()->create([
                        'size'       => $sizeData['size'] ?? '',
                        'qty'        => $sizeQty,
                        'sort_order' => $sizeIndex,
                    ]);

                    $qty += $sizeQty;
                }
            }

            $bomCost = 0.0;
            foreach (array_values($itemData['bom'] ?? []) as $bomIndex => $bomRow) {
                if (blank($bomRow['component_name'] ?? null)) {
                    continue;
                }

                $lineQty = (float) ($bomRow['qty'] ?? 1);
                $lineRate = isset($bomRow['rate']) && $bomRow['rate'] !== '' ? (float) $bomRow['rate'] : null;
                $bomCost += $lineQty * (float) ($lineRate ?? 0);

                $item->bomLines()->create([
                    'sort_order'     => $bomIndex,
                    'component_name' => $bomRow['component_name'],
                    'size'           => $bomRow['size'] ?? null,
                    'qty'            => $lineQty,
                    'rate'           => $lineRate,
                    'unit'           => $bomRow['unit'] ?? null,
                    'is_custom'      => (bool) ($bomRow['is_custom'] ?? true),
                    'remarks'        => $bomRow['remarks'] ?? null,
                    'image_path'     => $bomRow['image_path'] ?? null,
                ]);
            }

            $computedBom = round($bomCost, 2);
            $costPrice = (float) ($itemData['cost_price'] ?? 0);
            $finalCost = round($costPrice + $computedBom, 2);
            $fobUnit = isset($itemData['fob_unit']) && $itemData['fob_unit'] !== ''
                ? (float) $itemData['fob_unit']
                : $this->fobUnitFor($inquiry, $item->supplier_id, $finalCost);

            $unitPrice = (float) ($itemData['price'] ?? 0);
            if ($unitPrice <= 0 && $fobUnit > 0) {
                $unitPrice = $fobUnit;
            }

            $item->update([
                'qty'      => $qty,
                'amount'   => round($qty * $unitPrice, 2),
                'bom_cost' => $computedBom,
                'fob_unit' => $fobUnit,
            ]);
        }
    }

    /**
     * Skip blank table rows so "Add item row" placeholders are not saved.
     *
     * @param  array<string, mixed>  $itemData
     */
    private function itemRowIsEmpty(array $itemData): bool
    {
        $hasIdentity = filled($itemData['product_id'] ?? null)
            || filled($itemData['supplier_id'] ?? null)
            || filled($itemData['design_no'] ?? null)
            || filled($itemData['description'] ?? null);

        $qty = 0;
        foreach ($itemData['colours'] ?? [] as $colour) {
            foreach ($colour['sizes'] ?? [] as $size) {
                $qty += (int) ($size['qty'] ?? 0);
            }
        }

        $hasMoney = (float) ($itemData['price'] ?? 0) > 0
            || (float) ($itemData['cost_price'] ?? 0) > 0;

        return ! $hasIdentity && $qty === 0 && ! $hasMoney;
    }

    /**
     * FOB / client unit from Markup Master. Margin % never leaves this method
     * into the form UI — staff only see the resulting FOB figure.
     */
    private function fobUnitFor(Inquiry $inquiry, $supplierId, float $finalCost): float
    {
        if ($finalCost <= 0 || blank($supplierId) || blank($inquiry->buyer_id)) {
            return $finalCost;
        }

        $inquiry->loadMissing('currency');

        $markup = \App\Models\Markup::query()
            ->where('buyer_id', $inquiry->buyer_id)
            ->where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->first();

        $fobInr = $markup ? $markup->clientPrice($finalCost) : $finalCost;
        $rate = (float) ($inquiry->exchange_rate ?: 0);
        $iso = $inquiry->currency?->iso_code;

        if ($rate > 0 && $iso && strtoupper((string) $iso) !== 'INR') {
            return round($fobInr / $rate, 4);
        }

        return round($fobInr, 4);
    }

    /**
     * Add newly typed follow-ups, drop the ones the user removed.
     *
     * A genuine diff rather than a rewrite, unlike items above — unlike an
     * item row, a follow-up's created_by/created_at is the point of the
     * record ("track all buyer communication, dates & comments"). Deleting
     * and recreating it every time the inquiry is saved for an unrelated
     * reason would reattribute every past entry to whoever clicked Save
     * last. Existing entries carry their id back in the posted array and are
     * left untouched; only ids missing from the post are removed, and only
     * rows with no id are genuinely new.
     *
     * @param  array<int, array<string, mixed>>  $followups
     */
    private function syncFollowUps(Inquiry $inquiry, array $followups): void
    {
        $posted = collect($followups)->filter(fn ($row) => filled($row['comment'] ?? null));
        $keepIds = $posted->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $inquiry->followUps()->whereNotIn('id', $keepIds ?: [0])->delete();

        foreach ($posted as $row) {
            if (! empty($row['id'])) {
                continue;
            }

            $inquiry->followUps()->create([
                'follow_up_date' => $row['date'] ?? now()->toDateString(),
                'comment'        => $row['comment'],
                'created_by'     => auth()->id(),
            ]);
        }
    }
}
