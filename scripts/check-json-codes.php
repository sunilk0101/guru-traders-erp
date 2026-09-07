<?php

$j = json_decode(file_get_contents(dirname(__DIR__).'/storage/app/product-import-01092026.json'), true);
$codes = array_column($j['products'], 'item_group_code');
foreach (['245', '252D', '260B', '370AAA', '370AAB', '001AA'] as $c) {
    echo $c.'='.(in_array($c, $codes, true) ? 'YES' : 'NO').PHP_EOL;
}
echo 'count='.count($codes).PHP_EOL;
foreach ($j['products'] as $p) {
    $label = $p['source_label'] ?? '';
    if (stripos($label, 'SAREE') !== false || stripos($label, '370A') !== false) {
        echo $p['item_group_code'].' => '.$label.PHP_EOL;
    }
}
