<?php

require dirname(__DIR__).'/vendor/autoload.php';
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$path = 'c:/Users/Lenovo/Downloads/Product List - 01092026 (1).xls';
$ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$rows = $ss->getSheetByName('Data')->toArray(null, true, true, true);

function clean(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;
    $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

    return trim($v);
}

foreach ([511, 519, 526, 527, 604] as $i) {
    $b = clean((string) ($rows[$i]['B'] ?? ''));
    echo "ROW {$i}\n";
    echo "  raw=".json_encode($rows[$i]['B'] ?? null)."\n";
    echo "  clean=".json_encode($b)."\n";
    echo "  hex=".bin2hex(substr($b, 0, 20))."\n";

    $m1 = preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\/\s*(.+)$/u', $b, $m);
    echo "  m1={$m1} ".json_encode($m)."\n";
    $m2 = preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*(.+)$/u', $b, $m);
    echo "  m2={$m2} ".json_encode($m)."\n";
    $m3 = preg_match('/^(\d+[A-Za-z]*)\s*[-–]\s*(.+)$/u', $b, $m);
    echo "  m3={$m3} ".json_encode($m)."\n";
}
