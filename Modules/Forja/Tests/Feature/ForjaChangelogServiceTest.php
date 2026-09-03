<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Forja\Services\ForjaChangelogService;

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
 *   3. (Onda 9 · PARIDADE §11) shape do `ChangelogFeed` do protótipo: `flags`
 *      filtrado às 2 que o design estiliza, `modules` da coluna real, `date_label`
 *      dd/mm, e título de sessão DERIVADO do 1º prompt — nunca "Sessão Claude Code".
 *
 * Padrão era-sqlite (espelha AcceptanceRefTest): schema sintético sqlite-friendly.
 * Scout NullEngine (SCOUT_DRIVER=null no phpunit.xml) torna o Searchable do
 * McpMemoryDocument no-op; activitylog não se aplica a essas entidades.
 *
 * @see Modules\Forja\Services\ForjaChangelogService
 * @see memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md
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
    Schema::dropIfExists('mcp_cc_messages');
});

/** Monta as 3 tabelas-fonte (sqlite-friendly, só as colunas que o Service lê). */
function forjaChangelogBuildSchema(): void
{
    Schema::dropIfExists('mcp_memory_documents');
    Schema::dropIfExists('mcp_cc_sessions');
    Schema::dropIfExists('mcp_cc_messages');

    Schema::create('mcp_memory_documents', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('slug', 191)->nullable();
        $t->string('type', 40)->nullable();
        $t->string('module', 80)->nullable();
        $t->string('title', 255)->nullable();
        $t->date('decided_at')->nullable();
        $t->json('decided_by')->nullable();
        $t->json('tags')->nullable();
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

    // Fonte do título derivado (Onda 9). Só as colunas que o Service lê.
    Schema::create('mcp_cc_messages', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('session_id');
        $t->string('msg_type', 20)->nullable();
        $t->text('content_text')->nullable();
        $t->timestamp('ts')->nullable();
    });
}

// -------------------------------------------------------------------------
// 1. build() com fontes reais — SHAPE + ordenação
// -------------------------------------------------------------------------

it('UC-FORJA-16 · build() devolve entries no shape do ChangelogFeed a partir de ADRs + sessões', function () {
    forjaChangelogBuildSchema();

    McpMemoryDocument::create([
        'slug'       => '0114-prototipo-ui-cowork-loop',
        'type'       => 'adr',
        'module'     => 'governance',
        'title'      => 'Loop Cowork formalizado',
        'decided_at' => '2026-05-30',
        'decided_by' => ['Wagner'],
        'tags'       => ['design', 'tier-0'],
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
        expect($row)->toHaveKeys(['kind', 'id', 'title', 'actor', 'date', 'date_label', 'flags', 'modules']);
        expect($row['kind'])->toBeIn(['adr', 'session']);
        expect($row['id'])->toBeString();
        expect($row['title'])->toBeString()->not->toBe('');
        expect($row['actor'])->toBeString();
        expect($row['date'])->toBeString();
        expect($row['date_label'])->toBeString();
        expect($row['flags'])->toBeArray();
        expect($row['modules'])->toBeArray();
        // Chave interna do hidratador não pode vazar pro payload Inertia.
        expect($row)->not->toHaveKey('_session_id');
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

// -------------------------------------------------------------------------
// 3. Onda 9 (PARIDADE §11) — os campos que o ChangelogFeed do protótipo desenha
// -------------------------------------------------------------------------

it('UC-FORJA-16 · flags traz só as 2 que o protótipo estiliza (tier-0/breaking) e descarta o resto', function () {
    forjaChangelogBuildSchema();

    McpMemoryDocument::create([
        'slug' => '0093-multi-tenant', 'type' => 'adr', 'title' => 'Multi-tenant Tier 0',
        'decided_at' => '2026-06-10', 'decided_by' => ['W'],
        'tags' => ['whatsapp', 'TIER-0', 'multi-tenant', 'breaking', 'tier-0'],
    ]);
    McpMemoryDocument::create([
        'slug' => '0999-sem-flag', 'type' => 'adr', 'title' => 'Sem flag renderizável',
        'decided_at' => '2026-06-09', 'decided_by' => ['W'],
        'tags' => ['design', 'cowork'],
    ]);

    $rows = collect(app(ForjaChangelogService::class)->build());

    // Case-insensitive, deduplicado, e na ordem em que a tag real aparece.
    expect($rows->firstWhere('id', '0093-multi-tenant')['flags'])->toBe(['tier-0', 'breaking']);
    // Tag real que o design não sabe desenhar não vira selo sem cor.
    expect($rows->firstWhere('id', '0999-sem-flag')['flags'])->toBe([]);
});

it('UC-FORJA-16 · modules vem da coluna module; vazio e a string "null" contam como ausência', function () {
    forjaChangelogBuildSchema();

    McpMemoryDocument::create([
        'slug' => '0001-com-modulo', 'type' => 'adr', 'title' => 'Com módulo',
        'module' => 'Financeiro', 'decided_at' => '2026-06-10', 'decided_by' => ['W'],
    ]);
    McpMemoryDocument::create([
        'slug' => '0002-modulo-null', 'type' => 'adr', 'title' => 'module literal "null"',
        'module' => 'null', 'decided_at' => '2026-06-09', 'decided_by' => ['W'],
    ]);
    McpMemoryDocument::create([
        'slug' => '0003-sem-modulo', 'type' => 'adr', 'title' => 'Sem módulo',
        'module' => null, 'decided_at' => '2026-06-08', 'decided_by' => ['W'],
    ]);

    $rows = collect(app(ForjaChangelogService::class)->build());

    expect($rows->firstWhere('id', '0001-com-modulo')['modules'])->toBe(['Financeiro']);
    expect($rows->firstWhere('id', '0002-modulo-null')['modules'])->toBe([]);
    expect($rows->firstWhere('id', '0003-sem-modulo')['modules'])->toBe([]);
});

it('UC-FORJA-16 · date_label é a MESMA data do campo date, em dd/mm (o que o .fj-feed-when desenha)', function () {
    forjaChangelogBuildSchema();

    McpMemoryDocument::create([
        'slug' => '0264-governanca', 'type' => 'adr', 'title' => 'Governança executável',
        'decided_at' => '2026-06-09', 'decided_by' => ['W'],
    ]);
    McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'datalabel0000001',
        'summary_auto' => 'Sessão com data', 'started_at' => '2026-06-15 10:00:00',
        'metadata' => ['actor' => 'CC'],
    ]);

    $rows = collect(app(ForjaChangelogService::class)->build());

    expect($rows->firstWhere('id', '0264-governanca')['date_label'])->toBe('09/06');
    expect($rows->firstWhere('id', 'datalabe')['date_label'])->toBe('15/06');
    // `date` continua ISO — é ele que ordena e vai pro tooltip.
    expect($rows->firstWhere('id', '0264-governanca')['date'])->toStartWith('2026-06-09');
});

it('UC-FORJA-17 · sessão sem summary_auto herda o 1º prompt do usuário — não a parede "Sessão Claude Code"', function () {
    forjaChangelogBuildSchema();

    $sessao = McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'semsumario000001',
        'summary_auto' => null, 'started_at' => '2026-06-15 10:00:00',
        'metadata' => ['actor' => 'CL'],
    ]);

    // Ordem de inserção != ordem cronológica de propósito: o Service tem que
    // ordenar por `ts`, não pegar o primeiro id.
    DB::table('mcp_cc_messages')->insert([
        ['session_id' => $sessao->id, 'msg_type' => 'assistant', 'content_text' => 'resposta do agente', 'ts' => '2026-06-15 10:00:30'],
        ['session_id' => $sessao->id, 'msg_type' => 'user', 'content_text' => 'segundo prompt do usuário', 'ts' => '2026-06-15 10:05:00'],
        ['session_id' => $sessao->id, 'msg_type' => 'user', 'content_text' => "Forja Onda 9 — Changelog\nigual ao protótipo", 'ts' => '2026-06-15 10:00:00'],
    ]);

    $row = collect(app(ForjaChangelogService::class)->build())->firstWhere('id', 'semsumar');

    expect($row['title'])->toBe('Forja Onda 9 — Changelog igual ao protótipo');
    expect($row['title'])->not->toBe('Sessão Claude Code');
});

