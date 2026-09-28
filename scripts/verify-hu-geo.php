<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = App\Models\Country::where('iso_code', 'HU')->first();
$states = App\Models\State::where('country_id', $c->id)->orderBy('name')->get(['id', 'name']);
echo 'HU states='.$states->count().PHP_EOL;
foreach ($states as $st) {
    echo $st->name.'='.App\Models\City::where('state_id', $st->id)->count().PHP_EOL;
}
echo 'kap_as_state='.(App\Models\State::where('country_id', $c->id)->where('name', 'Kaposvár')->exists() ? 'yes' : 'no').PHP_EOL;
$som = App\Models\State::where('country_id', $c->id)->where('name', 'like', '%Somogy%')->first();
echo 'somogy='.$som->name.' cities='.App\Models\City::where('state_id', $som->id)->count().PHP_EOL;
echo 'has_kaposvar='.(App\Models\City::where('state_id', $som->id)->where('name', 'Kaposvár')->exists() ? 'yes' : 'no').PHP_EOL;
