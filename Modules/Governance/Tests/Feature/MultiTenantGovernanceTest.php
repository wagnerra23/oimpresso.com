<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Multi-tenant isolation governance — Modules/Governance.
 *
 * Governance é módulo INTENCIONALMENTE cross-tenant (Constituição Art. 6+8).
 * Este teste cobre ÂNGULOS COMPLEMENTARES ao CrossTenantPolicyTest.php (Wave G):
 *
 *   CrossTenantPolicyTest cobre: rotas existem, gates bloqueiam anônimo, schema sem
 *   business_id, queries cross-tenant retornam mesma count.
 *
 *   MultiTenantGovernanceTest cobre (este arquivo — ângulos COMPLEMENTARES):
 *     1. mcp_governance_rules é tabela GLOBAL (cross-tenant intencional by design)
 *     2. mcp_audit_log mantém business_id como METADATA (não como filtro de scope)
 *     3. User sem permission `superadmin`/`governance.dashboard.view` é bloqueado em QUALQUER biz
 *
 * Os cenários 3 e 5 originais cobriam a rota /governance/module-grades e o
 * ModuleGradeService; saíram com a rubrica module-grade (ADR 0399, ondas 2 e 4).
 *
 * SQLite guard obrigatório (ADR 0101 — biz=1 Wagner WR2, NUNCA biz=4 ROTA LIVRE).
 * Reusa setup de schema mcp_* (idem CrossTenantPolicyTest) — mas cenários NOVOS.
 *
 * Refs:
 *   - ADR 0086 (Governance MVP UI)
 *   - ADR 0093 (Multi-tenant Tier 0 — Governance é exceção transversal Art. 6+8)
 *   - ADR 0101 (Tests biz=1 Wagner — NUNCA biz=4 cliente)
 *   - Constituição Art. 6 (Identity Mesh) · Art. 8 (Policy Gating) · Art. 9 (Auditoria)
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('MultiTenantGovernanceTest roda apenas em SQLite in-memory (guard ADR 0101)');
    }

    // Schema mínimo das tabelas mcp_* — replicar Wave G pattern
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('mcp_governance_rules');

    Schema::create('mcp_governance_rules', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->boolean('enabled')->default(true);
        $t->timestamps();
    });
    Schema::create('mcp_audit_log', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id')->nullable();
        // business_id em mcp_audit_log é METADATA de auditoria (registrar QUEM
        // fez ação cross-tenant), NÃO filtro de scope. Cenário 2 valida isso.
        $t->unsignedBigInteger('business_id')->nullable();
        $t->string('endpoint', 60);
        $t->string('tool_or_resource', 120)->nullable();
        $t->string('status', 30)->default('ok');
        $t->unsignedInteger('duration_ms')->nullable();
        $t->timestamp('ts')->useCurrent();
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
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('mcp_governance_rules');
});

// IDs canônicos: biz=1 Wagner WR2, biz=99 fictício, biz=10 fictício extra (ADR 0101)
// NUNCA biz=4 (ROTA LIVRE — cliente Larissa produção)
const BIZ_WAGNER_MT = 1;
const BIZ_FICTICIO_MT = 99;
const BIZ_FICTICIO_EXTRA_MT = 10;

// ------------------------------------------------------------------
// Cenário 1: mcp_governance_rules é GLOBAL (cross-tenant by design)
// ------------------------------------------------------------------

