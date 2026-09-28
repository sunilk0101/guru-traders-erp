<?php

/**
 * Parse Final 08092026.xlsx → JSON for products:import-json.
 *
 * New sheet adds explicit Category in column G (category split).
 * Products stay keyed by item_group_code; import upserts category_id.
 *
 * Run: php scripts/parse-final-08092026.php
 */

require dirname(__DIR__).'/vendor/autoload.php';

$path = $argv[1] ?? 'c:/Users/Lenovo/Downloads/Final 08092026.xlsx';
$out = dirname(__DIR__).'/storage/app/product-import-08092026.json';

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$reader = PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
$ss = $reader->load($path);
$sheet = $ss->getSheetByName('Data') ?: $ss->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

function clean(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;
    $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

    return trim($v);
}

function isBlank($v): bool
{
    $s = clean((string) $v);

    return $s === '' || strtoupper($s) === '#N/A' || $s === '-' || strtoupper($s) === '#DIV/0!';
}

function parsePercent($v): ?float
{
    if (is_numeric($v) && ! is_string($v)) {
        $n = (float) $v;
        if ($n > 0 && $n <= 1) {
            $n *= 100;
        }

        return round($n, 3);
    }

    $raw = clean((string) $v);
    if (isBlank($raw)) {
        return null;
    }
    $hadPct = str_contains($raw, '%');
    $s = str_replace(['%', ','], '', $raw);
    if (! is_numeric($s)) {
        return null;
    }
    $n = (float) $s;
    if (! $hadPct && $n > 0 && $n <= 1) {
        $n *= 100;
    }

    return round($n, 3);
}

function parseMoney($v): ?float
{
    $v = clean((string) $v);
    if (isBlank($v)) {
        return null;
    }
    $v = preg_replace('/[^\d.]/', '', $v) ?? '';
    if ($v === '' || ! is_numeric($v)) {
        return null;
    }

    return round((float) $v, 4);
}

function parseBand($v): ?string
{
    $v = strtoupper(clean((string) $v));
    if ($v === '' || $v === '#N/A' || $v === 'N/A' || $v === 'NA') {
        return 'NA';
    }
    if (preg_match('/\b(AA|AB|NA)\b/', $v, $m)) {
        return $m[1];
    }

    return null;
}

function parseGst($v): ?float
{
    if (isBlank($v)) {
        return null;
    }
    if (is_numeric($v) && ! is_string($v)) {
        $n = (float) $v;
        if ($n > 0 && $n <= 1) {
            $n *= 100;
        }

        return round($n, 2);
    }
    $s = str_replace('%', '', clean((string) $v));
    if (! is_numeric($s)) {
        return null;
    }

    return round((float) $s, 2);
}

function parseBasis($v): ?string
{
    $v = clean((string) $v);
    if (isBlank($v)) {
        return null;
    }
    $lower = strtolower($v);
    if (str_contains($lower, 'fob')) {
        return 'FOB Value';
    }
    if ($lower === 'qty' || str_contains($lower, 'quantity')) {
        return 'Quantity';
    }
    if (str_contains($lower, 'net value')) {
        return 'Net Value';
    }
    if (str_contains($lower, 'net weight')) {
        return 'Net Weight';
    }
    if (str_contains($lower, 'square') || str_contains($lower, 'sq')) {
        return 'Square Metre';
    }

    return $v;
}

function normalizeCategory(string $cat, string $label): string
{
    $cat = clean($cat);
    if ($cat !== '') {
        // Sheet spelling variants
        if (strcasecmp($cat, 'Girls Unstitched Material') === 0) {
            return 'Girls Unstitched Materials';
        }
        if (strcasecmp($cat, 'Ladies Unstitched Material') === 0) {
            return 'Ladies Unstitched Materials';
        }

        return $cat;
    }

    $u = strtoupper($label);
    if (str_contains($u, 'SAREE')) {
        return 'Saree';
    }
    if (str_contains($u, 'GIRLS') && str_contains($u, 'UNSTITCH')) {
        return 'Girls Unstitched Materials';
    }
    if (str_contains($u, 'GIRLS')) {
        return 'Girls Readymade Garments';
    }
    if (str_contains($u, 'BOYS')) {
        return 'Boys Readymade Garments';
    }
    if (str_contains($u, 'MENS') || str_contains($u, "MEN'S")) {
        return 'Mens Readymade Garments';
    }
    if (str_contains($u, 'UNSTITCH')) {
        return 'Ladies Unstitched Materials';
    }

    return 'Ladies Readymade Garments';
}

