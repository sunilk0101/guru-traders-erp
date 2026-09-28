<?php

require dirname(__DIR__).'/vendor/autoload.php';

$path = 'c:/Users/Lenovo/Downloads/Final 08092026.xlsx';
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$rows = $ss->getSheetByName('Data')->toArray(null, true, true, true);

function clean(?string $v): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;

    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}

$checkRows = [19, 20, 21, 22, 313, 370, 371, 438, 439];
foreach ($checkRows as $i) {
    $r = $rows[$i] ?? null;
    if (! $r) {
        echo "missing row $i\n";
        continue;
    }
    echo "R$i A=".clean($r['A'] ?? '')." | B=".clean($r['B'] ?? '')." | C=".clean($r['C'] ?? '')." | D=".clean($r['D'] ?? '')." | G=".clean($r['G'] ?? '')."\n";
}

echo "\n--- rows where B code != C code ---\n";
$mismatch = 0;
foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }
    $b = clean((string) ($r['B'] ?? ''));
    $c = clean((string) ($r['C'] ?? ''));
    if ($b === '') {
        continue;
    }
    $codeB = null;
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\//u', $b, $m)) {
        $codeB = strtoupper($m[1].$m[2]);
    } elseif (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*/u', $b, $m)) {
        $codeB = strtoupper($m[1]);
    }
    $codeC = null;
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})$/u', $c, $m)) {
        $codeC = strtoupper($m[1].$m[2]);
    } elseif (preg_match('/^(\d+[A-Za-z]?)$/u', $c, $m)) {
        $codeC = strtoupper($m[1]);
    }
    if ($codeB && $codeC && $codeB !== $codeC) {
        $mismatch++;
        if ($mismatch <= 30) {
            echo "R$i Bcode=$codeB Ccode=$codeC | B=$b | C=$c\n";
        }
    }
}
echo "total_mismatches=$mismatch\n";
