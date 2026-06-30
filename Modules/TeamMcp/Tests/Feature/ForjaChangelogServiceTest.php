<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\TeamMcp\Services\Forja\ForjaChangelogService;

uses(Tests\TestCase::class);

/**
 * Forja · aba Changelog — ForjaChangelogService::build().
 *
 * Projeta "o que shippou" SÓ de fonte real: ADRs/SPECs (mcp_memory_documents) +
 * sessões Claude Code (mcp_cc_sessions), mescladas por data desc. PR/Onda omitidos
 * de propósito (sem dado fantasma — disciplina da Triagem).
 *
 * Cobertura (Onda Forja — código novo sem teste):
 *   1. build() com docs + sessões → lista de entries kind/id/title/actor/date,
 *      ordenada por data desc.
 *   2. build() com tabelas-fonte ausentes → [] (guard Schema::hasTable, sem fantasma).
 *
 * Padrão era-sqlite (espelha AcceptanceRefTest): schema sintético sqlite-friendly.
 * Scout NullEngine (SCOUT_DRIVER=null no phpunit.xml) torna o Searchable do
 * McpMemoryDocument no-op; activitylog não se aplica a essas entidades.
 *
 * @see Modules\TeamMcp\Services\Forja\ForjaChangelogService
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético incompatível com MySQL persistente (floor SDD).');
    }
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return; // era-sqlite: não dropar tabela compartilhada no MySQL persistente (US-GOV-021)
    }
    Schema::dropIfExists('mcp_memory_documents');
    Schema::dropIfExists('mcp_cc_sessions');
});

/** Monta as 2 tabelas-fonte (sqlite-friendly, só as colunas que o Service lê). */
function forjaChangelogBuildSchema(): void
{
    Schema::dropIfExists('mcp_memory_documents');
    Schema::dropIfExists('mcp_cc_sessions');

    Schema::create('mcp_memory_documents', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('slug', 191)->nullable();
        $t->string('type', 40)->nullable();
        $t->string('title', 255)->nullable();
        $t->date('decided_at')->nullable();
        $t->json('decided_by')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('mcp_cc_sessions', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('session_uuid', 64)->nullable();
        $t->string('summary_auto', 500)->nullable();
        $t->string('git_branch', 191)->nullable();
        $t->json('metadata')->nullable();
        $t->timestamp('started_at')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });
}

// -------------------------------------------------------------------------
// 1. build() com fontes reais — SHAPE + ordenação
// -------------------------------------------------------------------------

it('build() devolve entries kind/id/title/actor/date a partir de ADRs + sessões', function () {
    forjaChangelogBuildSchema();

    McpMemoryDocument::create([
        'slug'       => '0114-prototipo-ui-cowork-loop',
        'type'       => 'adr',
        'title'      => 'Loop Cowork formalizado',
        'decided_at' => '2026-05-30',
        'decided_by' => ['Wagner'],
    ]);
    McpCcSession::create([
        'business_id'  => 1,
        'session_uuid' => 'abcdef1234567890',
        'summary_auto' => 'Cockpit Forja — abas restantes',
        'git_branch'   => 'feat/forja-abas-restantes',
        'metadata'     => ['actor' => 'CC'],
        'started_at'   => '2026-06-15 10:00:00',
    ]);

    $rows = app(ForjaChangelogService::class)->build();

    expect($rows)->toBeArray()->not->toBeEmpty();

    foreach ($rows as $row) {
        expect($row)->toHaveKeys(['kind', 'id', 'title', 'actor', 'date']);
        expect($row['kind'])->toBeIn(['adr', 'session']);
        expect($row['id'])->toBeString();
        expect($row['title'])->toBeString()->not->toBe('');
        expect($row['actor'])->toBeString();
        expect($row['date'])->toBeString();
    }

    // Ambas as fontes representadas.
    $kinds = array_column($rows, 'kind');
    expect($kinds)->toContain('adr')->toContain('session');
});

it('build() ordena por data desc (mais recente primeiro)', function () {
    forjaChangelogBuildSchema();

    McpMemoryDocument::create([
        'slug' => '0001-antigo', 'type' => 'adr', 'title' => 'ADR antigo',
        'decided_at' => '2026-01-01', 'decided_by' => ['Wagner'],
    ]);
    McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'sessrecente0001',
        'summary_auto' => 'Sessão recente', 'started_at' => '2026-06-15 09:00:00',
        'metadata' => ['actor' => 'CC'],
    ]);

    $rows = app(ForjaChangelogService::class)->build();
    $datas = array_column($rows, 'date');
    $ordenado = $datas;
    rsort($ordenado);
    expect($datas)->toBe($ordenado, 'changelog deve sair ordenado por data desc');
});

it('build() usa metadata.actor da sessão, senão fallback CL', function () {
    forjaChangelogBuildSchema();

    McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'comactor00000001',
        'summary_auto' => 'Com actor', 'started_at' => '2026-06-14 12:00:00',
        'metadata' => ['actor' => 'CD'],
    ]);
    McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'semactor00000002',
        'summary_auto' => 'Sem actor', 'started_at' => '2026-06-13 12:00:00',
        'metadata' => [],
    ]);

    $rows = collect(app(ForjaChangelogService::class)->build());

    $comActor = $rows->firstWhere('id', 'comactor');
    $semActor = $rows->firstWhere('id', 'semactor');
    expect($comActor['actor'])->toBe('CD');
    expect($semActor['actor'])->toBe('CL', 'sessão sem metadata.actor cai no fallback CL');
});

// -------------------------------------------------------------------------
// 2. build() com fontes ausentes — guard Schema::hasTable → []
// -------------------------------------------------------------------------

it('build() devolve [] quando as tabelas-fonte não existem (sem dado fantasma)', function () {
    // Garante ausência das duas tabelas.
    Schema::dropIfExists('mcp_memory_documents');
    Schema::dropIfExists('mcp_cc_sessions');

    expect(app(ForjaChangelogService::class)->build())->toBe([]);
});
