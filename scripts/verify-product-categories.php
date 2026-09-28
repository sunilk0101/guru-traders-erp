<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cats = App\Models\Category::withCount('products')->orderBy('name')->get();
foreach ($cats as $c) {
    if ($c->products_count > 0) {
        echo $c->name.'='.$c->products_count.PHP_EOL;
    }
}
echo 'total_products='.App\Models\Product::count().PHP_EOL;
$p = App\Models\Product::where('item_group_code', '001AA')->with('category')->first();
echo '001AA category='.($p?->category?->name ?? '?').PHP_EOL;
$p2 = App\Models\Product::where('item_group_code', '319AA')->with('category')->first();
echo '319AA category='.($p2?->category?->name ?? '?').PHP_EOL;
$p3 = App\Models\Product::where('item_group_code', '245')->with('category')->first();
echo '245 category='.($p3?->category?->name ?? '?').PHP_EOL;
