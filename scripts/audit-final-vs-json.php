<?php

/**
 * Audit Final 08092026.xlsx against live DB product codes + categories.
 */

require dirname(__DIR__).'/vendor/autoload.php';

$path = $argv[1] ?? 'c:/Users/Lenovo/Downloads/Final 08092026.xlsx';
$jsonPath = dirname(__DIR__).'/storage/app/product-import-08092026.json';

$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $ss->getSheetByName('Data') ?: $ss->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

function clean(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;

    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}

function deriveCode(string $b, string $c): ?string
{
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})$/u', $c, $m)) {
        return strtoupper($m[1].$m[2]);
    }
    if (preg_match('/^(\d+[A-Za-z]?)$/u', $c, $m)) {
        return strtoupper($m[1]);
    }
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\/\s*(.+)$/u', $b, $m)) {
        return strtoupper($m[1].$m[2]);
    }
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*(.+)$/u', $b, $m)) {
        return strtoupper($m[1]);
    }

    return null;
}

$sheetCodes = []; // code => [rows, labels, cats]
$unparseable = [];
$dupes = [];

foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $b = clean((string) ($r['B'] ?? ''));
    $c = clean((string) ($r['C'] ?? ''));
    $g = clean((string) ($r['G'] ?? ''));
    if ($b === '' || stripos($b, 'PRODUCT NAME') !== false) {
        continue;
    }
    $code = deriveCode($b, $c);
    if (! $code) {
        $unparseable[] = ['row' => $i, 'b' => $b, 'c' => $c];
        continue;
    }
    if (isset($sheetCodes[$code])) {
        $dupes[] = ['code' => $code, 'first_row' => $sheetCodes[$code]['rows'][0], 'dup_row' => $i, 'b' => $b];
        $sheetCodes[$code]['rows'][] = $i;
    } else {
        $sheetCodes[$code] = ['rows' => [$i], 'label' => $b, 'cat' => $g];
    }
}

$json = json_decode((string) file_get_contents($jsonPath), true);
$jsonCodes = [];
foreach ($json['products'] as $p) {
    $jsonCodes[$p['item_group_code']] = $p['category_name'];
}

echo 'sheet_product_rows='.(count($sheetCodes) + count($dupes) + count(array_filter($dupes)))."\n";
echo 'sheet_unique_codes='.count($sheetCodes)."\n";
echo 'sheet_duplicate_code_rows='.count($dupes)."\n";
echo 'unparseable='.count($unparseable)."\n";
echo 'json_codes='.count($jsonCodes)."\n";

$onlySheet = array_diff(array_keys($sheetCodes), array_keys($jsonCodes));
$onlyJson = array_diff(array_keys($jsonCodes), array_keys($sheetCodes));
echo 'in_sheet_not_json='.count($onlySheet)."\n";
echo 'in_json_not_sheet='.count($onlyJson)."\n";

if ($unparseable) {
    echo "\nUNPARSEABLE:\n".json_encode($unparseable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
}
if ($dupes) {
    echo "\nDUPES (last wins):\n".json_encode(array_slice($dupes, 0, 20), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    if (count($dupes) > 20) {
        echo '... and '.(count($dupes) - 20)." more\n";
    }
}
if ($onlySheet) {
    echo "\nMISSING FROM JSON:\n";
    foreach ($onlySheet as $code) {
        echo "  {$code} | {$sheetCodes[$code]['label']}\n";
    }
}

file_put_contents(
    dirname(__DIR__).'/storage/app/product-audit-08092026-sheet-codes.json',
    json_encode(array_keys($sheetCodes), JSON_PRETTY_PRINT)
);
echo "\nwrote sheet code list for live compare\n";
