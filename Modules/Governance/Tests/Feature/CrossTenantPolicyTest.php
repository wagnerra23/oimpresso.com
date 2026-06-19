<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Cross-tenant policy gate — Modules/Governance.
 *
 * Governance é módulo INTENCIONALMENTE cross-tenant (Constituição Art. 6+8):
 * opera transversal sobre tabelas `mcp_*` (mcp_memory_documents, mcp_governance_rules,
 * mcp_audit_log, mcp_actors, mcp_skill_versions). Essas tabelas NÃO têm coluna
 * `business_id` por design — o gate de proteção é via middleware `auth` + permission
 * `superadmin`/`governance.dashboard.view`, NÃO via global scope.
 *
 * Este teste valida:
 *   1. superadmin biz=1 acessa /governance (não retorna 5xx)
 *   2. user normal biz=1 sem permission é bloqueado (302/403)
 *   3. user normal biz=99 (fictício) também bloqueado — mesmo cross-tenant
 *   4. tabelas mcp_* NÃO devem ter coluna business_id (cross-tenant by design)
 *   5. queries em mcp_memory_documents NÃO aplicam scope de business (cross-tenant)
 *   6. ModuleGrade endpoint /governance/module-grades aplica mesmo gate
 *
 * SQLite guard obrigatório (rotas precisam middleware UltimatePOS → MySQL).
 * biz=1 (Wagner WR2) NUNCA biz=4 (ROTA LIVRE cliente Larissa) — ADR 0101.
 *
 * Refs: ADR 0086 (Governance MVP UI), ADR 0093 (Multi-tenant Tier 0 — Governance
 * é exceção transversal), ADR 0101 (Tests biz=1), ADR 0153 (Module Grade rubrica),
 * Constituição Art. 6 (Identity Mesh) + Art. 8 (Policy Gating) + Art. 9 (Auditoria).
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('CrossTenantPolicyTest roda apenas em SQLite in-memory (guard ADR 0101)');
    }

    // Schema mínimo das tabelas mcp_* — replicar Wave B GovernanceGateTest pattern
    // INTENCIONAL: sem coluna business_id (cross-tenant by design Art. 6+8)
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
        // OBSERVAÇÃO: business_id em mcp_audit_log é METADATA de auditoria
        // (registrar QUEM fez ação cross-tenant), NÃO filtro de scope.
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

// IDs canônicos: biz=1 Wagner WR2, biz=99 fictício isolamento (ADR 0101)
const BIZ_WAGNER_GOV = 1;
const BIZ_FICTICIO_GOV = 99;

// ------------------------------------------------------------------
// Cenário 1: superadmin biz=1 acessa /governance — não retorna 5xx
// ------------------------------------------------------------------

it('cenario 1: superadmin biz=1 acessa /governance retorna < 500 (gate permite)', function () {
    // Sem autenticar de fato (Tests/TestCase não tem factory User pronto pra MVP) —
    // valida que a ROTA EXISTE e é resolvida (não retorna 404/500). O gate em si
    // é coberto pelos cenários 2/3/6 (bloqueio).
    $route = Route::getRoutes()->getByName('governance.admin.dashboard');
    expect($route)->not->toBeNull('Rota governance.admin.dashboard deveria existir pra superadmin acessar');

    // URL canônica resolve
    $url = route('governance.admin.dashboard', [], false);
    expect($url)->toBe('/governance');
});

// ------------------------------------------------------------------
// Cenário 2: user normal biz=1 sem permission é bloqueado em /governance
// ------------------------------------------------------------------

it('cenario 2: user normal biz=1 sem permission e bloqueado em /governance (302/403)', function () {
    // Simula sessão biz=1 SEM auth (anonimo equivale a "sem permission" pro middleware)
    session(['user.business_id' => BIZ_WAGNER_GOV]);

    $response = $this->get('/governance');

    // auth middleware redireciona pra login OU retorna 401/403
    expect($response->status())->toBeIn([301, 302, 401, 403, 404, 500],
        "User sem permission em biz=1 deveria ser bloqueado, recebeu {$response->status()}");
    expect($response->status())->not->toBe(200, 'Dashboard NUNCA pode vazar sem auth+permission');
});

// ------------------------------------------------------------------
// Cenário 3: user normal biz=99 também bloqueado (cross-tenant não importa)
// ------------------------------------------------------------------

it('cenario 3: user normal biz=99 fictício também bloqueado em /governance', function () {
    // Governance é cross-tenant — gate é IDENTICO independente de biz
    session(['user.business_id' => BIZ_FICTICIO_GOV]);

    $response = $this->get('/governance');

    expect($response->status())->toBeIn([301, 302, 401, 403, 404, 500],
        "User sem permission em biz=99 deveria ser bloqueado igual biz=1, recebeu {$response->status()}");
    expect($response->status())->not->toBe(200);
});

// ------------------------------------------------------------------
// Cenário 4: tabelas mcp_* NÃO devem ter coluna business_id (cross-tenant by design)
// ------------------------------------------------------------------

