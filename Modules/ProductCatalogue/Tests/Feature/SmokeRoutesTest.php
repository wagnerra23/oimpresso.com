<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Rota admin do catálogo (/product-catalogue/catalogue-qr): sem login não abre, e a rota carrega a
 * pilha de middleware do UltimatePOS.
 *
 * As duas rotas públicas saíram deste arquivo em 2026-11: o smoke delas usava a empresa 1 (real,
 * contra a ADR 0358) e só rejeitava 500. O comportamento delas tem contrato com corpo lido e
 * 98×99 em PublicCatalogueSecurityTest (par empresa/local, empresa inexistente) e em
 * tests/Feature/Sells/CatalogoPublico*ContratoTest.php (listagem, detalhe, produto alheio, XSS).
 * A tela logada tem contrato em tests/Feature/Sells/CatalogueQrContratoTest.php.
 */

it('rota admin /product-catalogue/catalogue-qr sem login não entrega a tela', function () {
    $response = $this->get('/product-catalogue/catalogue-qr');

    expect($response->status())->toBeIn([302, 401, 403]);
    expect((string) $response->getContent())->not->toContain('ProductCatalogue/CatalogueQr');
});

it('rota admin do catálogo carrega a pilha de middleware do UltimatePOS', function () {
    $route = collect(\Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'product-catalogue/catalogue-qr');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('web');
    expect($middleware)->toContain('auth');
    expect($middleware)->toContain('SetSessionData');
});
