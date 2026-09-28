<?php

require dirname(__DIR__).'/vendor/autoload.php';

$path = $argv[1] ?? 'c:/Users/Lenovo/Downloads/Final 08092026.xlsx';
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $ss->getSheetByName('Data') ?: $ss->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

function clean(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;

    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}

$cats = [];
$sectionHeaders = [];
$productRows = 0;
$currentSection = null;

foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $a = clean((string) ($r['A'] ?? ''));
    $b = clean((string) ($r['B'] ?? ''));
    $g = clean((string) ($r['G'] ?? '')); // Category
    $c = clean((string) ($r['C'] ?? ''));

    // Section header rows often have text in A without product codes
    if ($a !== '' && $b === '' && $c === '') {
        $sectionHeaders[$a] = ($sectionHeaders[$a] ?? 0) + 1;
        $currentSection = $a;
        continue;
    }
    if ($a !== '' && ! preg_match('/^\d/', $a) && stripos($b, '/') === false && $c === '') {
        $sectionHeaders[$a] = ($sectionHeaders[$a] ?? 0) + 1;
        $currentSection = $a;
    }

    if ($b === '' || stripos($b, 'PRODUCT NAME') !== false) {
        continue;
    }

    $productRows++;
    $key = $g !== '' ? $g : '(blank)';
    if (! isset($cats[$key])) {
        $cats[$key] = ['count' => 0, 'sections' => [], 'samples' => []];
    }
    $cats[$key]['count']++;
    if ($currentSection) {
        $cats[$key]['sections'][$currentSection] = ($cats[$key]['sections'][$currentSection] ?? 0) + 1;
    }
    if (count($cats[$key]['samples']) < 3) {
        $cats[$key]['samples'][] = $b;
    }
}

arsort($cats);
echo "product_rows={$productRows}\n";
echo "unique_categories=".count($cats)."\n\n";
foreach ($cats as $name => $info) {
    echo "CAT: {$name} count={$info['count']}\n";
    echo '  sections='.json_encode($info['sections'], JSON_UNESCAPED_UNICODE)."\n";
    echo '  samples='.json_encode($info['samples'], JSON_UNESCAPED_UNICODE)."\n";
}

echo "\nsection_headers:\n";
foreach ($sectionHeaders as $h => $n) {
    echo "  [{$n}] {$h}\n";
}
