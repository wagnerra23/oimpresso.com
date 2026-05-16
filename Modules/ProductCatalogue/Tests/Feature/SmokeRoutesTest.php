<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke routes Modules/ProductCatalogue — Wave B (gap P3).
 *
 * Cobre as 3 rotas principais do catalogo:
 *   - GET /catalogue/{business_id}/{location_id} — index publico (catalogo compartilhavel)
 *   - GET /show-catalogue/{business_id}/{product_id} — detalhe produto publico
 *   - GET /product-catalogue/catalogue-qr — geracao QR code (admin autenticado)
 *
 * Estrategia: verifica que rotas respondem (nao retornam 500 puro) e que controllers
 * sao despachaveis. Tests usam biz=1 (Wagner WR2) — NUNCA biz=4 (Larissa producao) — ADR 0101.
 *
 * SQLite-incompatible: ProductCatalogue depende de App\Product + relacoes
 * (variations, product_locations, discounts) com schema MySQL UltimatePOS.
 *
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite — Models core UltimatePOS requerem MySQL schema completo
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: ProductCatalogue depende de App\\Product + relacoes do schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
    }
    if (! Schema::hasTable('products') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS incompleto — rode migrate primeiro');
    }
});

const BIZ_WAGNER_CAT = 1;

it('rota publica /catalogue/{biz}/{loc} responde (nao 500 puro)', function () {
    // Smoke: rota deve resolver Controller + Model lookup. Pode 404 (sem data),
    // mas nao deve 500 com fatal error de class-not-found ou typo em namespace.
    $response = $this->get('/catalogue/' . BIZ_WAGNER_CAT . '/1');

    // Aceita 200 (catalogo renderizou), 404 (location nao existe), 302 (redirect).
    // Rejeita 500 (fatal) — sinal de typo namespace ou Provider broken.
    expect($response->status())->not->toBe(500);
});

it('rota publica /show-catalogue/{biz}/{id} responde (nao 500 puro)', function () {
    $response = $this->get('/show-catalogue/' . BIZ_WAGNER_CAT . '/999999');

    // 404 esperado quando product_id=999999 nao existe; smoke nao quer 500.
    expect($response->status())->not->toBe(500);
});

it('rota admin /product-catalogue/catalogue-qr exige autenticacao (302/401/403)', function () {
    // Sem auth, middleware 'auth' redireciona pro login (302) ou retorna 401/403.
    $response = $this->get('/product-catalogue/catalogue-qr');

    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rotas product-catalogue carregam stack middleware UltimatePOS', function () {
    $route = collect(\Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'product-catalogue/catalogue-qr');

    expect($route)->not->toBeNull('Rota product-catalogue/catalogue-qr deveria existir');

    $middleware = $route->gatherMiddleware();
    // Stack canonico UltimatePOS — at least 'web' + 'auth' presentes (skill runtime-rules)
    expect($middleware)->toContain('web');
    expect($middleware)->toContain('auth');
});
