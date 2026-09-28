<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'total='.App\Models\Port::count().PHP_EOL;
foreach (['AU', 'NZ', 'FJ', 'PG', 'MH', 'WS', 'TO', 'NC', 'PF', 'SB', 'VU'] as $iso) {
    echo $iso.'='.App\Models\Port::whereHas('country', fn ($q) => $q->where('iso_code', $iso))->count().PHP_EOL;
}
echo App\Models\Port::where('name', 'like', '%Sydney%')->value('name').PHP_EOL;
echo App\Models\Port::where('name', 'like', '%Auckland%')->value('name').PHP_EOL;
echo App\Models\Port::where('name', 'like', '%Majuro%')->value('name').PHP_EOL;
