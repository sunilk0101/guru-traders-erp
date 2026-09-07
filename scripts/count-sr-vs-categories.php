<?php

require dirname(__DIR__).'/vendor/autoload.php';

$path = 'c:/Users/Lenovo/Downloads/Product List - 01092026 (1).xls';
if (! file_exists($path)) {
    echo "NO_XLS\n";
    exit(1);
}

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
$ss = $reader->load($path);
$sheet = $ss->getSheetByName('Data') ?: $ss->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

$clean = function ($v) {
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;
    $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

    return trim($v);
};

$sr = [];
$banners = [];
$bannerOrder = [];

foreach ($rows as $i => $r) {
    $a = $clean($r['A'] ?? '');
    $b = $clean($r['B'] ?? '');

    if (preg_match('/^SR\s*\.?\s*NO/i', $a)) {
        $sr[] = $a;
    }

    // Same rules as parse-product-xls.php
    if ($a !== '' && ! preg_match('/^SR\s*\.?\s*NO/i', $a) && $b === '') {
        $banners[$a] = ($banners[$a] ?? 0) + 1;
        $bannerOrder[] = $a;
    } elseif ($a !== '' && ! preg_match('/^SR\s*\.?\s*NO/i', $a) && stripos($a, 'LADIES') !== false && strlen($a) > 20) {
        $banners[$a] = ($banners[$a] ?? 0) + 1;
        $bannerOrder[] = $a;
    }
}

echo 'SR_NO_rows='.count($sr).PHP_EOL;
echo 'unique_category_names='.count($banners).PHP_EOL;
echo 'banner_hits_total='.array_sum($banners).PHP_EOL;
echo 'first_sr='.($sr[0] ?? '').' last_sr='.($sr[count($sr) - 1] ?? '').PHP_EOL;

$dupes = array_filter($banners, fn ($c) => $c > 1);
echo 'repeated_names='.count($dupes).PHP_EOL;
foreach ($dupes as $name => $count) {
    echo "  x{$count}: {$name}\n";
}
