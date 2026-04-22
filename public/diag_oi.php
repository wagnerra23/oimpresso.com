<?php
// Bootstrap Laravel
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

echo "<h2>Officeimpresso Diagnostics</h2>";

// 1. Check module status
$status = json_decode(file_get_contents(__DIR__.'/../modules_statuses.json'), true);
echo "<b>Module status:</b> " . ($status['Officeimpresso'] ?? 'NOT SET') . "<br>";

// 2. Check routes registered
$router = $app->make('router');
$routes = $router->getRoutes();
$oi_routes = [];
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'officeimpresso')) {
        $oi_routes[] = $route->methods()[0] . ' /' . $route->uri();
    }
}
echo "<b>Officeimpresso routes registered:</b> " . count($oi_routes) . "<br>";
echo "<pre>" . implode("\n", $oi_routes) . "</pre>";

// 3. Check last error in log
$logfile = __DIR__.'/../storage/logs/laravel.log';
if (file_exists($logfile)) {
    $log = file_get_contents($logfile);
    $last4k = substr($log, -4000);
    echo "<h3>Last log entries:</h3><pre>" . htmlspecialchars($last4k) . "</pre>";
} else {
    echo "<b>Log file not found</b><br>";
}
