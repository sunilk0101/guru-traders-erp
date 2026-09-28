<?php

$sheet = json_decode(file_get_contents(dirname(__DIR__).'/storage/app/product-audit-08092026-sheet-codes.json'), true);
$j = json_decode(file_get_contents(dirname(__DIR__).'/storage/app/product-import-08092026.json'), true);
$json = array_column($j['products'], 'item_group_code');

$onlySheet = array_values(array_diff($sheet, $json));
$onlyJson = array_values(array_diff($json, $sheet));

echo 'sheet='.count($sheet).' json='.count($json).PHP_EOL;
echo 'only_sheet='.count($onlySheet).' '.json_encode($onlySheet).PHP_EOL;
echo 'only_json='.count($onlyJson).' '.json_encode(array_slice($onlyJson, 0, 40)).PHP_EOL;

// Debug first saree B row match
require dirname(__DIR__).'/vendor/autoload.php';
$b = '245- POLY. SILK JACQUARD BUTTA SAREE';
var_export(preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\//u', $b, $m1));
echo ' m1='.json_encode($m1 ?? null).PHP_EOL;
var_export(preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*/u', $b, $m2));
echo ' m2='.json_encode($m2 ?? null).PHP_EOL;

// hex of dash in actual sheet cell
$rows = PhpOffice\PhpSpreadsheet\IOFactory::load('c:/Users/Lenovo/Downloads/Final 08092026.xlsx')
    ->getSheetByName('Data')->toArray(null, true, true, true);
$raw = (string) ($rows[511]['B'] ?? '');
echo 'rawB='.json_encode($raw).PHP_EOL;
for ($i = 0; $i < min(strlen($raw), 10); $i++) {
    echo ord($raw[$i]).' ';
}
echo PHP_EOL;
