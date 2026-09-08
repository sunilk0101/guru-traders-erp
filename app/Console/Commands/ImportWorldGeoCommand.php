<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Imports worldwide states and cities so Country → State → City dropdowns
 * have real options for every ISO country we already store.
 *
 * Source: dr5hn/countries-states-cities-database (ODbL).
 *
 * City-level CSC "states" (e.g. Hungary "city with county rights" like
 * Kaposvár) are skipped so they appear only as cities under their county.
 */
class ImportWorldGeoCommand extends Command
{
    protected $signature = 'geo:import-world
                            {--states-only : Skip cities (faster)}
                            {--rebuild : Wipe existing states/cities first (nulls buyer/supplier FKs)}
                            {--force-download : Re-download source JSON files}';

    protected $description = 'Seed worldwide state/city dropdowns from the CSC open dataset';

    private string $dataDir;

    /** @var array<int, int> CSC state id => our states.id */
    private array $cscStateMap = [];

    public function handle(): int
    {
        $this->dataDir = storage_path('app/geo');
        if (! is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        if ($this->option('rebuild')) {
            $this->rebuildTables();
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

    private function rebuildTables(): void
    {
        $this->warn('Rebuilding geo tables — clearing state/city FKs on masters…');

        DB::transaction(function () {
            foreach (['buyers', 'suppliers'] as $table) {
                if (Schema::hasTable($table)) {
                    if (Schema::hasColumn($table, 'city_id')) {
                        DB::table($table)->update(['city_id' => null]);
                    }
                    if (Schema::hasColumn($table, 'state_id')) {
                        DB::table($table)->update(['state_id' => null]);
                    }
                }
            }

            City::query()->delete();
            State::query()->delete();
        });

        $this->cscStateMap = [];
        $this->info('Cleared states/cities.');
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

    /**
     * Skip place-level CSC rows that belong in the City dropdown, not State.
     */
    private function isAdminState(?string $type): bool
    {
        $type = strtolower(trim((string) $type));
        if ($type === '') {
            return true;
        }

        $excluded = [
            'city with county rights',
            'city municipality',
            'special self-governing city',
            'special city',
            'state city',
            'metropolitan city',
            'autonomous city',
            'city',
            'town',
            'town council',
            'village',
            'commune',
            'quarter',
            'borough',
            'ward',
            'urban community',
        ];

        foreach ($excluded as $bad) {
            if ($type === $bad) {
                return false;
            }
        }

        return true;
    }

    private function importStates(string $path): void
    {
        $this->info('Importing admin states from '.$path);
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $countries = Country::query()->pluck('id', 'iso_code');

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $created = 0;
        $skipped = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $countries, &$created, &$skipped, $bar) {
                foreach ($chunk as $row) {
                    $iso = strtoupper((string) ($row['country_code'] ?? ''));
                    $countryId = $countries[$iso] ?? null;
                    $name = trim((string) ($row['name'] ?? ''));
                    $cscId = (int) ($row['id'] ?? 0);

                    if (! $countryId || $name === '' || $cscId < 1) {
                        $bar->advance();
                        continue;
                    }

                    if (! $this->isAdminState($row['type'] ?? null)) {
                        $skipped++;
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

                    $this->cscStateMap[$cscId] = (int) $state->id;

                    if ($state->wasRecentlyCreated) {
                        $created++;
                    }

                    $bar->advance();
                }
            });
        }

        $bar->finish();
        $this->newLine();
        $this->info("States newly_created={$created} city-level_skipped={$skipped} mapped=".count($this->cscStateMap));
    }

    private function importCities(string $path): void
    {
        $this->info('Importing cities from '.$path.' (by CSC state id)…');

        // Fallback maps when a city's CSC state was a skipped city-level row:
        // match county/state by country+name / country+code instead.
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

        $buffer = '';
        $depth = 0;
        $inString = false;
        $escape = false;
        $object = '';
        $created = 0;
        $seen = 0;
        $unmapped = 0;
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

                        $cscStateId = (int) ($row['state_id'] ?? 0);
                        $stateId = $this->cscStateMap[$cscStateId] ?? null;

                        if (! $stateId) {
                            $stateName = trim((string) ($row['state_name'] ?? ''));
                            $stateCode = strtoupper((string) ($row['state_code'] ?? ''));
                            if ($stateName !== '') {
                                $stateId = $byName[$iso.'|'.mb_strtolower($stateName)] ?? null;
                            }
                            if (! $stateId && $stateCode !== '') {
                                $stateId = $byCode[$iso.'|'.$stateCode] ?? null;
                            }
                        }

                        if (! $stateId) {
                            $unmapped++;
                            continue;
                        }

                        $batch[] = [$stateId, $name];
                        if (count($batch) >= 300) {
                            $flush();
                            if ($seen % 20000 === 0) {
                                $this->line("… cities scanned={$seen} created={$created} unmapped={$unmapped}");
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
        $this->info("Cities scanned={$seen} newly_created={$created} unmapped={$unmapped}");
    }
}
