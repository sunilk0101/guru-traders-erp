<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$codes = json_decode(file_get_contents(__DIR__.'/../storage/app/product-audit-08092026-sheet-codes.json'), true);
$dbCodes = App\Models\Product::pluck('item_group_code')->all();
$dbSet = array_fill_keys($dbCodes, true);

$missing = [];
foreach ($codes as $code) {
    if (! isset($dbSet[$code])) {
        $missing[] = $code;
    }
}

$extra = [];
foreach ($dbCodes as $code) {
    if (! in_array($code, $codes, true)) {
        $extra[] = $code;
    }
}

echo 'sheet_unique='.count($codes).PHP_EOL;
echo 'db_products='.count($dbCodes).PHP_EOL;
echo 'missing_from_db='.count($missing).PHP_EOL;
echo 'in_db_not_sheet='.count($extra).PHP_EOL;
if ($missing) {
    echo "MISSING:\n".implode("\n", $missing)."\n";
}
if ($extra) {
    echo "EXTRA_IN_DB:\n".implode("\n", $extra)."\n";
}

foreach (['281AA', '281AB', '234AB', '643AA', '007AB'] as $c) {
    $p = App\Models\Product::where('item_group_code', $c)->with('category')->first();
    echo $c.' => '.($p ? $p->name.' | '.$p->category?->name : 'NOT FOUND').PHP_EOL;
}
