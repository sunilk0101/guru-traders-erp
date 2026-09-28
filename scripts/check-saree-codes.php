<?php

$c = json_decode(file_get_contents(dirname(__DIR__).'/storage/app/product-audit-08092026-sheet-codes.json'), true);
$j = json_decode(file_get_contents(dirname(__DIR__).'/storage/app/product-import-08092026.json'), true);
$codes = array_column($j['products'], 'item_group_code');

echo 'sheet_list_has_245='.(in_array('245', $c, true) ? 'yes' : 'no').PHP_EOL;
echo 'json_has_245='.(in_array('245', $codes, true) ? 'yes' : 'no').PHP_EOL;
echo 'json_count='.count($codes).PHP_EOL;

$saree = array_values(array_filter($j['products'], fn ($p) => $p['category_name'] === 'Saree'));
echo 'saree_in_json='.count($saree).PHP_EOL;
foreach (array_slice($saree, 0, 5) as $p) {
    echo '  '.$p['item_group_code'].' | '.$p['name'].PHP_EOL;
}

// Re-scan sheet B for saree-like rows
require dirname(__DIR__).'/vendor/autoload.php';
$path = 'c:/Users/Lenovo/Downloads/Final 08092026.xlsx';
$rows = PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getSheetByName('Data')->toArray(null, true, true, true);
function clean2(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;

    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}
echo "sheet saree-ish B rows:\n";
$n = 0;
foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $b = clean2($r['B'] ?? '');
    $g = clean2($r['G'] ?? '');
    if ($g === 'Saree' || stripos($b, 'SAREE') !== false) {
        $n++;
        if ($n <= 8) {
            echo "  R$i C=".clean2($r['C'] ?? '')." B=$b G=$g\n";
        }
    }
}
echo "total_sareeish=$n\n";
