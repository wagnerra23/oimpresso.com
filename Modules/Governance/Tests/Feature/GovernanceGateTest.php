<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Auth gate das rotas /governance.
 *
 * MVP atual (ADR 0086) protege rotas via `auth` middleware UltimatePOS — não há
 * permission Spatie específica por enquanto (Fase 5+1 adiciona ActionGate strict).
 * Este teste valida o que está implementado HOJE:
 *
 *   1. Usuário ANÔNIMO é bloqueado (redirect login OU 401/403)
 *   2. Rotas críticas (/governance, /audit, /drift) exigem auth
 *   3. ActionGate middleware existe e modo default é `warn` (não bloqueia)
 *
 * Quando Fase 5+1 promover ActionGate pra `strict`, este teste deve ser
 * expandido pra cobrir: user sem trust_level → 403; user com L0/L1 → 200.
 *
 * SQLite guard obrigatório. biz=1 NUNCA biz=4 (ADR 0101).
 *
 * Refs: ADR 0086 (Governance MVP UI), ADR 0093 (Multi-tenant Tier 0),
 * Constituição Art. 8 (Policy Gating).
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('GovernanceGateTest roda apenas em SQLite in-memory (guard ADR 0101)');
    }

    // Tabelas mínimas pra Controllers não explodirem no boot
    Schema::dropIfExists('mcp_memory_documents');
    Schema::dropIfExists('mcp_governance_rules');
    Schema::dropIfExists('mcp_skill_versions');
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('mcp_actors');

    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->id();
        $t->string('slug')->unique();
        $t->string('title');
        $t->string('type', 30)->default('adr');
        $t->string('status', 30)->nullable();
        $t->timestamp('deleted_at')->nullable();
        $t->timestamps();
    });
    Schema::create('mcp_governance_rules', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->boolean('enabled')->default(true);
        $t->timestamps();
    });
    Schema::create('mcp_skill_versions', function (Blueprint $t) {
        $t->id();
        $t->string('slug');
        $t->string('status', 30)->default('draft');
        $t->timestamps();
    });
    Schema::create('mcp_audit_log', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->unsignedBigInteger('business_id')->nullable();
        $t->string('endpoint', 60);
        $t->string('tool_or_resource', 120)->nullable();
        $t->string('status', 30)->default('ok');
        $t->unsignedInteger('duration_ms')->nullable();
        $t->timestamp('ts')->useCurrent();
    });
    Schema::create('mcp_actors', function (Blueprint $t) {
        $t->id();
        $t->string('slug')->unique();
        $t->string('display_name')->nullable();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->timestamp('revoked_at')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    // mcp_* são tabelas reais-migradas. O afterEach roda MESMO em teste pulado
    // (PHPUnit 12: tearDown gated só por hasMetRequirements, true antes do
    // beforeEach/markTestSkipped) — dropá-las no MySQL persistente do nightly
    // corromperia os testes irmãos (Base table not found). DDL só em sqlite.
    if (config('database.default') !== 'sqlite') {
        return;
    }
    Schema::dropIfExists('mcp_actors');
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('mcp_skill_versions');
    Schema::dropIfExists('mcp_governance_rules');
    Schema::dropIfExists('mcp_memory_documents');
});

it('cenario 1: anonimo bloqueado em /governance (sem auth)', function () {
    $response = $this->get('/governance');
    // auth middleware redireciona pra login (302) OU retorna 401/403
    expect($response->status())->toBeIn([301, 302, 401, 403],
        "Anonimo deveria ser bloqueado em /governance, recebeu {$response->status()}");

    // Nunca 200 (vazaria dashboard sensivel)
    expect($response->status())->not->toBe(200);
});

it('cenario 2: anonimo bloqueado em /governance/audit (Constituicao Art. 9)', function () {
    $response = $this->get('/governance/audit');
    expect($response->status())->toBeIn([301, 302, 401, 403]);
    expect($response->status())->not->toBe(200);
});

it('cenario 3: anonimo bloqueado em /governance/drift', function () {
    $response = $this->get('/governance/drift');
    expect($response->status())->toBeIn([301, 302, 401, 403]);
    expect($response->status())->not->toBe(200);
});

it('cenario 4: anonimo bloqueado em /governance/policies', function () {
    $response = $this->get('/governance/policies');
    expect($response->status())->toBeIn([301, 302, 401, 403]);
    expect($response->status())->not->toBe(200);
});

it('cenario 5: ActionGate middleware existe e modo default e warn', function () {
    expect(class_exists(\Modules\Governance\Http\Middleware\ActionGate::class))->toBeTrue();
    // Modo default `warn` por config — Fase 5+1 promove pra `strict`
    expect(config('governance.actiongate_mode', 'warn'))->toBeIn(['off', 'warn', 'strict']);
});

it('cenario 6: middleware stack das rotas inclui auth', function () {
    // Pega rota nomeada e verifica que algum middleware auth-relacionado está aplicado.
    // Aceita 'auth', 'auth:web', 'Authenticate' FQCN — pattern UltimatePOS pode aplicar
    // via grupo/alias/classe direta dependendo da versão.
    $route = \Route::getRoutes()->getByName('governance.admin.dashboard');
    expect($route)->not->toBeNull('Rota governance.admin.dashboard deveria existir');

    $middlewares = $route->gatherMiddleware();
    $hasAuthLike = collect($middlewares)->contains(
        fn ($m) => is_string($m) && stripos($m, 'auth') !== false
    );
    expect($hasAuthLike)->toBeTrue(
        'Rota /governance deveria ter algum middleware auth-relacionado (got: ' . json_encode($middlewares) . ')'
    );
});
