<?php

require dirname(__DIR__).'/vendor/autoload.php';

$path = 'c:/Users/Lenovo/Downloads/Product List - 01092026 (1).xls';
if (! file_exists($path)) {
    fwrite(STDERR, "File not found: {$path}\n");
    exit(1);
}

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
$ss = $reader->load($path);
$sheet = $ss->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

echo 'sheet='.$sheet->getTitle()."\n";
echo 'rows='.count($rows)."\n";
foreach (array_slice($rows, 0, 20, true) as $i => $r) {
    echo 'R'.$i.': '.json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}
