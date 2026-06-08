<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

// `uses(Tests\TestCase::class)` aplicado globalmente em tests/Pest.php pra
// Modules/KB/Tests/Feature (uses(TestCase::class)->in($kbFeatureDir)). Não
// redeclarar aqui — viola check "test case already uses" do Pest 3.x.

/**
 * Wave 26 KB Smoke — D2.b canonical pattern (ModuleGradeService rubrica).
 *
 * Smoke test rotas KB — Install (ADR 0024) + endpoint `/kb` (browser tri-pane).
 *
 * Não autentica usuário — só valida que rota responde algo coerente
 * (redirect login ou 401/403/422/302 ao invés de 500/404).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 * NUNCA biz=4 (ROTA LIVRE Larissa produção). Tests usam biz=1 (ADR 0101).
 *
 * Pattern proven em Modules/Brief/Tests/Feature/SmokeRoutesTest.php
 * (modelo canônico — adaptado pra KB).
 *
 * @see Modules/KB/Http/routes.php — rotas registradas
 * @see memory/decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md
 */

// Guard SQLite: KB consulta mcp_memory_documents (schema MCP MySQL only)
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: KB bridge depende de mcp_memory_documents (schema MCP MySQL). '.
            'Wagner Pest local MySQL — ADR 0101 / ADR 0061'
        );
    }
});

it('rota kb/install nao retorna 500 nem 404', function () {
    // Sem autenticar — esperamos redirect login (302), 401/403 ou 200.
    // O que NÃO queremos: 500 (erro de boot) nem 404 (rota faltando).
    $response = $this->get('/kb/install');

    expect($response->status())->not->toBe(500);
    expect($response->status())->not->toBe(404);
});

it('rota GET /kb existe nomeada kb.index', function () {
    // KbController@index é o ponto de entrada do browser tri-pane KB.
    // Verifica que está registrada via name() — fail-fast em refactor de routes.
    expect(\Route::has('kb.index'))->toBeTrue();
});

it('controllers principais KB sao injetaveis via container', function () {
    // Garante DI dos Controllers funciona — falha silenciosa de boot
    // do KBServiceProvider pega aqui antes de mockar request real.
    $controllers = [
        \Modules\KB\Http\Controllers\KbController::class,
        \Modules\KB\Http\Controllers\KbNodeController::class,
        \Modules\KB\Http\Controllers\DataController::class,
    ];

    foreach ($controllers as $class) {
        $instance = app($class);
        expect($instance)->toBeInstanceOf($class);
    }
});

it('DataController expoe superadmin_package + user_permissions', function () {
    $controller = new \Modules\KB\Http\Controllers\DataController;

    $pkg = $controller->superadmin_package();
    expect($pkg)->toBeArray()->and($pkg)->not->toBeEmpty();
    expect($pkg[0])->toHaveKey('name')
        ->and($pkg[0]['name'])->toBe('kb_module');

    $perms = $controller->user_permissions();
    expect($perms)->toBeArray()->and($perms)->not->toBeEmpty();
    expect($perms[0])->toHaveKey('value');
    // Pelo menos uma das permissões canônicas deve estar presente.
    $values = array_column($perms, 'value');
    expect($values)->toContain('kb.view');
});

it('Services canonicos KB sao injetaveis via container', function () {
    // Services-chave do KB: RAG + Reranker + ArticleService + BridgeStateService
    // Multi-tenant Tier 0 (ADR 0093) — businessId resolvido por scope canon
    // BusinessScope/BelongsToBusinessTrait nos Entities.
    $services = [
        \Modules\KB\Services\KbRagService::class,
        \Modules\KB\Services\KbArticleService::class,
        \Modules\KB\Services\KbBridgeStateService::class,
    ];

    foreach ($services as $class) {
        $svc = app($class);
        expect($svc)->toBeInstanceOf($class);
    }
});
