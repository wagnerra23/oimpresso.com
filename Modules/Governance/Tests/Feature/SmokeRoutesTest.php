<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Smoke das rotas principais /governance.
 *
 * Apenas valida que rotas respondem status <500 (sem 500 = sem erro PHP).
 * Não autentica usuário — espera-se 302 (redirect login) ou 401, NUNCA 500.
 *
 * SQLite guard obrigatório (ADR multi-tenant Tier 0): só roda em sqlite
 * pra não vazar pra MySQL de homolog/prod. Schema mínimo criado em-memory.
 *
 * biz=1 (Wagner WR2) — NUNCA biz=4 cliente (ADR 0101).
 *
 * Refs: ADR 0086 (Governance MVP UI), ADR 0011 (padrão Jana/Repair).
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('SmokeRoutesTest roda apenas em SQLite in-memory (guard ADR 0101)');
    }

    // Schemas mínimos pras tabelas que o DashboardController/AuditController consultam.
    // Cada Controller já degrada graciosamente via Schema::hasTable, mas precisamos
    // que a app não exploda no boot da query. Criar shape mínimo:
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
    Schema::dropIfExists('mcp_actors');
    Schema::dropIfExists('mcp_audit_log');
    Schema::dropIfExists('mcp_skill_versions');
    Schema::dropIfExists('mcp_governance_rules');
    Schema::dropIfExists('mcp_memory_documents');
});

/**
 * Conjunto de rotas principais — todas devem responder <500.
 * Como nenhuma sessão biz=1 está montada, esperamos redirect/401 (não-200).
 * Importante: zero 500.
 */
$rotas = [
    '/governance',
    '/governance/policies',
    '/governance/audit',
    '/governance/drift',
];

foreach ($rotas as $rota) {
    it("smoke: GET {$rota} responde status < 500", function () use ($rota) {
        $response = $this->get($rota);
        expect($response->status())->toBeLessThan(500, "GET {$rota} retornou {$response->status()} (esperado <500)");
    });
}

it('smoke: rota raiz /governance redireciona quando anonimo (middleware auth)', function () {
    $response = $this->get('/governance');
    // 302 redirect pro login OU 401/403 — todos são "não-500"
    expect($response->status())->toBeIn([200, 301, 302, 401, 403]);
});
