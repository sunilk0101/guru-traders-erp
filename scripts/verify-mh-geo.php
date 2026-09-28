<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Support\Facades\DB;

$same = DB::table('cities')
    ->join('states', 'states.id', '=', 'cities.state_id')
    ->whereRaw('lower(cities.name) = lower(states.name)')
    ->count();
echo "same_name_pairs={$same}\n";
echo 'states='.State::count().' cities='.City::count()."\n";

$c = Country::where('iso_code', 'MH')->first();
$states = State::where('country_id', $c->id)->orderBy('name')->get(['id', 'name']);
echo 'MH states='.$states->count()."\n";
foreach ($states as $st) {
    $cities = City::where('state_id', $st->id)->orderBy('name')->pluck('name');
    echo $st->name.' ('.$cities->count().') => '.$cities->take(12)->implode(', ').($cities->count() > 12 ? '…' : '')."\n";
}
echo 'Utrik as state='.(State::where('country_id', $c->id)->where('name', 'Utrik')->exists() ? 'yes' : 'no')."\n";
echo 'Utrik as city under Ratak='.(City::where('name', 'Utrik')->whereHas('state', fn ($q) => $q->where('name', 'Ratak')->where('country_id', $c->id))->exists() ? 'yes' : 'no')."\n";
