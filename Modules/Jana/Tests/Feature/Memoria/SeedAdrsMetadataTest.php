<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Jana\Console\Commands\SeedAdrsCommand;
use Modules\Jana\Entities\MemoriaFato;

uses(Tests\TestCase::class);

/**
 * GAP seed (auditoria 2026-05-28) — SeedAdrsCommand gravava metadata com chaves
 * ERRADAS (source_type/adr_status/indexed_at), neutralizando o time-decay do
 * MeilisearchDriver (que lê doc_type/status/published_at). Resultado: todo fato
 * de ADR caía no fallback do decay.
 *
 * Cobertura:
 *   - normalizeStatus() PT→EN no vocabulário do config status_multipliers
 *     (accepted/proposed/historical/superseded) + fallback 'default'.
 *   - buildFatoMetadata() grava doc_type/status/published_at corretos.
 *   - Back-compat: chaves antigas continuam presentes.
 *   - resolvePublishedAt() prioriza decided_at > accepted_at > date > indexed_at.
 *
 * Métodos public no command → invocação direta (sem reflection, sem DB, sem LLM).
 */

// ── helper ─────────────────────────────────────────────────────────────────

/**
 * Doc fake no shape que o command recebe do mcp_memory_documents (stdClass).
 */
function seedFakeDoc(string $type = 'adr', string $indexedAt = '2026-01-10 00:00:00'): object
{
    return (object) [
        'id'         => 1,
        'slug'       => '0093-multi-tenant-isolation-tier-0',
        'type'       => $type,
        'title'      => 'Multi-tenant isolation Tier 0',
        'content_md' => '# ADR',
        'metadata'   => '{}',
        'indexed_at' => $indexedAt,
    ];
}

// ── 1. normalizeStatus PT→EN ─────────────────────────────────────────────────

it('normaliza status PT/EN aceito → accepted', function (string $input) {
    $cmd = new SeedAdrsCommand();
    expect($cmd->normalizeStatus($input))->toBe('accepted');
})->with(['aceito', 'aceita', 'Aceito', 'ACCEPTED', 'accepted', 'aceitado']);

it('normaliza status PT/EN proposto → proposed', function (string $input) {
    $cmd = new SeedAdrsCommand();
    expect($cmd->normalizeStatus($input))->toBe('proposed');
})->with(['proposto', 'proposta', 'Proposed', 'PROPOSTO']);

it('normaliza status PT/EN supersede/deprecated/rejected → superseded', function (string $input) {
    $cmd = new SeedAdrsCommand();
    expect($cmd->normalizeStatus($input))->toBe('superseded');
})->with([
    'superseded', 'supersedido', 'supersedida',
    'deprecated', 'depreciado', 'depreciada',
    'rejected', 'rejeitado', 'rejeitada',
    'substituido', 'substituida',
]);

it('normaliza status PT/EN historico → historical', function (string $input) {
    $cmd = new SeedAdrsCommand();
    expect($cmd->normalizeStatus($input))->toBe('historical');
})->with(['historical', 'historico', 'histórico', 'HISTORICAL']);

it('status desconhecido cai em default (multiplier 1.0 no config)', function () {
    $cmd = new SeedAdrsCommand();
    expect($cmd->normalizeStatus('xpto-nao-existe'))->toBe('default');
    expect($cmd->normalizeStatus(''))->toBe('default');
    expect($cmd->normalizeStatus(null))->toBe('default');
});

it('só emite status do vocabulário canônico do config status_multipliers', function () {
    $cmd = new SeedAdrsCommand();
    $vocab = array_keys((array) config('copiloto.time_decay.status_multipliers'));

    foreach (['aceito', 'proposto', 'superseded', 'historico', 'qualquer-coisa'] as $raw) {
        expect($vocab)->toContain($cmd->normalizeStatus($raw));
    }
});

// ── 2. buildFatoMetadata grava as chaves canônicas do time-decay ─────────────

it('grava doc_type/status/published_at corretos pro time-decay ler', function () {
    $cmd  = new SeedAdrsCommand();
    $doc  = seedFakeDoc('adr', '2026-01-10 00:00:00');
    $meta = ['module' => 'Jana', 'decided_at' => '2026-05-06'];

    $built = $cmd->buildFatoMetadata($doc, $meta, 'aceito', null);

    // chaves canônicas que MeilisearchDriver::applyTimeDecay + resolveDocDate leem
    expect($built['doc_type'])->toBe('adr');
    expect($built['status'])->toBe('accepted');          // PT→EN normalizado
    expect($built['published_at'])->toBe('2026-05-06');  // decided_at, NÃO indexed_at
});