$products = [];
$skipped = 0;
$catCounts = [];

foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }

    $b = clean((string) ($r['B'] ?? ''));
    $c = clean((string) ($r['C'] ?? ''));
    $d = clean((string) ($r['D'] ?? ''));
    $e = clean((string) ($r['E'] ?? ''));
    $categoryCell = clean((string) ($r['G'] ?? ''));

    if ($b === '' || stripos($b, 'PRODUCT NAME') !== false || stripos($b, 'Item Group') !== false) {
        $skipped++;
        continue;
    }

    $code = null;
    $bandFromB = null;
    $nameFromB = $d !== '' ? $d : $b;

    // Prefer label column B — sheet sometimes leaves a stale Item Group Code in C
    // (e.g. rows 370–371: B=281-AA/… but C still 234-AB from the previous product).
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\/\s*(.+)$/u', $b, $m)) {
        $code = strtoupper($m[1].$m[2]);
        $bandFromB = strtoupper($m[2]);
        $nameFromLabel = clean($m[3]);

        $codeFromC = null;
        if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})$/u', $c, $cm)) {
            $codeFromC = strtoupper($cm[1].$cm[2]);
        }

        if ($codeFromC && $codeFromC !== $code) {
            $nameFromB = $nameFromLabel;
            $e = $nameFromLabel;
        } elseif ($d === '') {
            $nameFromB = $nameFromLabel;
        }
    } elseif (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*(.+)$/u', $b, $m)) {
        $code = strtoupper($m[1]);
        if ($d === '') {
            $nameFromB = clean($m[2]);
        }
    } elseif (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})$/u', $c, $m)) {
        $code = strtoupper($m[1].$m[2]);
        $bandFromB = strtoupper($m[2]);
    } elseif (preg_match('/^(\d+[A-Za-z]?)$/u', $c, $m)) {
        $code = strtoupper($m[1]);
    } else {
        $skipped++;
        continue;
    }

    $category = normalizeCategory($categoryCell, $b.' '.$nameFromB);
    $catCounts[$category] = ($catCounts[$category] ?? 0) + 1;

    $bandFromSheet = parseBand($r['J'] ?? null);
    $band = $bandFromSheet;
    if ($band === 'NA' && $bandFromB && in_array($bandFromB, ['AA', 'AB'], true)) {
        $band = $bandFromB;
    }
    $band = $band ?: 'NA';

    $unitPo = strtoupper(clean((string) ($r['L'] ?? ''))) ?: 'SET';
    $unitExport = strtoupper(clean((string) ($r['M'] ?? ''))) ?: $unitPo;
    $hsn = clean((string) ($r['N'] ?? ''));
    $drawbackSr = clean((string) ($r['O'] ?? ''));
    $barcode = clean((string) ($r['F'] ?? ''));
    $groupCode = clean((string) ($r['H'] ?? ''));
    $additional = clean((string) ($r['I'] ?? ''));
    $gst = parseGst($r['K'] ?? null);

    $drawbackPct = parsePercent($r['P'] ?? null);
    $drawbackBasis = parseBasis($r['Q'] ?? null) ?: 'FOB Value';
    $drawbackCap = parseMoney($r['R'] ?? null);
    $drawbackCapBasis = parseBasis($r['S'] ?? null);

    $rosctl1 = parsePercent($r['T'] ?? null);
    $rosctlBasis1 = parseBasis($r['U'] ?? null) ?: 'FOB Value';
    $rosctlCap1 = parseMoney($r['V'] ?? null);
    $rosctlCapBasis1 = parseBasis($r['W'] ?? null);
    $rosctl2 = parsePercent($r['X'] ?? null);
    $rosctlBasis2 = parseBasis($r['Y'] ?? null);
    $rosctlCap2 = parseMoney($r['Z'] ?? null);
    $rosctlCapBasis2 = parseBasis($r['AA'] ?? null);

    $rodtepPct = parsePercent($r['AB'] ?? null);
    $rodtepBasis = parseBasis($r['AC'] ?? null) ?: 'FOB Value';
    $rodtepCap = parseMoney($r['AD'] ?? null);
    $rodtepCapBasis = parseBasis($r['AE'] ?? null);

    $fabricLen = parseMoney($r['AF'] ?? null);
    $fabricWid = parseMoney($r['AG'] ?? null);

    $remarkParts = [];
    if ($groupCode !== '') {
        $remarkParts[] = 'Group Code: '.$groupCode;
    }
    if ($drawbackCapBasis) {
        $remarkParts[] = 'Drawback cap on: '.$drawbackCapBasis;
    }
    if ($rosctlCapBasis1) {
        $remarkParts[] = 'RoSCTL cap1 on: '.$rosctlCapBasis1;
    }
    if ($rosctlCapBasis2) {
        $remarkParts[] = 'RoSCTL cap2 on: '.$rosctlCapBasis2;
    }
    if ($rodtepCapBasis) {
        $remarkParts[] = 'RoDTEP cap on: '.$rodtepCapBasis;
    }
    if ($rosctlBasis2 && $rosctlBasis2 !== $rosctlBasis1) {
        $remarkParts[] = 'RoSCTL %2 basis: '.$rosctlBasis2;
    }

    $products[$code] = [
        'item_group_code'         => $code,
        'name'                    => $nameFromB,
        'name_on_export_document' => $e !== '' ? $e : $nameFromB,
        'barcode'                 => $barcode !== '' ? $barcode : null,
        'category_name'           => $category,
        'group_code'              => $groupCode !== '' ? $groupCode : null,
        'description'             => $additional !== '' ? $additional : null,
        'remarks'                 => $remarkParts !== [] ? implode(' | ', $remarkParts) : null,
        'comments'                => 'Source: '.basename($path).' row '.$i.' | Sheet label: '.$b,
        'price_band_code'         => $band,
        'gst_rate'                => $gst,
        'unit_po'                 => $unitPo,
        'unit_export'             => $unitExport,
        'hsn_code'                => $hsn !== '' ? $hsn : null,
        'drawback_sr_no'          => $drawbackSr !== '' ? $drawbackSr : null,
        'fabric_length_mtr'       => $fabricLen,
        'fabric_width_inch'       => $fabricWid,
        'incentives'              => [
            'drawback' => [
                'percent_1' => $drawbackPct,
                'cap_value' => $drawbackCap,
                'basis'     => $drawbackBasis,
            ],
            'rosctl' => [
                'percent_1'   => $rosctl1,
                'percent_2'   => $rosctl2,
                'cap_value'   => $rosctlCap1,
                'cap_value_2' => $rosctlCap2,
                'basis'       => $rosctlBasis1,
            ],
            'rodtep' => [
                'percent_1' => $rodtepPct,
                'cap_value' => $rodtepCap,
                'basis'     => $rodtepBasis,
            ],
        ],
        'source_row'   => $i,
        'source_label' => $b,
    ];
}

$products = array_values($products);
file_put_contents($out, json_encode([
    'generated_at' => date('c'),
    'source'       => basename($path),
    'count'        => count($products),
    'categories'   => $catCounts,
    'products'     => $products,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'parsed='.count($products)." skipped≈{$skipped}\n";
echo 'out='.$out."\n";
echo 'categories='.json_encode($catCounts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
