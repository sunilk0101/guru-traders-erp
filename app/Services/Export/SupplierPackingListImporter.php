<?php

namespace App\Services\Export;

use App\Models\ExportDocument;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Parse supplier-filled packing list (Excel or OCR'd PDF) into carton rows
 * for ExportDocumentService::syncCartons().
 */
class SupplierPackingListImporter
{
    public function __construct(
        private readonly GeminiDocumentExtractor $ocr,
        private readonly ExportDocumentService $documents,
    ) {
    }

    /**
     * @return array{
     *     supplier_name: ?string,
     *     reference_no: ?string,
     *     remarks: ?string,
     *     cartons: list<array{carton_no: string, net_weight: ?float, gross_weight: ?float, dimensions: ?string, lines: list<array{description: string, unit: string, qty: float|int}>}>
     * }
     */
    public function fromExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if ($rows === []) {
            throw new RuntimeException('The Excel file is empty.');
        }

        $header = array_map(fn ($h) => Str::lower(trim((string) $h)), $rows[0] ?? []);
        $map = $this->headerMap($header);

        if (! isset($map['carton_no']) || ! isset($map['description'])) {
            throw new RuntimeException('Excel must include "Carton No" and "Description" columns (use the download template).');
        }

        $grouped = [];

        foreach (array_slice($rows, 1) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cartonNo = trim((string) ($row[$map['carton_no']] ?? ''));
            $description = trim((string) ($row[$map['description']] ?? ''));

            if ($cartonNo === '' && $description === '') {
                continue;
            }

            if ($cartonNo === '') {
                throw new RuntimeException('Every filled line needs a Carton No.');
            }

            if ($description === '') {
                continue;
            }

            if (! isset($grouped[$cartonNo])) {
                $grouped[$cartonNo] = [
                    'carton_no'    => $cartonNo,
                    'net_weight'   => $this->num($row, $map['net_weight'] ?? null),
                    'gross_weight' => $this->num($row, $map['gross_weight'] ?? null),
                    'dimensions'   => $this->str($row, $map['dimensions'] ?? null),
                    'lines'        => [],
                ];
            } else {
                foreach (['net_weight', 'gross_weight'] as $w) {
                    if (($grouped[$cartonNo][$w] ?? null) === null) {
                        $grouped[$cartonNo][$w] = $this->num($row, $map[$w] ?? null);
                    }
                }
                if (blank($grouped[$cartonNo]['dimensions'] ?? null)) {
                    $grouped[$cartonNo]['dimensions'] = $this->str($row, $map['dimensions'] ?? null);
                }
            }

            $extra = $this->str($row, $map['colour_size'] ?? null);
            $lineDesc = $description.($extra ? ' — '.$extra : '');

            $grouped[$cartonNo]['lines'][] = [
                'description' => $lineDesc,
                'unit'        => strtoupper($this->str($row, $map['unit'] ?? null) ?: 'PCS'),
                'qty'         => $this->num($row, $map['qty'] ?? null) ?? 0,
            ];
        }

        if ($grouped === []) {
            throw new RuntimeException('No carton lines found in the Excel file.');
        }

        return [
            'supplier_name' => null,
            'reference_no'  => null,
            'remarks'       => null,
            'cartons'       => array_values($grouped),
        ];
    }

    /**
     * @return array{
     *     supplier_name: ?string,
     *     reference_no: ?string,
     *     remarks: ?string,
     *     cartons: list<array{carton_no: string, net_weight: ?float, gross_weight: ?float, dimensions: ?string, lines: list<array{description: string, unit: string, qty: float|int}>}>
     * }
     */
    public function fromPdfOrImage(UploadedFile $file): array
    {
        $prompt = <<<'PROMPT'
This is a supplier packing list (PDF or scan) filled by an outside packer / supplier for a garment export shipment.

Return ONLY a JSON object with these keys:
- supplier_name: string|null — supplier / packer name if printed
- reference_no: string|null — packing list / invoice / PO reference if printed
- remarks: string|null — any header note
- cartons: array of objects, each with:
  - carton_no: string (required)
  - net_weight: number|null (kg)
  - gross_weight: number|null (kg)
  - dimensions: string|null (e.g. 40x30x25)
  - lines: array of { description: string, unit: string|null (PCS/SETS/MTRS), qty: number }

Read every carton and every line. Do not invent cartons. Use null when unclear.
PROMPT;

        $raw = $this->ocr->extractStructured($file, $prompt);

        return $this->normalizeParsed($raw);
    }

    /**
     * Tag line descriptions with supplier code and write cartons onto the ED.
     * Optionally keep the supplier's original Excel/PDF on the packing-list checklist.
     *
     * @param  list<array{carton_no: string, net_weight?: mixed, gross_weight?: mixed, dimensions?: ?string, lines?: array}>  $cartons
     * @param  'replace'|'append'  $mode
     */
    public function apply(
        ExportDocument $document,
        array $cartons,
        string $mode = 'replace',
        ?Supplier $supplier = null,
        ?UploadedFile $sourceFile = null,
        ?string $referenceNo = null,
        ?string $remarks = null,
    ): void {
        $prepared = $this->prepareCartons($cartons, $supplier);

        if ($mode === 'append') {
            $existing = $document->cartons()->with('lines')->get()->map(function ($carton) {
                return [
                    'carton_no'    => $carton->carton_no,
                    'net_weight'   => $carton->net_weight,
                    'gross_weight' => $carton->gross_weight,
                    'dimensions'   => $carton->dimensions,
                    'lines'        => $carton->lines->map(fn ($l) => [
                        'description' => $l->description,
                        'unit'        => $l->unit,
                        'qty'         => $l->qty,
                    ])->all(),
                ];
            })->all();

            $prepared = array_merge($existing, $prepared);
        }

        $this->documents->syncCartons($document, $prepared);

        $totals = $document->fresh()->cartons;
        $document->update([
            'total_cartons' => $totals->count() ?: $document->total_cartons,
            'net_weight'    => $totals->sum(fn ($c) => (float) ($c->net_weight ?? 0)) ?: $document->net_weight,
            'gross_weight'  => $totals->sum(fn ($c) => (float) ($c->gross_weight ?? 0)) ?: $document->gross_weight,
        ]);

        $this->documents->ensureChecklist($document);

        $entry = $document->checklist()
            ->whereHas('type', fn ($q) => $q->where('code', 'packing_list'))
            ->orderBy('id')
            ->first();

        if ($entry) {
            $noteParts = array_filter([
                'Imported from supplier packing form',
                $supplier ? ('Supplier: '.$supplier->label) : null,
                $remarks,
            ]);

            $payload = [
                'reference_no'        => $referenceNo,
                'remarks'             => implode(' · ', $noteParts),
                'matched_supplier_id' => $supplier?->id,
                'matched_order_confirmation_id' => $document->order_confirmation_id,
            ];

            if ($sourceFile) {
                $payload['file'] = $sourceFile;
                $payload['mark_done'] = true;
            }

            $this->documents->recordChecklist($entry, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *     supplier_name: ?string,
     *     reference_no: ?string,
     *     remarks: ?string,
     *     cartons: list<array{carton_no: string, net_weight: ?float, gross_weight: ?float, dimensions: ?string, lines: list<array{description: string, unit: string, qty: float|int}>}>
     * }
     */
    private function normalizeParsed(array $raw): array
    {
        $cartons = [];

        foreach ($raw['cartons'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cartonNo = trim((string) ($row['carton_no'] ?? ''));
            if ($cartonNo === '') {
                continue;
            }

            $lines = [];
            foreach ($row['lines'] ?? [] as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $description = trim((string) ($line['description'] ?? ''));
                if ($description === '') {
                    continue;
                }
                $lines[] = [
                    'description' => $description,
                    'unit'        => strtoupper(trim((string) ($line['unit'] ?? 'PCS')) ?: 'PCS'),
                    'qty'         => is_numeric($line['qty'] ?? null) ? 0 + $line['qty'] : 0,
                ];
            }

            if ($lines === []) {
                continue;
            }

            $cartons[] = [
                'carton_no'    => $cartonNo,
                'net_weight'   => is_numeric($row['net_weight'] ?? null) ? (float) $row['net_weight'] : null,
                'gross_weight' => is_numeric($row['gross_weight'] ?? null) ? (float) $row['gross_weight'] : null,
                'dimensions'   => filled($row['dimensions'] ?? null) ? trim((string) $row['dimensions']) : null,
                'lines'        => $lines,
            ];
        }

        if ($cartons === []) {
            throw new RuntimeException('OCR did not find any carton lines. Check the scan or use the Excel template.');
        }

        return [
            'supplier_name' => filled($raw['supplier_name'] ?? null) ? trim((string) $raw['supplier_name']) : null,
            'reference_no'  => filled($raw['reference_no'] ?? null) ? trim((string) $raw['reference_no']) : null,
            'remarks'       => filled($raw['remarks'] ?? null) ? trim((string) $raw['remarks']) : null,
            'cartons'       => $cartons,
        ];
    }

    /**
     * @param  list<array{carton_no: string, net_weight?: mixed, gross_weight?: mixed, dimensions?: ?string, lines?: array}>  $cartons
     * @return list<array{carton_no: string, net_weight: mixed, gross_weight: mixed, dimensions: ?string, lines: list<array{description: string, unit: string, qty: mixed}>}>
     */
    private function prepareCartons(array $cartons, ?Supplier $supplier): array
    {
        $prefix = $supplier
            ? '['.($supplier->display_code ?: $supplier->company_name).'] '
            : '';

        $out = [];

        foreach ($cartons as $row) {
            $cartonNo = trim((string) ($row['carton_no'] ?? ''));
            if ($cartonNo === '') {
                continue;
            }

            $lines = [];
            foreach ($row['lines'] ?? [] as $line) {
                $description = trim((string) ($line['description'] ?? ''));
                if ($description === '') {
                    continue;
                }
                if ($prefix !== '' && ! str_starts_with($description, $prefix)) {
                    $description = $prefix.$description;
                }
                $lines[] = [
                    'description' => $description,
                    'unit'        => strtoupper(trim((string) ($line['unit'] ?? 'PCS')) ?: 'PCS'),
                    'qty'         => $line['qty'] ?? 0,
                ];
            }

            if ($lines === []) {
                continue;
            }

            $out[] = [
                'carton_no'    => $cartonNo,
                'net_weight'   => $row['net_weight'] ?? null,
                'gross_weight' => $row['gross_weight'] ?? null,
                'dimensions'   => $row['dimensions'] ?? null,
                'lines'        => $lines,
            ];
        }

        if ($out === []) {
            throw new RuntimeException('Nothing to apply — no valid carton lines.');
        }

        return $out;
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function headerMap(array $header): array
    {
        $aliases = [
            'carton_no'    => ['carton no', 'carton', 'ctn', 'ctn no', 'carton_no', 'box no'],
            'net_weight'   => ['net weight (kg)', 'net weight', 'net wt', 'n.wt', 'net'],
            'gross_weight' => ['gross weight (kg)', 'gross weight', 'gross wt', 'g.wt', 'gross'],
            'dimensions'   => ['dimensions (lxwxh cm)', 'dimensions', 'size cm', 'lwh', 'cbm size'],
            'description'  => ['description', 'item', 'product', 'style', 'design'],
            'unit'         => ['unit', 'uom'],
            'qty'          => ['qty', 'quantity', 'pcs'],
            'colour_size'  => ['colour / size', 'colour/size', 'color / size', 'colour', 'size'],
            'remarks'      => ['remarks', 'note', 'notes'],
        ];

        $map = [];
        foreach ($aliases as $key => $names) {
            foreach ($header as $i => $label) {
                if (in_array($label, $names, true)) {
                    $map[$key] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function str(array $row, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }
        $v = $row[$index] ?? null;
        if ($v === null || $v === '') {
            return null;
        }

        return trim((string) $v);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function num(array $row, ?int $index): ?float
    {
        $v = $this->str($row, $index);
        if ($v === null || ! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }
}