it('mantém as chaves antigas pra back-compat', function () {
    $cmd   = new SeedAdrsCommand();
    $doc   = seedFakeDoc('adr');
    $built = $cmd->buildFatoMetadata($doc, ['module' => 'Jana'], 'aceito', 'ADR-0050');

    expect($built)->toHaveKeys([
        'seeded_from_mcp', 'source_type', 'source_slug', 'source_title',
        'adr_status', 'supersedes', 'module', 'indexed_at',
    ]);
    expect($built['source_type'])->toBe('adr');
    expect($built['adr_status'])->toBe('aceito');   // crú preservado (back-compat)
    expect($built['supersedes'])->toBe('ADR-0050');
    expect($built['module'])->toBe('Jana');
});

it('doc_type espelha o tipo cru do doc (spec/reference)', function (string $type) {
    $cmd   = new SeedAdrsCommand();
    $built = $cmd->buildFatoMetadata(seedFakeDoc($type), [], 'aceito', null);
    expect($built['doc_type'])->toBe($type);
})->with(['adr', 'spec', 'reference']);

// ── 3. resolvePublishedAt: prioridade decided_at > accepted_at > date > indexed_at

it('resolvePublishedAt prioriza decided_at', function () {
    $cmd = new SeedAdrsCommand();
    $meta = ['decided_at' => '2026-05-06', 'accepted_at' => '2026-05-07', 'date' => '2026-05-08'];
    expect($cmd->resolvePublishedAt(seedFakeDoc('adr', '2026-01-01 00:00:00'), $meta))
        ->toBe('2026-05-06');
});

it('resolvePublishedAt usa accepted_at quando não tem decided_at', function () {
    $cmd = new SeedAdrsCommand();
    expect($cmd->resolvePublishedAt(seedFakeDoc(), ['accepted_at' => '2026-05-07', 'date' => '2026-05-08']))
        ->toBe('2026-05-07');
});

it('resolvePublishedAt usa date quando não tem decided_at/accepted_at', function () {
    $cmd = new SeedAdrsCommand();
    expect($cmd->resolvePublishedAt(seedFakeDoc(), ['date' => '2026-05-08']))
        ->toBe('2026-05-08');
});

it('resolvePublishedAt cai em indexed_at quando metadata não tem datas', function () {
    $cmd = new SeedAdrsCommand();
    expect($cmd->resolvePublishedAt(seedFakeDoc('adr', '2026-01-10 00:00:00'), []))
        ->toBe('2026-01-10 00:00:00');
});

it('published_at é null quando não há nenhuma data (não crasha o decay)', function () {
    $cmd = new SeedAdrsCommand();
    $doc = seedFakeDoc('adr');
    $doc->indexed_at = null;
    expect($cmd->resolvePublishedAt($doc, []))->toBeNull();
});

// ── 4. reindex Scout: partição ativo (indexa) vs superseded (remove) ─────────
//
// GAP (handoff 2026-05-29): o insert/update do command usa DB::table (raw), o que
// BYPASSA os eventos Eloquent que o Scout escuta — sem reindex explícito o fato
// nunca chegava ao Meilisearch (mesmo com o schedule diário rodando). O fix carrega
// os fatos seedados e particiona: ativos → searchable(), superseded → unsearchable().
// Aqui blindamos a LÓGICA da partição (shouldBeSearchable), que é a parte com risco
// de bug — o searchable()/unsearchable() em si no-opa com SCOUT_DRIVER=null.

it('fato ativo (valid_until null) é indexável no Scout', function () {
    $f = new MemoriaFato();
    $f->valid_until = null;
    expect($f->shouldBeSearchable())->toBeTrue();
});

it('fato superseded (valid_until preenchido) NÃO é indexável (vai pra unsearchable)', function () {
    $f = new MemoriaFato();
    $f->valid_until = Carbon::parse('2026-01-10 00:00:00');
    expect($f->shouldBeSearchable())->toBeFalse();
});

it('filter/reject shouldBeSearchable particiona ativos de superseded (lógica do reindex)', function () {
    $ativo = new MemoriaFato();
    $ativo->valid_until = null;

    $superseded = new MemoriaFato();
    $superseded->valid_until = Carbon::parse('2026-01-10 00:00:00');

    $col = collect([$ativo, $superseded]);

    expect($col->filter->shouldBeSearchable())->toHaveCount(1);   // → ->searchable()
    expect($col->reject->shouldBeSearchable())->toHaveCount(1);   // → ->unsearchable()
});
