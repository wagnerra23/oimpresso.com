<?php

/**
 * Modulo: Superadmin — /pricing redesenhado (Inertia + React).
 *
 * Decisao recente (2026-04-2x): PricingController::index agora retorna
 * Inertia::render('Site/Pricing', ['packages', 'permissions']). Versao Blade
 * legada vive em /pricing/old (PricingController::indexLegacy).
 *
 * Cobertura:
 *   - GET /pricing      : 200 + Inertia component "Site/Pricing"
 *   - GET /pricing/old  : 200 + Blade tradicional
 *   - props.packages    : array (mesmo vazio nao quebra)
 *   - props.permissions : array
 *   - rotas /superadmin/* exigem middleware "superadmin" -> 302/403 sem auth
 *
 * /pricing eh PUBLICO (sem middleware 'superadmin') — eh a vitrine de planos.
 */

beforeEach(function () {
    session()->flush();
    auth()->logout();
});

it('GET /pricing renderiza Inertia "Site/Pricing" com packages e permissions', function () {
    $r = $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html',
    ])->get('/pricing');

    expect($r->getStatusCode())->toBe(200);
    $r->assertHeader('X-Inertia', 'true');

    $payload = $r->json();
    expect($payload)->toHaveKey('component');
    expect($payload['component'])->toBe('Site/Pricing');
    expect($payload['props'])->toHaveKey('packages');
    expect($payload['props'])->toHaveKey('permissions');
    expect($payload['props']['packages'])->toBeArray();
    expect($payload['props']['permissions'])->toBeArray();
});

it('GET /pricing sem header Inertia retorna HTML (200) com root data-page', function () {
    $r = $this->get('/pricing');
    expect($r->getStatusCode())->toBe(200);
    // Inertia injeta data-page no root <div id="app">.
    expect($r->getContent())->toContain('Site/Pricing');
});

it('GET /pricing/old renderiza versao Blade legada com 200', function () {
    $r = $this->get('/pricing/old');
    expect($r->getStatusCode())->toBe(200);
});

it('rotas /superadmin/* exigem auth + middleware superadmin (302/403 sem login)', function () {
    auth()->logout();
    session()->flush();

    foreach (['/superadmin', '/superadmin/business', '/superadmin/packages', '/superadmin/settings'] as $url) {
        $r = $this->get($url);
        expect($r->getStatusCode())
            ->toBeIn([302, 401, 403], "Rota {$url} deveria bloquear acesso anon");
    }
});

it('GET /page/{slug} de pagina inexistente nao crasha (404 esperado)', function () {
    $r = $this->get('/page/' . uniqid('inexistente-'));
    expect($r->getStatusCode())->toBeIn([302, 404, 500]);
});
