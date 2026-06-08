<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke test rotas Brief — Install (ADR 0024) + endpoint MCP brief-fetch (ADR 0091).
 *
 * Não autentica usuário — só valida que rota responde algo coerente
 * (redirect login ou 401/403/422 ao invés de 500/404).
 *
 * NUNCA biz=4 (ROTA LIVRE produção) — ADR 0101. Tests usam biz=1.
 */

// Guard SQLite: BriefFetchController consulta tabela mcp_briefs (schema MCP MySQL only).
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: BriefFetchController consulta mcp_briefs (schema MCP MySQL). '.
            'Wagner Pest local segue mandatory — ADR 0101 / ADR 0091'
        );
    }
});

it('rota brief/install nao retorna 500', function () {
    // Sem autenticar — esperamos redirect login (302), 401/403 ou 200 (se middleware bypass test).
    // O que NÃO queremos: 500 (erro de boot) nem 404 (rota não registrada).
    $response = $this->get('/brief/install');

    expect($response->status())->not->toBe(500, 'Rota brief/install não deveria estourar 500');
    expect($response->status())->not->toBe(404, 'Rota brief/install deveria existir (ADR 0024)');
});

it('rota POST mcp/tools/brief-fetch existe e exige auth MCP', function () {
    // Sem header X-MCP-Token, middleware mcp.auth deve rejeitar (401/403/422).
    // Não esperamos 404 (rota faltando) nem 500 (erro de boot).
    $response = $this->postJson('/api/mcp/tools/brief-fetch', [
        'force_refresh' => false,
    ]);

    expect($response->status())->not->toBe(404, 'Rota MCP brief-fetch deveria existir');
    expect($response->status())->not->toBe(500, 'Rota MCP brief-fetch não deveria estourar 500 sem auth');
    // Aceita 401/403/422 (auth rejeitada) ou 429 (throttle agressivo)
    expect(in_array($response->status(), [401, 403, 422, 429], true))
        ->toBeTrue('brief-fetch sem auth MCP deveria retornar 401/403/422/429, retornou '.$response->status());
});

it('controller BriefFetch e injetavel via container', function () {
    // Garante DI do BriefGeneratorService funciona — falha silenciosa de boot
    // pega aqui antes de mockar request.
    $controller = app(\Modules\Brief\Http\Controllers\BriefFetchController::class);
    expect($controller)->toBeInstanceOf(\Modules\Brief\Http\Controllers\BriefFetchController::class);
});
