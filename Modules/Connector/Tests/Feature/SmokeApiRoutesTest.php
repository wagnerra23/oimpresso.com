<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Smoke das rotas REST principais do Modules/Connector — garante que estão
 * registradas (sem 404), sem precisar de token Passport real.
 *
 * O Connector expõe API a clientes externos (Woo, SaaS, Delphi WR Comercial).
 * Qualquer remoção acidental de rota quebra integrações em prod silenciosamente
 * (route:cache não detecta — rotas inexistentes retornam 404 padrão sem alerta).
 *
 * Estratégia: usar Route::has() OR fazer request sem auth e validar que NÃO é 404.
 * 401/422 = rota existe + middleware funcionando = ✅
 * 404     = rota foi removida silenciosamente = ❌ regressão
 *
 * @see Modules/Connector/Routes/api.php (definição canônica)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Connector API requer schema MySQL UltimatePOS (ADR 0101)');
    }
});

it('rota GET /connector/api/contactapi (listar contatos) está registrada', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->get('/connector/api/contactapi');
    expect($r->getStatusCode())->not->toBe(404, 'Rota contactapi removida silenciosamente!');
    expect($r->getStatusCode())->toBeIn([401, 422]);
});

it('rota GET /connector/api/product (listar produtos) está registrada', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->get('/connector/api/product');
    expect($r->getStatusCode())->not->toBe(404, 'Rota product removida silenciosamente!');
    expect($r->getStatusCode())->toBeIn([401, 422]);
});

it('rota GET /connector/api/sell (listar vendas/transactions) está registrada', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->get('/connector/api/sell');
    expect($r->getStatusCode())->not->toBe(404, 'Rota sell removida silenciosamente!');
    expect($r->getStatusCode())->toBeIn([401, 422]);
});

it('rota GET /connector/api/business-details está registrada', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->get('/connector/api/business-details');
    expect($r->getStatusCode())->not->toBe(404, 'Rota business-details removida silenciosamente!');
    expect($r->getStatusCode())->toBeIn([401, 422]);
});

it('rota GET /connector/api/taxonomy (categorias) está registrada', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->get('/connector/api/taxonomy');
    expect($r->getStatusCode())->not->toBe(404, 'Rota taxonomy removida silenciosamente!');
    expect($r->getStatusCode())->toBeIn([401, 422]);
});

it('rotas Resource Connector estão todas registradas via Route::has()', function () {
    // Usa nomes Laravel gerados por Route::resource() pra validar via Route::has().
    // Names canônicos: connector.<resource>.<action> (prefix declarado em api.php).
    $namedRoutes = [
        'connector.contactapi.index',
        'connector.contactapi.show',
        'connector.product.index',
        'connector.product.show',
        'connector.sell.index',
        'connector.sell.store',
        'connector.business-location.index',
        'connector.tax.index',
        'connector.brand.index',
        'connector.unit.index',
        'connector.delphi.processa-dados-cliente',
        'connector.delphi.salvar-cliente',
        'connector.delphi.oimpresso.registrar',
        'connector.delphi.check-update',
    ];

    $missing = [];
    foreach ($namedRoutes as $name) {
        if (! Route::has($name)) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBe([], 'Rotas Connector ausentes: ' . implode(', ', $missing));
});

it('Connector expõe pelo menos 20 rotas /connector/api/*', function () {
    // Sanity check de cobertura mínima — Connector tem 23+ controllers Api/.
    // Se cair abaixo de 20, é sinal de remoção acidental de bloco inteiro.
    $count = collect(Route::getRoutes())->filter(function ($r) {
        return str_starts_with($r->uri(), 'connector/api');
    })->count();

    expect($count)->toBeGreaterThanOrEqual(20, "Esperado >=20 rotas connector/api/*, achou {$count}");
});

it('rotas de pagamento contactapi-payment e shipping estão registradas', function () {
    // Endpoints transacionais críticos pra integração financeira externa.
    $r1 = $this->withHeaders(['Accept' => 'application/json'])->post('/connector/api/contactapi-payment');
    expect($r1->getStatusCode())->not->toBe(404);

    $r2 = $this->withHeaders(['Accept' => 'application/json'])->post('/connector/api/update-shipping-status');
    expect($r2->getStatusCode())->not->toBe(404);
});

it('endpoint de registro de usuário não é exposto sem auth', function () {
    // user-registration é POST com auth:api. Se cair pra 200 sem token = brecha.
    $r = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/connector/api/user-registration', ['username' => 'evil', 'password' => 'x']);
    expect($r->getStatusCode())->toBeIn([401, 422]);
    expect($r->getStatusCode())->not->toBe(200, 'user-registration aceitou request sem auth — brecha crítica!');
    expect($r->getStatusCode())->not->toBe(201);
});
