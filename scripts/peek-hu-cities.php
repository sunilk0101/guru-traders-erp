<?php

$gzCandidates = [
    __DIR__.'/../storage/app/geo/cities.json.gz',
    (getenv('TEMP') ?: sys_get_temp_dir()).DIRECTORY_SEPARATOR.'json-cities.json.gz',
];

$gz = null;
foreach ($gzCandidates as $path) {
    if (is_file($path)) {
        $gz = $path;
        break;
    }
}

if (! $gz) {
    fwrite(STDERR, "cities.gz not found\n");
    exit(1);
}

echo "gz={$gz}\n";

$z = gzopen($gz, 'rb');
$buf = '';
$found = 0;
$huStates = [];

while (! gzeof($z) && $found < 12) {
    $buf .= gzread($z, 1024 * 256);

    while (($start = strpos($buf, '{')) !== false) {
        $depth = 0;
        $in = false;
        $esc = false;
        $obj = '';
        $len = strlen($buf);
        $completed = false;

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
                    $completed = true;

                    if (is_array($row) && ($row['country_code'] ?? '') === 'HU') {
                        $huStates[$row['state_name'] ?? ''] = ($huStates[$row['state_name'] ?? ''] ?? 0) + 1;

                        $name = $row['name'] ?? '';
                        $stateName = $row['state_name'] ?? '';
                        if (
                            stripos($name, 'Kapos') !== false
                            || stripos($stateName, 'Kapos') !== false
                            || stripos($stateName, 'Somogy') !== false
                        ) {
                            echo json_encode([
                                'city' => $name,
                                'state_name' => $stateName,
                                'state_code' => $row['state_code'] ?? null,
                                'state_id' => $row['state_id'] ?? null,
                            ], JSON_UNESCAPED_UNICODE), PHP_EOL;
                            $found++;
                        }
                    }
                    break;
                }
                continue;
            }

            $obj .= $ch;
        }

        if (! $completed) {
            $buf = substr($buf, $start);
            break;
        }
    }

    if (strlen($buf) > 1024 * 1024) {
        $buf = substr($buf, -8000);
    }
}

gzclose($z);
arsort($huStates);
echo "top HU state_names:\n";
foreach (array_slice($huStates, 0, 25, true) as $k => $v) {
    echo "{$k}={$v}\n";
}
