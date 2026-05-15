<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Jobs\Mcp\ReindexarDocumentoJob;
use Modules\Jana\Services\Memoria\Freshness\ReindexJobDispatcher;
use Modules\Jana\Services\Memoria\Freshness\StalenessDetectorService;

uses(Tests\TestCase::class);

/**
 * GAP D7 #2 (auditoria memoria-senior 2026-05-15) — Pest Freshness Pipeline.
 *
 * Cobertura (8 cenários):
 *  1. doc indexed_at 2h atrás → FRESH
 *  2. doc indexed_at 5d atrás → WARM
 *  3. doc indexed_at 15d atrás → STALE
 *  4. doc indexed_at 60d atrás → CRITICAL + alerta mcp_alertas_eventos
 *  5. doc com updated_at > indexed_at → DRIFT (DB tipo)
 *  6. ReindexJobDispatcher respeita --limit
 *  7. Idempotência alerta CRITICAL (mesmo dia não duplica)
 *  8. Contagem por nível (% saúde)
 *
 * Multi-tenant Tier 0: `mcp_memory_documents` é cross-tenant (sem business_id);
 * Pest valida que detector não tenta filtrar por scope errado.
 * Pest usa biz=1 (ADR 0101 — biz=cliente proibido).
 */

beforeEach(function () {
    // mcp_memory_documents (mesmo schema da migration canônica, mas sem FULLTEXT
    // pra rodar em sqlite :memory: do phpunit.xml — ADR 0101).
    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('slug', 200)->unique();
        $t->string('type', 30);
        $t->string('module', 50)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->string('scope_required', 100)->nullable();
        $t->boolean('admin_only')->default(false);
        $t->json('metadata')->nullable();
        $t->string('git_sha', 40)->nullable();
        $t->string('git_path', 300)->nullable();
        $t->unsignedSmallInteger('pii_redactions_count')->default(0);
        $t->binary('embedding')->nullable();
        $t->timestamp('indexed_at')->nullable();
        $t->string('status', 50)->nullable();
        $t->string('authority', 50)->nullable();
        $t->string('lifecycle', 50)->nullable();
        $t->string('quarter', 10)->nullable();
        $t->date('decided_at')->nullable();
        $t->json('decided_by')->nullable();
        $t->json('tags')->nullable();
        $t->json('supersedes')->nullable();
        $t->json('superseded_by')->nullable();
        $t->json('related')->nullable();
        $t->boolean('has_pii')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });

    // mcp_alertas_eventos (subset suficiente — schema canônico da migration).
    Schema::create('mcp_alertas_eventos', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('user_id')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->string('tipo', 50);
        $t->string('severidade', 20)->default('medium');
        $t->string('titulo', 200);
        $t->text('descricao')->nullable();
        $t->string('chave_idempotencia', 200)->unique();
        $t->json('metadata')->nullable();
        $t->enum('status', ['aberto', 'notificado', 'ack', 'arquivado'])->default('aberto');
        $t->timestamp('criado_em')->useCurrent();
        $t->timestamp('notificado_em')->nullable();
        $t->timestamp('ack_em')->nullable();
        $t->unsignedInteger('ack_by_user_id')->nullable();
        $t->timestamps();
    });

    // Defaults config jana freshness (ADR canon thresholds)
    config()->set('copiloto.freshness.enabled', true);
    config()->set('copiloto.freshness.thresholds_days.fresh', 1);
    config()->set('copiloto.freshness.thresholds_days.warm', 7);
    config()->set('copiloto.freshness.thresholds_days.stale', 30);
});

afterEach(function () {
    Schema::dropIfExists('mcp_alertas_eventos');
    Schema::dropIfExists('mcp_memory_documents');
});

// ─── helpers ────────────────────────────────────────────────────────────────

function freshFazDoc(string $slug, ?\DateTimeInterface $indexedAt, array $extras = []): McpMemoryDocument
{
    // withoutSyncingToSearch evita disparar Scout/Meilisearch em testes
    return McpMemoryDocument::withoutSyncingToSearch(function () use ($slug, $indexedAt, $extras) {
        $doc = McpMemoryDocument::create(array_merge([
            'business_id' => 1, // ADR 0101 — Pest sempre biz=1
            'slug'        => $slug,
            'type'        => 'adr',
            'title'       => "Doc {$slug}",
            'content_md'  => "Conteúdo {$slug}",
            'git_path'    => "memory/decisions/{$slug}.md",
            'git_sha'     => 'abc123',
            'indexed_at'  => $indexedAt,
        ], $extras));
        return $doc;
    });
}

// ─── testes ─────────────────────────────────────────────────────────────────

it('classifica doc com indexed_at 2h atrás como FRESH', function () {
    $doc = freshFazDoc('fresh-doc', now()->subHours(2));

    $detector = new StalenessDetectorService();

    expect($detector->staleness($doc))->toBe(StalenessDetectorService::NIVEL_FRESH);
});

