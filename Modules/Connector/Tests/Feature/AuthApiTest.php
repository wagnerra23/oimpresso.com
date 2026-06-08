<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Smoke do pipeline de autenticação Passport (auth:api) do Modules/Connector.
 *
 * O Connector expõe API REST extensa pra clientes externos (Delphi WR Comercial,
 * SaaS Woo, etc) — toda rota está sob middleware `['log.delphi', 'auth:api', 'timezone']`
 * conforme [Modules/Connector/Routes/api.php](../../Routes/api.php).
 *
 * Este teste NÃO valida geração de token real (Passport personal access client
 * pode não estar provisionado em CI). Apenas garante:
 *   1. Endpoints rejeitam acesso sem Bearer → 401
 *   2. Endpoints rejeitam Bearer inválido → 401
 *   3. Pipeline auth:api está ANTES do controller (não há 200 anônimo)
 *
 * Para testes de token real, ver [DelphiOImpressoContractTest](../../../../tests/Feature/Connector/DelphiOImpressoContractTest.php).
 *
 * @see memory/decisions/0021-...-connector-delphi-restaurado.md (se existir — ADR 0021)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (Tier 0 — token sempre carrega business_id)
 */

beforeEach(function () {
    // Guard SQLite: Passport + UltimatePOS schema requerem MySQL/MariaDB real.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + UltimatePOS Connector requerem schema MySQL (ADR 0101)');
    }
});

it('endpoints Connector API rejeitam acesso sem Bearer (401)', function () {
    // Smoke nos endpoints REST principais do Connector — todos protegidos por
    // auth:api. Sem Bearer = 401 Unauthenticated.
    $endpoints = [
        ['GET',  '/connector/api/contactapi'],
        ['GET',  '/connector/api/product'],
        ['GET',  '/connector/api/sell'],
        ['GET',  '/connector/api/business-location'],
        ['GET',  '/connector/api/taxonomy'],
        ['GET',  '/connector/api/business-details'],
    ];

    foreach ($endpoints as [$method, $url]) {
        $r = $this->withHeaders(['Accept' => 'application/json'])->call($method, $url);
        // 401 Unauthenticated é o esperado. 404 indica rota não registrada (regressão).
        expect($r->getStatusCode())->toBeIn([401, 422], "Endpoint {$method} {$url} retornou {$r->getStatusCode()}, esperado 401");
        expect($r->getStatusCode())->not->toBe(404, "Rota {$method} {$url} não está registrada (404)");
        expect($r->getStatusCode())->not->toBe(200, "Endpoint {$method} {$url} retornou 200 sem auth — pipeline auth:api quebrado!");
    }
});

it('endpoints Connector API rejeitam Bearer token inválido (401)', function () {
    $endpoints = [
        ['GET',  '/connector/api/contactapi'],
        ['GET',  '/connector/api/product'],
        ['GET',  '/connector/api/sell'],
    ];

    foreach ($endpoints as [$method, $url]) {
        $r = $this->withHeaders([
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer token-invalido-falso-' . uniqid(),
        ])->call($method, $url);

        // Passport rejeita token mal-formado/expirado/inexistente → 401
        expect($r->getStatusCode())->toBe(401, "Endpoint {$method} {$url} aceitou token inválido — fail-secure quebrado!");
    }
});

it('endpoints CRM e FieldForce também exigem auth:api', function () {
    // Sub-grupos com middleware próprio mas mesmo stack auth:api.
    $endpoints = [
        ['GET',  '/connector/api/crm/follow-ups'],
        ['GET',  '/connector/api/crm/leads'],
        ['GET',  '/connector/api/field-force'],
    ];

    foreach ($endpoints as [$method, $url]) {
        $r = $this->withHeaders(['Accept' => 'application/json'])->call($method, $url);
        expect($r->getStatusCode())->toBeIn([401, 422], "Sub-API {$method} {$url} status inesperado: {$r->getStatusCode()}");
        expect($r->getStatusCode())->not->toBe(200, "Sub-API {$method} {$url} retornou 200 sem auth!");
    }
});

it('rotas Connector estão registradas com middleware auth:api', function () {
    // Audita o registro de rotas — protege contra alguém remover auth:api do grupo.
    $routes = Route::getRoutes();
    $connectorRoutes = collect($routes)->filter(function ($r) {
        return str_starts_with($r->uri(), 'connector/api');
    });

    expect($connectorRoutes->count())->toBeGreaterThan(10, 'Esperado >10 rotas registradas em connector/api/*');

    // Toda rota Connector API DEVE incluir auth:api no middleware
    $semAuth = $connectorRoutes->filter(function ($r) {
        return ! in_array('auth:api', $r->gatherMiddleware(), true);
    });

    expect($semAuth->count())->toBe(0, 'Rotas Connector sem auth:api: ' . $semAuth->pluck('uri')->implode(', '));
});

it('middleware log.delphi aplicado ANTES de auth:api (captura 401 também)', function () {
    // Guard de ordem — log.delphi precisa rodar antes pra logar tentativas
    // não-autenticadas. Se inverter, perdemos visibilidade de auth failures
    // (Delphi mandando token expirado, ataques de força bruta, etc).
    $routesFile = file_get_contents(base_path('Modules/Connector/Routes/api.php'));
    expect($routesFile)->toContain("'log.delphi', 'auth:api'");
});
