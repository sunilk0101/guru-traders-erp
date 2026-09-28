<?php

require dirname(__DIR__).'/vendor/autoload.php';

$path = 'c:/Users/Lenovo/Downloads/Final 08092026.xlsx';
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$rows = $ss->getSheetByName('Data')->toArray(null, true, true, true);

function clean(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;

    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}

$codes = [];
foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $b = clean((string) ($r['B'] ?? ''));
    if ($b === '' || stripos($b, 'PRODUCT NAME') !== false) {
        continue;
    }
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\//u', $b, $m)) {
        $codes[strtoupper($m[1].$m[2])] = $b;
    } elseif (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*/u', $b, $m)) {
        $codes[strtoupper($m[1])] = $b;
    }
}

file_put_contents(
    dirname(__DIR__).'/storage/app/product-audit-08092026-sheet-codes.json',
    json_encode(array_keys($codes), JSON_PRETTY_PRINT)
);
echo 'unique_B_codes='.count($codes).PHP_EOL;
