<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = App\Models\Category::withTrashed()
    ->where('name', 'like', '%Readymade%')
    ->orWhere('name', 'like', '%Saree%')
    ->get(['id', 'code', 'name', 'status', 'deleted_at']);

echo $rows->toJson(JSON_PRETTY_PRINT).PHP_EOL;
echo 'active_count='.App\Models\Category::active()->count().PHP_EOL;
echo 'total='.App\Models\Category::count().PHP_EOL;
echo 'trashed='.App\Models\Category::onlyTrashed()->count().PHP_EOL;
