<?php

require dirname(__DIR__).'/vendor/autoload.php';

$path = 'c:/Users/Lenovo/Downloads/Product List - 01092026 (1).xls';
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
// Need formatted/cached values — ReadDataOnly blanks many header cells in this file.
$ss = $reader->load($path);
$sheet = $ss->getSheetByName('Data') ?: $ss->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $i) {
    $r = $rows[$i] ?? [];
    $parts = [];
    foreach ($r as $col => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $parts[] = $col.'='.substr(preg_replace('/\s+/u', ' ', (string) $v), 0, 80);
    }
    echo "R{$i}: ".implode(' || ', $parts)."\n\n";
}

// Find a row with fabric AE/AF
foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $ae = $r['AE'] ?? null;
    $af = $r['AF'] ?? null;
    if (($ae !== null && $ae !== '' && $ae !== '#N/A') || ($af !== null && $af !== '' && $af !== '#N/A')) {
        echo "fabric_row={$i}\n";
        foreach ($r as $col => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            echo "  {$col}=".json_encode($v, JSON_UNESCAPED_UNICODE)."\n";
        }
        break;
    }
}

// Find a row with rodtep AA
foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $aa = $r['AA'] ?? null;
    if ($aa !== null && $aa !== '' && $aa !== '#N/A' && $aa !== 0 && $aa !== '0') {
        echo "rodtep_row={$i} AA=".json_encode($aa)." AC=".json_encode($r['AC'] ?? null)." AD=".json_encode($r['AD'] ?? null)."\n";
        break;
    }
}

// GST non-null
$gstHits = 0;
foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $j = $r['J'] ?? null;
    if ($j !== null && $j !== '' && strtoupper((string) $j) !== '#N/A') {
        $gstHits++;
        if ($gstHits <= 3) {
            echo "gst_row={$i} J=".json_encode($j)." B=".json_encode($r['B'] ?? null)."\n";
        }
    }
}
echo "gst_non_empty={$gstHits}\n";

// Basis columns sample from row 7
$r7 = $rows[7] ?? [];
foreach (['O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF'] as $c) {
    echo "R7 {$c}=".json_encode($r7[$c] ?? null, JSON_UNESCAPED_UNICODE)."\n";
}