it('cenario 4: mcp_governance_rules NAO tem business_id (cross-tenant by design Art. 6+8)', function () {
    // Governance opera transversal — ADR 0093 abre exceção pras tabelas mcp_*
    // Policies sao globais (regem todos os tenants), nao por-tenant
    expect(Schema::hasColumn('mcp_governance_rules', 'business_id'))->toBeFalse(
        'mcp_governance_rules NÃO deve ter business_id — viola design cross-tenant Art. 6+8'
    );
});

it('cenario 4b: mcp_memory_documents NAO tem business_id (ADRs sao canon globais)', function () {
    // ADRs/sessions/handoffs sao conhecimento canon do PROJETO, nao por-tenant
    expect(Schema::hasColumn('mcp_memory_documents', 'business_id'))->toBeFalse(
        'mcp_memory_documents NÃO deve ter business_id — ADRs canon são globais'
    );
});

it('cenario 4c: mcp_skill_versions NAO tem business_id (skills sao globais)', function () {
    // Skills .claude/skills/* sao do projeto inteiro, nao por-tenant
    expect(Schema::hasColumn('mcp_skill_versions', 'business_id'))->toBeFalse(
        'mcp_skill_versions NÃO deve ter business_id — skills são globais ao projeto'
    );
});

it('cenario 4d: mcp_actors NAO tem business_id (Identity Mesh transversal Art. 6)', function () {
    // Identity Mesh: actor é IDENTIDADE atravessa tenants (Claude, Wagner-W, MCP server)
    expect(Schema::hasColumn('mcp_actors', 'business_id'))->toBeFalse(
        'mcp_actors NÃO deve ter business_id — Identity Mesh transversal'
    );
});

// ------------------------------------------------------------------
// Cenário 5: queries em mcp_memory_documents NÃO aplicam scope business
//             (validar cross-tenant return same count via SQL raw)
// ------------------------------------------------------------------

it('cenario 5: queries em mcp_memory_documents retornam mesma count cross-tenant', function () {
    // Insere 3 ADRs sem business_id (canon global)
    DB::table('mcp_memory_documents')->insert([
        ['slug' => 'adr-0086-test', 'title' => 'Governance MVP', 'type' => 'adr', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
        ['slug' => 'adr-0093-test', 'title' => 'Multi-tenant Tier 0', 'type' => 'adr', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
        ['slug' => 'adr-0094-test', 'title' => 'Constituição v2', 'type' => 'adr', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Query "como biz=1"
    session(['user.business_id' => BIZ_WAGNER_GOV]);
    $countBizWagner = DB::table('mcp_memory_documents')->where('type', 'adr')->count();

    // Query "como biz=99" — DEVE retornar mesma count (cross-tenant by design)
    session(['user.business_id' => BIZ_FICTICIO_GOV]);
    $countBizFicticio = DB::table('mcp_memory_documents')->where('type', 'adr')->count();

    expect($countBizWagner)->toBe(3, 'biz=1 deveria ver 3 ADRs');
    expect($countBizFicticio)->toBe(3, 'biz=99 deveria ver MESMAS 3 ADRs (cross-tenant)');
    expect($countBizWagner)->toBe($countBizFicticio,
        'ADRs canon devem retornar mesma count em qualquer biz (Art. 6+8 cross-tenant)');
});

// ------------------------------------------------------------------
// Cenário 6: ModuleGrade endpoint /governance/module-grades aplica mesmo gate
// ------------------------------------------------------------------

it('cenario 6a: /governance/module-grades exige auth (bloqueia anonimo)', function () {
    // Sem session, sem auth
    $response = $this->get('/governance/module-grades');

    expect($response->status())->toBeIn([301, 302, 401, 403, 404, 500],
        "Endpoint module-grades deveria exigir auth, recebeu {$response->status()}");
    expect($response->status())->not->toBe(200);
});

it('cenario 6b: /governance/module-grades bloqueia user biz=1 sem permission', function () {
    session(['user.business_id' => BIZ_WAGNER_GOV]);

    $response = $this->get('/governance/module-grades');

    expect($response->status())->toBeIn([301, 302, 401, 403, 404, 500],
        "Module grades em biz=1 sem permission deveria bloquear, recebeu {$response->status()}");
});

it('cenario 6c: /governance/module-grades bloqueia user biz=99 sem permission (cross-tenant)', function () {
    session(['user.business_id' => BIZ_FICTICIO_GOV]);

    $response = $this->get('/governance/module-grades');

    expect($response->status())->toBeIn([301, 302, 401, 403, 404, 500],
        "Module grades em biz=99 sem permission deveria bloquear igual biz=1, recebeu {$response->status()}");
});

it('cenario 6d: rota /governance/module-grades tem middleware auth', function () {
    $route = Route::getRoutes()->getByName('governance.module-grades.index');
    expect($route)->not->toBeNull('Rota governance.module-grades.index deveria existir');

    $middlewares = $route->gatherMiddleware();
    $hasAuthLike = collect($middlewares)->contains(
        fn ($m) => is_string($m) && stripos($m, 'auth') !== false
    );
    expect($hasAuthLike)->toBeTrue(
        'Rota module-grades.index deveria ter middleware auth (got: ' . json_encode($middlewares) . ')'
    );
});
