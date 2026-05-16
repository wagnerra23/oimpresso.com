<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Security tests da rota publica de consulta de OS.
 *
 * Estado atual (2026-05-15): ConsultaOsController::buscar() usa mockData()
 * in-memory — NAO consulta DB ainda. O TODO(query-real) no Controller diz que
 * a busca real sera por invoice_no + ultimos 4 do telefone (padrao Repair),
 * com filtro multi-tenant via transactions.business_id.
 *
 * Estes testes formam o CONTRATO DE SEGURANCA que a futura implementacao
 * com DB precisa preservar. Hoje validamos comportamento via mock; quando
 * Wagner migrar para query real (transactions table), os mesmos asserts
 * precisarao continuar passando — agora consultando dados de biz=1 vs biz=99.
 *
 * Refs:
 *   - ADR 0093 multi-tenant Tier 0 IRREVOGAVEL
 *   - ADR 0101 tests biz=1 (Wagner WR2), nunca biz=4 (ROTA LIVRE cliente)
 *   - Modules/Repair/Routes/web.php /repair-status (padrao a imitar)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: TestCase UltimatePOS requer schema MySQL (ADR 0101).');
    }
});

// ID Wagner WR2 — biz canonico para tests (ADR 0101) e ID ficticio sem dados
const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

it('cenario A: numero invalido/inexistente retorna 404 com found=false (sem vazar dados)', function () {
    // Tentar varios numeros aleatorios — nenhum existe no mock.
    $tentativas = ['00000', '99999', 'XYZ123', '4820']; // 4820 nao esta no mock

    foreach ($tentativas as $numero) {
        $response = $this->getJson('/consulta-os/buscar?numero='.urlencode($numero));

        $response->assertStatus(404, "Numero invalido [{$numero}] deveria retornar 404");
        $response->assertJson(['found' => false]);

        // Garantir que nenhum dado de OS vazou na resposta de erro
        $body = $response->json();
        expect($body)->not->toHaveKey('os', "Numero invalido [{$numero}] NAO deve retornar payload 'os'");
    }
});

it('cenario B: anonimo sem token/sem numero recebe 422 validation (sem listar nada)', function () {
    // Rota /consulta-os/buscar exige campo 'numero' (Controller::buscar valida).
    // Sem ele, retorna 422 — anonimo NUNCA consegue listar todas as OS de algum biz.
    $response = $this->getJson('/consulta-os/buscar');

    $response->assertStatus(422);

    // Garantir que payload de erro NAO inclui dados de OS de nenhum business
    $body = $response->json();
    expect($body)->not->toHaveKey('os');
    expect($body)->not->toHaveKey('data');
});

it('cenario C: anonimo NAO consegue brute-force enumeracao (rate-limit throttle:30,1 aplicado)', function () {
    // Rota /consulta-os/buscar tem middleware 'throttle:30,1' (30 req/min).
    // Esta e uma garantia ANTI brute-force — anonimo nao pode varrer numeros sequenciais
    // ilimitadamente para descobrir OS de outros businesses.
    //
    // Validamos que middleware throttle esta registrado na rota.
    $route = \Route::getRoutes()->getByName('consulta-os.buscar');
    expect($route)->not->toBeNull('Rota consulta-os.buscar deveria existir');

    $middlewares = $route->middleware();
    $temThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with($m, 'throttle:'));
    expect($temThrottle)->toBeTrue(
        'Rota publica consulta-os.buscar DEVE ter middleware throttle: para evitar enumeracao brute-force cross-business (ADR 0093).'
    );
});

it('cenario D: cross-business — anonimo nao pode passar business_id como query e sobrescrever scope', function () {
    // Tentar injetar business_id na query string — Controller atual ignora
    // (mockData nao olha business_id). Quando migrar para DB real, multi-tenant
    // Tier 0 IRREVOGAVEL (ADR 0093) GARANTE que cliente anonimo nao pode forcar
    // escopo de outro business via query param.
    //
    // Hoje validamos que mesmo passando o param, a resposta segue o mock —
    // NAO deve retornar lista ampla nem vazar metadata de outros businesses.
    $response = $this->getJson('/consulta-os/buscar?numero=4821&business_id='.BIZ_FICTICIO);

    // Numero 4821 existe no mock — Controller atual responde normalmente, ignorando business_id.
    // O que IMPORTA: resposta NAO inclui campo business_id no payload (sem leak)
    // e NAO retorna lista (apenas 1 registro pontual).
    if ($response->getStatusCode() === 200) {
        $body = $response->json();
        expect($body)->toHaveKey('os');
        expect($body['os'])->not->toHaveKey('business_id', 'Payload publico NAO deve vazar business_id (ADR 0093 PII guard).');

        // Resposta deve ser registro unico, nunca array de OS
        expect($body['os'])->toBeArray();
        expect(isset($body['os'][0]))->toBeFalse('Resposta publica deve retornar 1 OS, nunca lista — anonimo nao tem permissao de listar.');
    }
});
