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
 * Rules that keep State ≠ City:
 * - Skip place-level CSC "states" (city with county rights, town, …).
 * - Prefer top-level admin rows; child municipalities (parent_id set) become
 *   cities under their parent (e.g. MH Utrik → city under Ratak).
 * - Never insert a city whose name matches its parent state name.
 * - Empty leftover states are folded into a per-country "Regions" state.
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

    /** @var array<int, int> Child CSC state id => our parent states.id */
    private array $childCscToStateId = [];

    /** @var array<int, string> Our state id => name */
    private array $ourStateNames = [];

    /** @var array<int, array<string, mixed>> CSC id => row */
    private array $cscRowsById = [];

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
        $this->promoteChildDivisionsAsCities();

        if (! $this->option('states-only')) {
            $citiesPath = $this->ensureCitiesJson();
            $this->importCities($citiesPath);
        }

        $this->rescueEmptyStates();
        $this->pruneEmptyStates();
        $this->dropSameNameCities();

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
        $this->childCscToStateId = [];
        $this->ourStateNames = [];
        $this->cscRowsById = [];
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

        return ! in_array($type, $excluded, true);
    }

    /**
     * Walk CSC parent_id until a top-level admin row (no admin parent).
     */
    private function topAdminCscId(int $cscId): ?int
    {
        $guard = 0;
        $current = $cscId;

        while ($guard++ < 20) {
            $row = $this->cscRowsById[$current] ?? null;
            if (! $row || ! $this->isAdminState($row['type'] ?? null)) {
                return null;
            }

            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId < 1 || ! isset($this->cscRowsById[$parentId])) {
                return $current;
            }

            $parent = $this->cscRowsById[$parentId];
            if (! $this->isAdminState($parent['type'] ?? null)) {
                return $current;
            }

            $current = $parentId;
        }

        return $cscId;
    }

    private function importStates(string $path): void
    {
        $this->info('Importing top-level admin states from '.$path);
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $countries = Country::query()->pluck('id', 'iso_code');

        $this->cscRowsById = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $this->cscRowsById[$id] = $row;
            }
        }

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $created = 0;
        $skippedType = 0;
        $skippedChild = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $countries, &$created, &$skippedType, &$skippedChild, $bar) {
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
                        $skippedType++;
                        $bar->advance();
                        continue;
                    }

                    $topId = $this->topAdminCscId($cscId);
                    if ($topId === null) {
                        $skippedType++;
                        $bar->advance();
                        continue;
                    }

                    // Child division → map onto parent state later; do not create State.
                    if ($topId !== $cscId) {
                        $skippedChild++;
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
                    $this->ourStateNames[(int) $state->id] = $state->name;

                    if ($state->wasRecentlyCreated) {
                        $created++;
                    }

                    $bar->advance();
                }
            });
        }

        // Map every child admin CSC id to the imported top-level state.
        foreach ($this->cscRowsById as $cscId => $row) {
            if (isset($this->cscStateMap[$cscId])) {
                continue;
            }
            if (! $this->isAdminState($row['type'] ?? null)) {
                continue;
            }
            $topId = $this->topAdminCscId($cscId);
            if ($topId && isset($this->cscStateMap[$topId])) {
                $this->childCscToStateId[$cscId] = $this->cscStateMap[$topId];
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info(
            "States newly_created={$created} type_skipped={$skippedType} child_skipped={$skippedChild} ".
            'mapped='.count($this->cscStateMap).' child_mapped='.count($this->childCscToStateId)
        );
    }

    /**
     * Turn skipped child municipalities into cities under their parent state.
     */
    private function promoteChildDivisionsAsCities(): void
    {
        $created = 0;
        $skippedSame = 0;

        foreach ($this->childCscToStateId as $cscId => $stateId) {
            $row = $this->cscRowsById[$cscId] ?? null;
            if (! $row) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $stateName = $this->ourStateNames[$stateId] ?? State::query()->find($stateId)?->name;
            if ($stateName && mb_strtolower($name) === mb_strtolower($stateName)) {
                $skippedSame++;
                continue;
            }

            $city = City::query()->firstOrCreate(
                ['state_id' => $stateId, 'name' => $name],
                ['status' => 'active']
            );
            if ($city->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Promoted child divisions as cities created={$created} same-name_skipped={$skippedSame}");
    }

    private function resolveOurStateId(int $cscStateId, string $iso, string $stateName, string $stateCode, array $byName, array $byCode): ?int
    {
        if (isset($this->cscStateMap[$cscStateId])) {
            return $this->cscStateMap[$cscStateId];
        }
        if (isset($this->childCscToStateId[$cscStateId])) {
            return $this->childCscToStateId[$cscStateId];
        }
        if ($stateName !== '') {
            $id = $byName[$iso.'|'.mb_strtolower($stateName)] ?? null;
            if ($id) {
                return $id;
            }
        }
        if ($stateCode !== '') {
            return $byCode[$iso.'|'.$stateCode] ?? null;
        }

        return null;
    }

    private function importCities(string $path): void
    {
        $this->info('Importing cities from '.$path.' (by CSC state id)…');

        $byName = [];
        $byCode = [];
        State::query()
            ->join('countries', 'countries.id', '=', 'states.country_id')
            ->get(['states.id', 'states.name', 'states.code', 'countries.iso_code'])
            ->each(function ($row) use (&$byName, &$byCode) {
                $iso = strtoupper($row->iso_code);
                $byName[$iso.'|'.mb_strtolower($row->name)] = (int) $row->id;
                $this->ourStateNames[(int) $row->id] = $row->name;
                if ($row->code) {
                    $byCode[$iso.'|'.strtoupper($row->code)] = (int) $row->id;
                }
            });

        $handle = fopen($path, 'rb');
        if (! $handle) {
            $this->error('Cannot open cities file');

            return;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $object = '';
        $created = 0;
        $seen = 0;
        $unmapped = 0;
        $skippedSame = 0;
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
                        $stateId = $this->resolveOurStateId(
                            $cscStateId,
                            $iso,
                            trim((string) ($row['state_name'] ?? '')),
                            strtoupper((string) ($row['state_code'] ?? '')),
                            $byName,
                            $byCode
                        );

                        if (! $stateId) {
                            $unmapped++;
                            continue;
                        }

                        $stateName = $this->ourStateNames[$stateId] ?? '';
                        if ($stateName !== '' && mb_strtolower($name) === mb_strtolower($stateName)) {
                            $skippedSame++;
                            continue;
                        }

                        $batch[] = [$stateId, $name];
                        if (count($batch) >= 300) {
                            $flush();
                            if ($seen % 20000 === 0) {
                                $this->line("… cities scanned={$seen} created={$created} same_skipped={$skippedSame} unmapped={$unmapped}");
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
        $this->info("Cities scanned={$seen} newly_created={$created} same-name_skipped={$skippedSame} unmapped={$unmapped}");
    }

    /**
     * Fold states that still have no cities into a per-country "Regions" bucket
     * so places remain selectable without State === City.
     */
    private function rescueEmptyStates(): void
    {
        $empties = State::query()
            ->whereDoesntHave('cities')
            ->orderBy('country_id')
            ->orderBy('name')
            ->get(['id', 'country_id', 'name']);

        if ($empties->isEmpty()) {
            return;
        }

        $moved = 0;
        foreach ($empties->groupBy('country_id') as $countryId => $states) {
            $bucket = State::query()->firstOrCreate(
                ['country_id' => (int) $countryId, 'name' => 'Regions'],
                ['status' => 'active']
            );
            $this->ourStateNames[(int) $bucket->id] = $bucket->name;

            foreach ($states as $st) {
                if ((int) $st->id === (int) $bucket->id) {
                    continue;
                }
                if (mb_strtolower($st->name) === mb_strtolower($bucket->name)) {
                    $st->delete();
                    continue;
                }
                City::query()->firstOrCreate(
                    ['state_id' => $bucket->id, 'name' => $st->name],
                    ['status' => 'active']
                );
                $st->delete();
                $moved++;
            }
        }

        $this->info("Rescued empty states into Regions cities={$moved}");
    }

    private function pruneEmptyStates(): void
    {
        $ids = State::query()
            ->whereDoesntHave('cities')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $count = $ids->count();
        State::query()->whereIn('id', $ids)->delete();
        $this->info("Pruned empty states={$count}");
    }

    /**
     * Safety net: remove any remaining city that duplicates its state name.
     */
    private function dropSameNameCities(): void
    {
        $deleted = DB::affectingStatement(
            'DELETE FROM cities WHERE EXISTS (
                SELECT 1 FROM states
                WHERE states.id = cities.state_id
                  AND lower(states.name) = lower(cities.name)
            )'
        );
        // SQLite returns affected rows; some drivers may not — still ok.
        $this->info('Dropped same-name cities≈'.$deleted);

        // Re-rescue if we emptied any state by deleting the only same-name city.
        $this->rescueEmptyStates();
        $this->pruneEmptyStates();
    }
}
