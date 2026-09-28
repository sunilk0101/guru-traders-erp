<?php

$s = json_decode(file_get_contents(__DIR__.'/../database/seeders/data/world-states.json'), true);
foreach ($s as $r) {
    if (($r['country_code'] ?? '') === 'MH') {
        echo $r['id'].'|'.$r['name'].'|'.$r['type'].'|parent='.($r['parent_id'] ?? 'null').PHP_EOL;
    }
}

// How often is the only city same-named as state worldwide?
echo "---\n";
$types = [];
foreach ($s as $r) {
    $t = strtolower(trim((string) ($r['type'] ?? '')));
    $types[$t] = ($types[$t] ?? 0) + 1;
}
arsort($types);
echo "top types:\n";
$i = 0;
foreach ($types as $t => $n) {
    echo ($t === '' ? '(empty)' : $t)."=$n\n";
    if (++$i >= 40) {
        break;
    }
}
