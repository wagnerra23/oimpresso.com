<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $svc = app(\App\Services\Sells\SellsCockpitAggregator::class);
    $r = $svc->buildInsightsAggregates(1);
    echo "OK\n";
    var_export($r);
} catch (\Throwable $e) {
    echo "ERR: " . $e->getMessage() . "\n";
    echo "AT: " . $e->getFile() . ':' . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
