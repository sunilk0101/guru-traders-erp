<?php

/**
 * Full coverage check: every product-like sheet row must map to a JSON product.
 */

require dirname(__DIR__).'/vendor/autoload.php';
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$path = 'c:/Users/Lenovo/Downloads/Product List - 01092026 (1).xls';
$jsonPath = dirname(__DIR__).'/storage/app/product-import-01092026.json';

$ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$rows = $ss->getSheetByName('Data')->toArray(null, true, true, true);
$json = json_decode((string) file_get_contents($jsonPath), true);
$byCode = [];
foreach ($json['products'] as $p) {
    $byCode[$p['item_group_code']] = $p;
}

function clean(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;

    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}

function deriveCode(string $b): ?array
{
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\/\s*(.+)$/u', $b, $m)) {
        return [strtoupper($m[1].$m[2]), 'band'];
    }
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*(.+)$/u', $b, $m)) {
        return [strtoupper($m[1]), 'plain'];
    }
    if (preg_match('/^(\d+[A-Za-z]?)\s*([A-Za-z]{2})\b/u', $b, $m)) {
        return [strtoupper($m[1].$m[2]), 'tight'];
    }

    return null;
}

$ok = 0;
$missing = [];
$dupes = [];
$seen = [];

foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $b = clean((string) ($r['B'] ?? ''));
    if ($b === '' || stripos($b, 'PRODUCT NAME') !== false || stripos($b, 'Item Group') !== false) {
        continue;
    }
    $derived = deriveCode($b);
    if (! $derived) {
        $missing[] = ['row' => $i, 'b' => $b, 'reason' => 'unparseable'];
        continue;
    }
    [$code] = $derived;
    if (isset($seen[$code])) {
        $dupes[] = ['row' => $i, 'code' => $code, 'first_row' => $seen[$code], 'b' => $b];
    } else {
        $seen[$code] = $i;
    }
    if (! isset($byCode[$code])) {
        $missing[] = ['row' => $i, 'b' => $b, 'code' => $code, 'reason' => 'not_in_json'];
    } else {
        $ok++;
    }
}

echo "sheet_rows_ok={$ok}\n";
echo 'sheet_dupes='.count($dupes)."\n";
echo 'missing='.count($missing)."\n";
if ($missing) {
    echo json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
}
echo 'json_count='.count($byCode)."\n";
file_put_contents(dirname(__DIR__).'/storage/app/product-import-codes.json', json_encode(array_keys($byCode)));
echo "codes_written\n";
