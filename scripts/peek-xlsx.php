<?php

require __DIR__.'/../vendor/autoload.php';

$path = $argv[1] ?? 'c:/Users/Lenovo/Downloads/Final 08092026.xlsx';
$s = PhpOffice\PhpSpreadsheet\IOFactory::load($path);

foreach ($s->getWorksheetIterator() as $w) {
    echo 'SHEET: '.$w->getTitle().PHP_EOL;
    $rows = $w->toArray(null, true, true, true);
    $n = 0;
    foreach ($rows as $r) {
        if ($n++ > 20) {
            break;
        }
        echo json_encode(array_values($r), JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
    echo 'total_rows='.count($rows).PHP_EOL.PHP_EOL;
}
