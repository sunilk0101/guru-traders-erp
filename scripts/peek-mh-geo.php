<?php

$gz = __DIR__.'/../storage/app/geo/cities.json.gz';
$statesPath = __DIR__.'/../database/seeders/data/world-states.json';
if (! is_file($statesPath)) {
    $statesPath = __DIR__.'/../storage/app/geo/states.json';
}

$states = json_decode(file_get_contents($statesPath), true);
$mhStates = [];
foreach ($states as $r) {
    if (($r['country_code'] ?? '') === 'MH') {
        $mhStates[$r['id']] = $r['name'].' ['.($r['type'] ?? '').']';
    }
}
echo "MH CSC states=".count($mhStates)."\n";
foreach ($mhStates as $id => $label) {
    echo "  $id $label\n";
}

$z = gzopen($gz, 'rb');
$buf = '';
$byState = [];
while (! gzeof($z)) {
    $buf .= gzread($z, 1024 * 512);
    while (($start = strpos($buf, '{')) !== false) {
        $depth = 0;
        $in = false;
        $esc = false;
        $obj = '';
        $len = strlen($buf);
        $done = false;
        for ($i = $start; $i < $len; $i++) {
            $ch = $buf[$i];
            if ($in) {
                $obj .= $ch;
                if ($esc) {
                    $esc = false;
                } elseif ($ch === '\\') {
                    $esc = true;
                } elseif ($ch === '"') {
                    $in = false;
                }
                continue;
            }
            if ($ch === '"') {
                $in = true;
                $obj .= $ch;
                continue;
            }
            if ($ch === '{') {
                $depth++;
                $obj .= $ch;
                continue;
            }
            if ($ch === '}') {
                $obj .= $ch;
                $depth--;
                if ($depth === 0) {
                    $row = json_decode($obj, true);
                    $buf = substr($buf, $i + 1);
                    $done = true;
                    if (is_array($row) && ($row['country_code'] ?? '') === 'MH') {
                        $sid = (int) ($row['state_id'] ?? 0);
                        $byState[$sid][] = $row['name'] ?? '';
                    }
                    break;
                }
                continue;
            }
            $obj .= $ch;
        }
        if (! $done) {
            break;
        }
    }
    if (strlen($buf) > 2_000_000) {
        $buf = substr($buf, -100000);
    }
}
gzclose($z);

foreach ($mhStates as $id => $label) {
    $cities = $byState[$id] ?? [];
    echo "$label cities=".count($cities).' => '.implode(', ', array_slice($cities, 0, 10))."\n";
}