it('classifica doc com indexed_at 5d atrás como WARM', function () {
    $doc = freshFazDoc('warm-doc', now()->subDays(5));

    $detector = new StalenessDetectorService();

    expect($detector->staleness($doc))->toBe(StalenessDetectorService::NIVEL_WARM);
});

it('classifica doc com indexed_at 15d atrás como STALE', function () {
    $doc = freshFazDoc('stale-doc', now()->subDays(15));

    $detector = new StalenessDetectorService();

    expect($detector->staleness($doc))->toBe(StalenessDetectorService::NIVEL_STALE);
});

it('classifica doc com indexed_at 60d atrás como CRITICAL e dispara alerta idempotente', function () {
    $doc = freshFazDoc('critical-doc', now()->subDays(60));

    $detector = new StalenessDetectorService();

    expect($detector->staleness($doc))->toBe(StalenessDetectorService::NIVEL_CRITICAL);

    $critical = $detector->detectCritical();
    expect($critical)->toHaveCount(1);
    expect($critical[0]->slug)->toBe('critical-doc');

    $inseridos = $detector->alertCritical($critical);
    expect($inseridos)->toBe(1);

    $alerta = DB::table('mcp_alertas_eventos')->where('tipo', 'memory_staleness')->first();
    expect($alerta)->not->toBeNull();
    expect($alerta->severidade)->toBe('high');
    expect($alerta->business_id)->toBeNull(); // repo-wide, cross-tenant
});

it('idempotência alerta CRITICAL: segunda chamada no mesmo dia não duplica', function () {
    $doc = freshFazDoc('idem-critical', now()->subDays(45));

    $detector = new StalenessDetectorService();
    $critical = $detector->detectCritical();

    $primeiro = $detector->alertCritical($critical);
    $segundo  = $detector->alertCritical($critical);

    expect($primeiro)->toBe(1);
    expect($segundo)->toBe(0); // mesmo dia → chave idempotencia bloqueia
    expect(DB::table('mcp_alertas_eventos')->count())->toBe(1);
});

it('detecta DRIFT quando updated_at > indexed_at', function () {
    $doc = freshFazDoc('drift-doc', now()->subDays(2));
    // Força updated_at posterior ao indexed_at (Eloquent normalmente bumpa juntos)
    McpMemoryDocument::withoutSyncingToSearch(function () use ($doc) {
        DB::table('mcp_memory_documents')
            ->where('id', $doc->id)
            ->update(['updated_at' => now()]);
    });

    $detector = new StalenessDetectorService();
    $drift = $detector->detectDrift();

    expect($drift)->toHaveCount(1);
    expect($drift[0]->slug)->toBe('drift-doc');
});

it('ReindexJobDispatcher respeita --limit e enfileira no queue jana-index', function () {
    // Cria 5 stale + 3 drift (1 overlap)
    for ($i = 1; $i <= 5; $i++) {
        freshFazDoc("stale-{$i}", now()->subDays(15));
    }
    for ($i = 1; $i <= 3; $i++) {
        $doc = freshFazDoc("drift-{$i}", now()->subDays(2));
        DB::table('mcp_memory_documents')
            ->where('id', $doc->id)
            ->update(['updated_at' => now()]);
    }

    Queue::fake();

    $detector = new StalenessDetectorService();
    $dispatcher = new ReindexJobDispatcher($detector);

    $dispatched = $dispatcher->dispatchStaleAndDrift(limit: 4);

    expect($dispatched)->toBe(4);
    Queue::assertPushedOn('jana-index', ReindexarDocumentoJob::class);
});

it('contagemPorNivel retorna distribuição FRESH/WARM/STALE/CRITICAL coerente', function () {
    freshFazDoc('f1', now()->subHours(2));   // FRESH
    freshFazDoc('f2', now()->subHours(12));  // FRESH
    freshFazDoc('w1', now()->subDays(3));    // WARM
    freshFazDoc('s1', now()->subDays(15));   // STALE
    freshFazDoc('c1', now()->subDays(60));   // CRITICAL
    freshFazDoc('c2', null);                  // CRITICAL (nunca indexed)

    $detector = new StalenessDetectorService();
    $contagem = $detector->contagemPorNivel();

    expect($contagem['FRESH'])->toBe(2);
    expect($contagem['WARM'])->toBe(1);
    expect($contagem['STALE'])->toBe(1);
    expect($contagem['CRITICAL'])->toBe(2);
    expect($contagem['total'])->toBe(6);
});

it('doc com indexed_at NULL é CRITICAL (nunca foi indexado)', function () {
    $doc = freshFazDoc('never-indexed', null);

    $detector = new StalenessDetectorService();

    expect($detector->staleness($doc))->toBe(StalenessDetectorService::NIVEL_CRITICAL);

    $stale = $detector->detectStale();
    expect($stale)->toHaveCount(1);
});