it('UC-FORJA-17 · sessão sem summary_auto E sem prompt fica com título VAZIO (empty honesto)', function () {
    forjaChangelogBuildSchema();

    McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'semnada000000001',
        'summary_auto' => '', 'started_at' => '2026-06-15 10:00:00',
        'metadata' => ['actor' => 'CL'],
    ]);

    $row = collect(app(ForjaChangelogService::class)->build())->firstWhere('id', 'semnada0');

    expect($row['title'])->toBe('', 'sem fonte de título, o componente omite o parágrafo — não inventa rótulo');
});

it('UC-FORJA-17 · título derivado do prompt é cortado em 160 chars e tem PII redigida', function () {
    forjaChangelogBuildSchema();

    $longa = McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'promptlongo00001',
        'summary_auto' => null, 'started_at' => '2026-06-15 11:00:00',
        'metadata' => ['actor' => 'CL'],
    ]);
    $comPii = McpCcSession::create([
        'business_id' => 1, 'session_uuid' => 'promptpii0000001',
        'summary_auto' => null, 'started_at' => '2026-06-15 10:00:00',
        'metadata' => ['actor' => 'CL'],
    ]);

    DB::table('mcp_cc_messages')->insert([
        ['session_id' => $longa->id, 'msg_type' => 'user', 'content_text' => str_repeat('palavra ', 60), 'ts' => '2026-06-15 11:00:00'],
        ['session_id' => $comPii->id, 'msg_type' => 'user', 'content_text' => 'manda o retorno pro cliente@exemplo.com.br hoje', 'ts' => '2026-06-15 10:00:00'],
    ]);

    $rows = collect(app(ForjaChangelogService::class)->build());

    $t = $rows->firstWhere('id', 'promptlo')['title'];
    expect(mb_strlen($t))->toBeLessThanOrEqual(161); // 160 + a reticência
    expect($t)->toEndWith('…');

    expect($rows->firstWhere('id', 'promptpi')['title'])
        ->not->toContain('cliente@exemplo.com.br')
        ->toContain('[REDACTED:EMAIL]');
});
