<?php

require dirname(__DIR__).'/vendor/autoload.php';
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$path = 'c:/Users/Lenovo/Downloads/Product List - 01092026 (1).xls';
$ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$rows = $ss->getSheetByName('Data')->toArray(null, true, true, true);

function clean($v): string
{
    return trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
}

foreach ([15, 16, 17, 140, 141, 511, 519, 528, 604, 605] as $i) {
    $r = $rows[$i] ?? [];
    echo "R{$i} B=".json_encode($r['B'] ?? null).' D='.json_encode($r['D'] ?? null).' G='.json_encode($r['G'] ?? null).PHP_EOL;
}

$map = [];
foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $b = clean($r['B'] ?? '');
    if (preg_match('/^(\d+)\s*[-–]\s*([A-Za-z]{2})\s*\//u', $b, $m)) {
        $c = $m[1].strtoupper($m[2]);
        $map[$c][] = $i;
    }
}
foreach ($map as $c => $list) {
    if (count($list) > 1) {
        echo "DUPE {$c} rows=".implode(',', $list).PHP_EOL;
        foreach ($list as $i) {
            echo '  '.json_encode($rows[$i]['B']).PHP_EOL;
        }
    }
}