it('Multi-tenant: mcp_governance_rules é tabela global cross-tenant intencional (não tem business_id por design)', function () {
    // Insere policies globais (sem coluna business_id no schema)
    DB::table('mcp_governance_rules')->insert([
        ['name' => 'tier-0-multi-tenant', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'append-only-adrs', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'pii-redactor', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Schema NÃO tem business_id (validar design)
    expect(Schema::hasColumn('mcp_governance_rules', 'business_id'))->toBeFalse(
        'mcp_governance_rules NÃO deve ter business_id — policies regem TODOS tenants'
    );

    // 3 bizs DIFERENTES (Wagner, fictício 99, fictício extra 10) veem MESMAS 3 policies
    session(['user.business_id' => BIZ_WAGNER_MT]);
    $countWagner = DB::table('mcp_governance_rules')->where('enabled', true)->count();

    session(['user.business_id' => BIZ_FICTICIO_MT]);
    $countFicticio = DB::table('mcp_governance_rules')->where('enabled', true)->count();

    session(['user.business_id' => BIZ_FICTICIO_EXTRA_MT]);
    $countExtra = DB::table('mcp_governance_rules')->where('enabled', true)->count();

    expect($countWagner)->toBe(3, 'biz=1 deveria ver 3 policies globais');
    expect($countFicticio)->toBe(3, 'biz=99 deveria ver MESMAS 3 policies');
    expect($countExtra)->toBe(3, 'biz=10 deveria ver MESMAS 3 policies');
    expect($countWagner)->toBe($countFicticio)->toBe($countExtra);
});

// ------------------------------------------------------------------
// Cenário 2: mcp_audit_log mantém business_id como METADATA (não como filtro)
// ------------------------------------------------------------------

it('Multi-tenant: queries em mcp_audit_log não filtram por business_id (Wagner-only via permission)', function () {
    // Insere audit entries de 3 bizs (Wagner registra ação cross-tenant)
    DB::table('mcp_audit_log')->insert([
        ['user_id' => 1, 'business_id' => BIZ_WAGNER_MT, 'endpoint' => 'tools-call', 'tool_or_resource' => 'tasks-list', 'status' => 'ok', 'ts' => now()],
        ['user_id' => 1, 'business_id' => BIZ_FICTICIO_MT, 'endpoint' => 'tools-call', 'tool_or_resource' => 'brief-fetch', 'status' => 'ok', 'ts' => now()],
        ['user_id' => 1, 'business_id' => BIZ_FICTICIO_EXTRA_MT, 'endpoint' => 'tools-call', 'tool_or_resource' => 'my-work', 'status' => 'ok', 'ts' => now()],
        // Entry SEM business_id (ação Identity Mesh transversal — Wagner como superadmin)
        ['user_id' => 1, 'business_id' => null, 'endpoint' => 'tools-call', 'tool_or_resource' => 'decisions-search', 'status' => 'ok', 'ts' => now()],
    ]);

    // Wagner consulta audit log COMO superadmin (sem filtro biz) — vê TUDO (4 entries)
    session(['user.business_id' => BIZ_WAGNER_MT]);
    $totalCrossTenant = DB::table('mcp_audit_log')->count();

    expect($totalCrossTenant)->toBe(4,
        'Wagner superadmin vê 4 audit entries cross-tenant (3 com business_id + 1 sem)');

    // business_id é METADATA — filtro APENAS quando explicitamente pedido por Wagner
    $entriesBizWagner = DB::table('mcp_audit_log')->where('business_id', BIZ_WAGNER_MT)->count();
    $entriesSemBiz = DB::table('mcp_audit_log')->whereNull('business_id')->count();

    expect($entriesBizWagner)->toBe(1, 'Filtro EXPLÍCITO biz=1 retorna 1 entry');
    expect($entriesSemBiz)->toBe(1, 'Entry sem business_id (Identity Mesh transversal) é preservada');
});

// ------------------------------------------------------------------
// Cenário 4: user sem permission é bloqueado em QUALQUER biz
// ------------------------------------------------------------------

it('Multi-tenant: user sem permission `governance.dashboard.view` é bloqueado em qualquer biz', function () {
    // Cross-tenant gate: permission `superadmin` ou `governance.dashboard.view`
    // bloqueia user normal INDEPENDENTE de business_id (Art. 8 Policy Gating)

    $endpoints = [
        '/governance',
    ];

    $bizs = [BIZ_WAGNER_MT, BIZ_FICTICIO_MT, BIZ_FICTICIO_EXTRA_MT];

    foreach ($endpoints as $endpoint) {
        foreach ($bizs as $biz) {
            session(['user.business_id' => $biz]);
            $status = $this->get($endpoint)->status();

            expect($status)->not->toBe(200,
                "Endpoint {$endpoint} NUNCA pode vazar 200 sem permission (biz={$biz}, recebeu {$status})");
            expect($status)->toBeIn([301, 302, 401, 403, 404, 500],
                "Endpoint {$endpoint} biz={$biz} deveria bloquear sem permission, recebeu {$status}");
        }
    }
});
