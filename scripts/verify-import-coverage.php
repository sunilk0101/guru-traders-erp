<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$import = json_decode(file_get_contents(__DIR__.'/../storage/app/product-import-08092026.json'), true);
$importCodes = array_column($import['products'], 'item_group_code');
$dbCodes = App\Models\Product::pluck('item_group_code')->all();

$missing = array_values(array_diff($importCodes, $dbCodes));
echo 'import_json='.count($importCodes).PHP_EOL;
echo 'db='.count($dbCodes).PHP_EOL;
echo 'missing_from_db='.count($missing).PHP_EOL;
if ($missing) {
    echo implode(',', $missing).PHP_EOL;
}

$cats = [];
foreach (App\Models\Product::with('category')->get() as $p) {
    $name = $p->category?->name ?? '(none)';
    $cats[$name] = ($cats[$name] ?? 0) + 1;
}
arsort($cats);
foreach ($cats as $n => $c) {
    echo "$n=$c\n";
}

foreach (['245', '281AA', '319AA', '001AA', '410AA'] as $c) {
    $p = App\Models\Product::where('item_group_code', $c)->with('category')->first();
    echo $c.' => '.($p ? $p->name.' | cat='.$p->category?->name : 'MISSING').PHP_EOL;
}
