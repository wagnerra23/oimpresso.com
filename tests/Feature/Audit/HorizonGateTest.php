<?php

declare(strict_types=1);

/**
 * GUARD: Horizon UI nunca exposta com flag default (ADR 0062 Hostinger != CT 100).
 *
 * Com HORIZON_TOOLS_EXPOSED=false (default), HorizonServiceProvider faz early-return
 * em register()/boot() e o vendor está em dont-discover (composer.json). Resultado:
 * zero rotas `horizon.*` registradas. Hostinger nunca expõe Horizon.
 * CT 100 ativa via .env próprio (HORIZON_TOOLS_EXPOSED=true) e valida no deploy.
 *
 * Padrão idêntico ao MCP_TOOLS_EXPOSED (US-COPI-094).
 */

use Illuminate\Support\Facades\Route;

test('flag default = false (config/horizon.php tools_exposed)', function () {
    expect(config('horizon.tools_exposed'))->toBeFalse();
});

test('zero rotas horizon registradas com flag default (ADR 0062)', function () {
    $horizonRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'horizon'))
        ->count();

    expect($horizonRoutes)->toBe(
        0,
        'Horizon registrou rotas com tools_exposed=false — ADR 0062 violada (Hostinger expondo UI)'
    );
});
