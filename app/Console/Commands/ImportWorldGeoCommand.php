<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Imports worldwide states and cities so Country → State → City dropdowns
 * have real options for every ISO country we already store.
 *
 * Source: dr5hn/countries-states-cities-database (ODbL — attribution retained
 * in the data folder README note). Idempotent via firstOrCreate on natural keys.
 */
class ImportWorldGeoCommand extends Command
{
    protected $signature = 'geo:import-world
                            {--states-only : Skip cities (faster)}
                            {--force-download : Re-download source JSON files}';

    protected $description = 'Seed worldwide state/city dropdowns from the CSC open dataset';

    private string $dataDir;

    public function handle(): int
    {
        $this->dataDir = storage_path('app/geo');
        if (! is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $statesPath = $this->ensureStatesJson();
        $this->importStates($statesPath);

        if (! $this->option('states-only')) {
            $citiesPath = $this->ensureCitiesJson();
            $this->importCities($citiesPath);
        }

        $this->info('Done. states='.State::count().' cities='.City::count());

        return self::SUCCESS;
    }

    private function ensureStatesJson(): string
    {
        $bundled = database_path('seeders/data/world-states.json');
        $cached = $this->dataDir.'/states.json';

        if (is_file($bundled) && ! $this->option('force-download')) {
            return $bundled;
        }

        if (is_file($cached) && ! $this->option('force-download')) {
            return $cached;
        }

        $this->info('Downloading world states…');
        $response = Http::timeout(120)->get(
            'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/states.json'
        );
        $response->throw();
        file_put_contents($cached, $response->body());

        return $cached;
    }

    private function ensureCitiesJson(): string
    {
        $cached = $this->dataDir.'/cities.json';
        if (is_file($cached) && ! $this->option('force-download')) {
            return $cached;
        }

        $gz = $this->dataDir.'/cities.json.gz';
        $this->info('Downloading world cities (gzip)…');
        $response = Http::timeout(300)->withOptions(['sink' => $gz])->get(
            'https://github.com/dr5hn/countries-states-cities-database/releases/latest/download/json-cities.json.gz'
        );
        $response->throw();

        $this->info('Decompressing cities…');
        $in = gzopen($gz, 'rb');
        $out = fopen($cached, 'wb');
        while (! gzeof($in)) {
            fwrite($out, gzread($in, 1024 * 512));
        }
        gzclose($in);
        fclose($out);

        return $cached;
    }

    private function importStates(string $path): void
    {
        $this->info('Importing states from '.$path);
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $countries = Country::query()->pluck('id', 'iso_code');

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $created = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $countries, &$created, $bar) {
                foreach ($chunk as $row) {
                    $iso = strtoupper((string) ($row['country_code'] ?? ''));
                    $countryId = $countries[$iso] ?? null;
                    $name = trim((string) ($row['name'] ?? ''));
                    if (! $countryId || $name === '') {
                        $bar->advance();
                        continue;
                    }

                    $state = State::query()->firstOrCreate(
                        ['country_id' => $countryId, 'name' => $name],
                        [
                            'code'   => $row['iso2'] ?? $row['state_code'] ?? null,
                            'status' => 'active',
                        ]
                    );
                    if ($state->wasRecentlyCreated) {
                        $created++;
                    }
                    $bar->advance();
                }
            });
        }

        $bar->finish();
        $this->newLine();
        $this->info("States created/seen. newly_created={$created}");
    }

    private function importCities(string $path): void
    {
        $this->info('Importing cities from '.$path.' (streaming)…');

        // Map (country_iso|state_name) and (country_iso|state_code) → our state id
        $byName = [];
        $byCode = [];
        State::query()
            ->join('countries', 'countries.id', '=', 'states.country_id')
            ->get(['states.id', 'states.name', 'states.code', 'countries.iso_code'])
            ->each(function ($row) use (&$byName, &$byCode) {
                $iso = strtoupper($row->iso_code);
                $byName[$iso.'|'.mb_strtolower($row->name)] = (int) $row->id;
                if ($row->code) {
                    $byCode[$iso.'|'.strtoupper($row->code)] = (int) $row->id;
                }
            });

        $handle = fopen($path, 'rb');
        if (! $handle) {
            $this->error('Cannot open cities file');

            return;
        }

        // cities.json is a top-level array — read with a simple buffer parser
        // for memory. For reliability on shared hosts we decode in chunks via
        // regex-free streaming of objects.
        $buffer = '';
        $depth = 0;
        $inString = false;
        $escape = false;
        $object = '';
        $created = 0;
        $seen = 0;
        $batch = [];

        $flush = function () use (&$batch, &$created) {
            if (! $batch) {
                return;
            }
            DB::transaction(function () use (&$batch, &$created) {
                foreach ($batch as [$stateId, $name]) {
                    $city = City::query()->firstOrCreate(
                        ['state_id' => $stateId, 'name' => $name],
                        ['status' => 'active']
                    );
                    if ($city->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });
            $batch = [];
        };

        while (! feof($handle)) {
            $chunk = fread($handle, 1024 * 256);
            if ($chunk === false) {
                break;
            }
            $len = strlen($chunk);
            for ($i = 0; $i < $len; $i++) {
                $ch = $chunk[$i];

                if ($inString) {
                    $object .= $ch;
                    if ($escape) {
                        $escape = false;
                    } elseif ($ch === '\\') {
                        $escape = true;
                    } elseif ($ch === '"') {
                        $inString = false;
                    }
                    continue;
                }

                if ($ch === '"') {
                    $inString = true;
                    if ($depth >= 1) {
                        $object .= $ch;
                    }
                    continue;
                }

                if ($ch === '{') {
                    $depth++;
                    if ($depth === 1) {
                        $object = '{';
                    } else {
                        $object .= $ch;
                    }
                    continue;
                }

                if ($ch === '}') {
                    $object .= $ch;
                    $depth--;
                    if ($depth === 0) {
                        $row = json_decode($object, true);
                        $object = '';
                        if (! is_array($row)) {
                            continue;
                        }
                        $seen++;
                        $iso = strtoupper((string) ($row['country_code'] ?? ''));
                        $name = trim((string) ($row['name'] ?? ''));
                        if ($iso === '' || $name === '') {
                            continue;
                        }
                        $stateId = null;
                        $stateName = trim((string) ($row['state_name'] ?? ''));
                        $stateCode = strtoupper((string) ($row['state_code'] ?? ''));
                        if ($stateName !== '') {
                            $stateId = $byName[$iso.'|'.mb_strtolower($stateName)] ?? null;
                        }
                        if (! $stateId && $stateCode !== '') {
                            $stateId = $byCode[$iso.'|'.$stateCode] ?? null;
                        }
                        if (! $stateId) {
                            continue;
                        }
                        $batch[] = [$stateId, $name];
                        if (count($batch) >= 300) {
                            $flush();
                            if ($seen % 5000 === 0) {
                                $this->line("… cities scanned={$seen} created={$created}");
                            }
                        }
                    }
                    continue;
                }

                if ($depth >= 1) {
                    $object .= $ch;
                }
            }
        }

        $flush();
        fclose($handle);
        $this->info("Cities scanned={$seen} newly_created={$created}");
    }
}
