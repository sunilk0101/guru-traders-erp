<?php

namespace App\Services\Procurement;

use App\Models\PurchaseOrder;
use App\Services\Export\GeminiDocumentExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OCR a supplier-filled PO reply PDF/scan and apply confirmed fields onto the PO.
 */
class PurchaseOrderOcrService
{
    public function __construct(private readonly GeminiDocumentExtractor $ocr)
    {
    }

    /**
     * @return array{
     *     supplier_name: ?string,
     *     dispatch_date: ?string,
     *     delivery_details: ?string,
     *     packing_details: ?string,
     *     remarks: ?string,
     *     items: list<array{design_no: ?string, description: ?string, unit: ?string, qty: ?float, colour: ?string, size: ?string, notes: ?string}>
     * }
     */
    public function extract(UploadedFile $file, PurchaseOrder $po): array
    {
        $po->loadMissing(['supplier', 'items.product']);

        $expectedItems = $po->items->map(function ($item) {
            return trim(implode(' | ', array_filter([
                $item->design_no,
                $item->product?->name,
                $item->description,
                'PO qty '.$item->qty,
            ])));
        })->filter()->values()->implode("\n");

        $prompt = <<<PROMPT
This is a Purchase Order reply / confirmation form filled by a garment supplier (handwritten or typed on our PO PDF format).

Our PO number is: {$po->po_num}
Supplier on file: {$po->supplier?->company_name}

Expected lines on the PO (for matching only — do not invent extra styles):
{$expectedItems}

Return ONLY a JSON object with:
- supplier_name: string|null
- dispatch_date: string|null as YYYY-MM-DD
- delivery_details: string|null
- packing_details: string|null
- remarks: string|null — supplier notes / confirmation remarks
- items: array of {
    design_no: string|null,
    description: string|null,
    unit: string|null,
    qty: number|null — confirmed quantity,
    colour: string|null,
    size: string|null,
    notes: string|null
  }

Extract only what is clearly written. Use null when unclear. Prefer matching design_no / description to the expected lines above.
PROMPT;

        $raw = $this->ocr->extractStructured($file, $prompt);

        $items = [];
        foreach ($raw['items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $items[] = [
                'design_no'   => $this->nullStr($row['design_no'] ?? null),
                'description' => $this->nullStr($row['description'] ?? null),
                'unit'        => $this->nullStr($row['unit'] ?? null),
                'qty'         => is_numeric($row['qty'] ?? null) ? 0 + $row['qty'] : null,
                'colour'      => $this->nullStr($row['colour'] ?? null),
                'size'        => $this->nullStr($row['size'] ?? null),
                'notes'       => $this->nullStr($row['notes'] ?? null),
            ];
        }

        return [
            'supplier_name'    => $this->nullStr($raw['supplier_name'] ?? null),
            'dispatch_date'    => $this->nullStr($raw['dispatch_date'] ?? null),
            'delivery_details' => $this->nullStr($raw['delivery_details'] ?? null),
            'packing_details'  => $this->nullStr($raw['packing_details'] ?? null),
            'remarks'          => $this->nullStr($raw['remarks'] ?? null),
            'items'            => $items,
        ];
    }

    /**
     * Apply OCR result onto PO header (+ optional qty updates matched by design_no).
     *
     * @param  array<string, mixed>  $payload
     */
    public function apply(
        PurchaseOrder $po,
        array $payload,
        bool $updateItemQtys = true,
        ?UploadedFile $sourceFile = null,
    ): PurchaseOrder {
        return DB::transaction(function () use ($po, $payload, $updateItemQtys, $sourceFile) {
            $po->load(['items.colours.sizes', 'timelineEntries']);

            $header = [];
            if (filled($payload['dispatch_date'] ?? null)) {
                $header['dispatch_date'] = $payload['dispatch_date'];
            }
            if (array_key_exists('delivery_details', $payload) && $payload['delivery_details'] !== null) {
                $header['delivery_details'] = $payload['delivery_details'];
            }
            if (array_key_exists('packing_details', $payload) && $payload['packing_details'] !== null) {
                $header['packing_details'] = $payload['packing_details'];
            }
            if (array_key_exists('remarks', $payload) && $payload['remarks'] !== null) {
                $header['remarks'] = $payload['remarks'];
            }

            if ($header !== []) {
                $po->update($header);
            }

            $matched = 0;
            if ($updateItemQtys) {
                foreach ($payload['items'] ?? [] as $row) {
                    if (! is_array($row) || ! is_numeric($row['qty'] ?? null)) {
                        continue;
                    }

                    $item = $this->matchItem($po, $row);
                    if (! $item) {
                        continue;
                    }

                    $newQty = (int) round((float) $row['qty']);
                    $item->update([
                        'qty'    => $newQty,
                        'amount' => round($newQty * (float) ($item->cost_price ?? 0), 2),
                    ]);

                    $colour = $item->colours()->first();
                    if ($colour) {
                        $colour->sizes()->delete();
                        $colour->sizes()->create([
                            'size'       => filled($row['size'] ?? null) ? (string) $row['size'] : '',
                            'qty'        => $newQty,
                            'sort_order' => 0,
                        ]);
                        if (filled($row['colour'] ?? null)) {
                            $colour->update(['colour' => $row['colour']]);
                        }
                    }

                    $matched++;
                }
            }

            $note = 'Supplier PO reply imported via OCR'
                .($matched ? " — updated qty on {$matched} line(s)." : ' — header fields updated.');

            if ($sourceFile) {
                $stored = $sourceFile->store('purchase-orders/ocr', 'public');
                $note .= ' Source: '.$sourceFile->getClientOriginalName()." ({$stored})";
            }

            $po->timelineEntries()->create([
                'entry_date' => now()->toDateString(),
                'note'       => $note,
                'qty'        => null,
                'sort_order' => (int) $po->timelineEntries()->max('sort_order') + 1,
            ]);

            return $po->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchItem(PurchaseOrder $po, array $row): ?\App\Models\PurchaseOrderItem
    {
        $design = Str::lower(trim((string) ($row['design_no'] ?? '')));
        $desc = Str::lower(trim((string) ($row['description'] ?? '')));

        if ($design !== '') {
            $hit = $po->items->first(fn ($i) => Str::lower((string) $i->design_no) === $design);
            if ($hit) {
                return $hit;
            }
        }

        if ($desc !== '') {
            return $po->items->first(function ($i) use ($desc) {
                $hay = Str::lower(trim(implode(' ', array_filter([
                    $i->design_no,
                    $i->description,
                    $i->product?->name,
                ]))));

                return $hay !== '' && (str_contains($hay, $desc) || str_contains($desc, $hay));
            });
        }

        return null;
    }

    private function nullStr(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
