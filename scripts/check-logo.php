<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = App\Models\CompanyProfile::current();
echo 'logo_path='.($p->logo_path ?? 'null').PHP_EOL;
echo 'hasLogo='.($p->hasLogo() ? 'yes' : 'no').PHP_EOL;
echo 'abs='.($p->logoAbsolutePath() ?? 'null').PHP_EOL;
