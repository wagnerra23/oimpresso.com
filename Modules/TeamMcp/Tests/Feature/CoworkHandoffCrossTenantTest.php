<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\CoworkHandoff;

uses(Tests\TestCase::class);

/**
 * Cross-tenant invariant do CoworkHandoff — Loop de Handoff Zero-Paste
 * (Fase 0 · ADR 0283). Espelha o guard #7 de MultiTenantTokenIsolationTest
 * (mcp_actors sem business_id) e o de IngestHeartbeatTest.
 *
 * Handoff de design é artefato do REPO, NÃO dado de tenant: a tabela
 * `cowork_handoffs` é cross-tenant by design (ADR 0093/0283) — SEM business_id e
 * SEM BusinessScope global. Um handoff criado no contexto biz=1 (Wagner-superadmin,
 * nunca biz=4 ROTA LIVRE real — ADR 0101) tem de ser visível também no contexto de
 * um tenant fictício biz=99: é o OPOSTO de isolamento multi-tenant — é
 * compartilhamento deliberado entre tenants.
 *
 * Estes testes TRAVAM a decisão Tier 0: se alguém adicionar business_id/BusinessScope
 * aqui sem ADR mãe nova, o loop Cowork→Code quebra (o handoff some pro Code rodando
 * em outro contexto de business) E o invariante documentado na própria entidade
 * ("NUNCA adicionar global scope de business aqui") é violado. Cross-tenant Tier 0 é
 * o bug pior possível — aqui nos DOIS sentidos (vazar OU sumir indevidamente).
 *
 * @see Modules/TeamMcp/Entities/CoworkHandoff.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0283-loop-handoff-zero-paste.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

/** Tabela sintética sqlite-friendly (espelha a migration; nome único pra não colidir com HandoffIngestTest). */
function coworkHandoffSyntheticTable(): void
{
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }

    Schema::create('cowork_handoffs', function ($t) {
        $t->bigIncrements('id');
        $t->string('slug', 120);
        $t->unsignedInteger('version')->default(1);
        $t->string('tela', 160)->default('');
        $t->string('status', 16)->default('pending');
        $t->string('audited_against', 40)->nullable();
        $t->longText('body_md');
        $t->json('files_json');
        $t->char('source_hash', 64);
        $t->char('sig', 64);
        $t->string('created_by', 40)->default('CC');
        $t->timestamp('created_at')->nullable();
        $t->timestamp('applied_at')->nullable();
        $t->string('applied_by', 60)->nullable();
        $t->text('pr_url')->nullable();
        $t->json('gate_status')->nullable();
        $t->unique(['slug', 'version']);
        $t->index('status');
    });
}

function coworkHandoffRow(string $slug): CoworkHandoff
{
    return CoworkHandoff::create([
        'slug'        => $slug,
        'version'     => 1,
        'tela'        => 'Atendimento/CaixaUnificada',
        'status'      => 'pending',
        'body_md'     => '## handoff '.$slug,
        'files_json'  => ['resources/css/cockpit.css'],
        'source_hash' => str_repeat('a', 64),
        'sig'         => str_repeat('b', 64),
        'created_by'  => 'CC',
        'created_at'  => now(),
    ]);
}

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: tabela sintética cowork_handoffs só roda no sqlite (US-GOV-021)');
    }
    coworkHandoffSyntheticTable();
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return; // era-sqlite: não dropar tabela compartilhada no MySQL persistente (US-GOV-021)
    }
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }
});

// ------------------------------------------------------------------
// 1. Schema — sem business_id (espelha mcp_actors test #7, ADR 0093/0081)
// ------------------------------------------------------------------

it('cowork_handoffs NÃO tem coluna business_id — cross-tenant by design [ADR 0093/0283]', function () {
    expect(Schema::hasColumn('cowork_handoffs', 'business_id'))->toBeFalse(
        'cowork_handoffs NÃO deveria ter business_id — handoff é artefato do repo '.
        '(cross-tenant). business_id aqui = Tier 0 violação sem ADR mãe nova.'
    );
});

// ------------------------------------------------------------------
// 2. Model — sem BusinessScope global nem business_id fillable
// ------------------------------------------------------------------

it('CoworkHandoff não registra BusinessScope global nem expõe business_id', function () {
    $model = new CoworkHandoff;

    foreach (array_keys($model->getGlobalScopes()) as $scopeKey) {
        expect((string) $scopeKey)
            ->not->toContain('BusinessScope');
        expect(strtolower((string) $scopeKey))
            ->not->toContain('business');
    }

    expect($model->getFillable())->not->toContain('business_id');
});

// ------------------------------------------------------------------
// 3. Comportamento — visível através de tenants (biz=1 → biz=99)
// ------------------------------------------------------------------

it('handoff criado no contexto biz=1 é visível no contexto biz=99 (multi-tenant compartilhado)', function () {
    // biz=1 = Wagner-superadmin; NUNCA biz=4 (ROTA LIVRE real — ADR 0101).
    session(['business.id' => 1, 'user.business_id' => 1]);
    $criado = coworkHandoffRow('cross-tenant-probe');

    // Troca pro tenant fictício biz=99 — o handoff DEVE continuar visível (sem scope).
    session(['business.id' => 99, 'user.business_id' => 99]);
    $visivel = CoworkHandoff::where('slug', 'cross-tenant-probe')->first();

    expect($visivel)->not->toBeNull(
        'Handoff sumiu ao trocar de tenant (biz=1 → biz=99) — virou business-scoped. '.
        'Regressão Tier 0: o loop Cowork→Code depende de o handoff ser cross-tenant.'
    );
    expect($visivel->id)->toBe($criado->id);
});

it('handoffs de contextos de business distintos coexistem numa só query (sem partição por tenant)', function () {
    session(['business.id' => 1]);
    coworkHandoffRow('handoff-biz-1');

    session(['business.id' => 99]);
    coworkHandoffRow('handoff-biz-99');

    // Sem BusinessScope, uma query única enxerga AMBOS — prova de não-partição cross-tenant.
    expect(CoworkHandoff::query()->count())->toBe(2);
});
