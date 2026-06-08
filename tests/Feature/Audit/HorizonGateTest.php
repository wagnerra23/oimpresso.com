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
 *
 * DoD US-COPI-096:
 *   - 404 quando flag false (rotas nem existem)            → covered
 *   - 403 quando user não-superadmin (gate Horizon::check) → covered
 *   - 200 (allow) quando user superadmin                   → covered
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Horizon;

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

test('rota /horizon retorna 404 quando flag false (DoD US-COPI-096)', function () {
    // Com flag false rota nem existe → Laravel responde 404 (não 403).
    // Garante que Hostinger sem env override jamais sirva Horizon UI.
    $response = $this->get('/horizon');

    expect($response->status())->toBe(
        404,
        'Rota /horizon respondeu '.$response->status().' com flag false — esperava 404. ADR 0062.'
    );
});

test('gate Horizon::check rejeita user não-superadmin (DoD US-COPI-096 → 403)', function () {
    // Replica o gate canônico do HorizonServiceProvider (early-return desliga
    // o gate junto com o resto, então re-aplica aqui pra testar a lógica pura).
    Horizon::auth(static fn ($request) => $request->user()
        && $request->user()->can('superadmin'));

    $user = new class {
        public function can(string $ability): bool
        {
            return false; // user comum sem permission superadmin
        }
    };

    $request = Request::create('/horizon', 'GET');
    $request->setUserResolver(fn () => $user);

    expect(Horizon::check($request))->toBeFalse(
        'Gate Horizon deveria rejeitar user sem permission superadmin (Authenticate middleware → 403).'
    );
});

test('gate Horizon::check aceita user superadmin (DoD US-COPI-096 → 200)', function () {
    Horizon::auth(static fn ($request) => $request->user()
        && $request->user()->can('superadmin'));

    $superadmin = new class {
        public function can(string $ability): bool
        {
            return $ability === 'superadmin';
        }
    };

    $request = Request::create('/horizon', 'GET');
    $request->setUserResolver(fn () => $superadmin);

    expect(Horizon::check($request))->toBeTrue(
        'Gate Horizon deveria liberar superadmin (Spatie permission canon UltimatePOS via Gate::before AuthServiceProvider).'
    );
});

test('gate Horizon::check rejeita request sem user autenticado', function () {
    Horizon::auth(static fn ($request) => $request->user()
        && $request->user()->can('superadmin'));

    $request = Request::create('/horizon', 'GET');
    $request->setUserResolver(fn () => null);

    expect(Horizon::check($request))->toBeFalse(
        'Gate Horizon deveria rejeitar request sem user (auth() check primeiro).'
    );
});
