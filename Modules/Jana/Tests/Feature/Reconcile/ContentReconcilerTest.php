<?php

declare(strict_types=1);

use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\Reconcilers\ContentReconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;

uses(Tests\TestCase::class);

/**
 * ContentReconciler (ADR 0237) — faceta 'content' do loop jana:reconcile.
 *
 * Garante git (memory/**, ADR 0061) == índice MCP (mcp_memory_documents). Núcleo
 * PURO `analisar(dbDocs, gitShaResolver)` + observação do DB INJETÁVEL = testável
 * SEM DB real e SEM git real. Espelha o padrão do DeployReconcilerTest.
 *
 * ── DB-FIRST (eliminação do phantom-drift) ───────────────────────────────────
 * A faceta itera o OBSERVED (linhas que JÁ estão em mcp_memory_documents) e checa
 * cada uma contra o git via resolver injetado. NÃO enumera o git inteiro — então
 * NÃO existe mais caso "doc do git ausente no DB" (que reportava ~1000+ paths fora
 * da whitelist do healer = phantom-drift). Cobertura == cobertura do healer.
 *
 * Drift coberto: (b) git_sha divergente · (c) updated_at > indexed_at. Iguais =
 * synced. Corpus GLOBAL na leitura (sem business_id).
 *
 * ── ALERTA-ONLY (Tier 0) ─────────────────────────────────────────────────────
 * TODO drift sai healable=false e healedCount é SEMPRE 0: o único healer
 * (IndexarMemoryGitParaDb::run) faz delete global sem business_id (Tier-0-inseguro,
 * ADR 0093). `--heal` é no-op aqui — testes garantem que nenhum healer destrutivo
 * é disparado. Auto-heal seguro = FOLLOW-UP (path-lister compartilhado + escopo
 * business_id no soft-delete).
 */

/**
 * Helper: monta uma linha do DB (observed) chaveada por path.
 *
 * @return array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}
 */
function contentDbDoc(string $path, ?string $sha, ?string $indexedAt, ?string $updatedAt): array
{
    return [
        'git_path' => $path,
        'git_sha' => $sha,
        'indexed_at' => $indexedAt,
        'updated_at' => $updatedAt,
    ];
}

/**
 * Helper: resolver de git_sha por path a partir de um mapa fixo (default = todos
 * synced). Determinístico — substitui o `git log` real. Path ausente do mapa →
 * null (git_sha indeterminado, como no Hostinger sem shell_exec).
 *
 * @param array<string, ?string> $shaPorPath
 * @return \Closure(string): ?string
 */
function contentGitShaResolver(array $shaPorPath): \Closure
{
    return static fn (string $path): ?string => $shaPorPath[$path] ?? null;
}

it('implementa Reconciler, name()=content, registrado em copiloto.reconcilers', function () {
    $r = new ContentReconciler(
        dbDocsObserver: static fn (): array => [],
        gitShaResolver: contentGitShaResolver([]),
    );

    expect($r)->toBeInstanceOf(Reconciler::class)
        ->and($r->name())->toBe('content')
        ->and($r->description())->toBeString()->not->toBe('')
        ->and($r->tags())->toContain('content')
        // o config Jana é merged como copiloto.* (JanaServiceProvider) — ADR 0237
        ->and((array) config('copiloto.reconcilers'))->toContain(ContentReconciler::class);
});

// ── Núcleo puro analisar() ───────────────────────────────────────────────────

it('analisar: git == DB (mesmo sha, indexed >= updated) → synced (sem drift)', function () {
    $dbDocs = ['memory/decisions/0237.md' => contentDbDoc('memory/decisions/0237.md', 'aaa111', '2026-05-30T10:00:00+00:00', '2026-05-30T09:00:00+00:00')];
    $resolver = contentGitShaResolver(['memory/decisions/0237.md' => 'aaa111']);

    expect((new ContentReconciler())->analisar($dbDocs, $resolver))->toBeEmpty();
});

it('analisar (b): git_sha git != git_sha DB → drift alerta-only (healable=false)', function () {
    $dbDocs = ['memory/sessions/s.md' => contentDbDoc('memory/sessions/s.md', 'oldSHA', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00')];
    $resolver = contentGitShaResolver(['memory/sessions/s.md' => 'HEADsha']);

    $drifts = (new ContentReconciler())->analisar($dbDocs, $resolver);

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0])->toBeInstanceOf(ReconcileDrift::class)
        ->and($drifts[0]->target)->toBe('memory/sessions/s.md')
        ->and($drifts[0]->healable)->toBeFalse() // alerta-only (Tier 0)
        ->and($drifts[0]->healed)->toBeFalse()
        ->and($drifts[0]->desired)->toBe('git_sha=HEADsha')
        ->and($drifts[0]->observed)->toBe('git_sha=oldSHA')
        ->and($drifts[0]->detail)->toContain('diverge do HEAD');
});

it('analisar (c): updated_at > indexed_at → drift alerta-only (Scout não re-embeddou)', function () {
    // sha igual, mas updated_at (12:00) > indexed_at (09:00) → DB mudou, índice stale.
    $dbDocs = ['memory/reference/r.md' => contentDbDoc('memory/reference/r.md', 'samesha', '2026-05-30T09:00:00+00:00', '2026-05-30T12:00:00+00:00')];
    $resolver = contentGitShaResolver(['memory/reference/r.md' => 'samesha']);

    $drifts = (new ContentReconciler())->analisar($dbDocs, $resolver);

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0]->target)->toBe('memory/reference/r.md')
        ->and($drifts[0]->healable)->toBeFalse() // alerta-only (Tier 0)
        ->and($drifts[0]->detail)->toContain('updated_at > indexed_at');
});

it('analisar: sha null em git OU DB → NÃO inventa drift de sha (Hostinger sem shell_exec)', function () {
    // git_sha null (resolver devolve null) + mesmo conteúdo lógico, indexed >= updated.
    $dbDocs = ['memory/x.md' => contentDbDoc('memory/x.md', 'qualquer', '2026-05-30T10:00:00+00:00', '2026-05-30T08:00:00+00:00')];
    $resolverNull = contentGitShaResolver(['memory/x.md' => null]); // git_sha indeterminado

    expect((new ContentReconciler())->analisar($dbDocs, $resolverNull))->toBeEmpty();

    // E o simétrico: DB sem sha, git com sha → também não compara sha.
    $dbDocs2 = ['memory/x.md' => contentDbDoc('memory/x.md', null, '2026-05-30T10:00:00+00:00', '2026-05-30T08:00:00+00:00')];
    $resolver2 = contentGitShaResolver(['memory/x.md' => 'HEADsha']);

    expect((new ContentReconciler())->analisar($dbDocs2, $resolver2))->toBeEmpty();
});

it('analisar: DB-FIRST — doc no git que NÃO está no índice é IGNORADO (sem phantom-drift)', function () {
    // O resolver "conhece" um doc do git que o DB não tem (ex memory/clientes/** que
    // o healer exclui por LGPD). Como iteramos o OBSERVED (DB), esse path nunca é
    // checado → zero drift. É exatamente o phantom-drift que a versão antiga gerava.
    $dbDocs = []; // índice vazio
    $resolver = contentGitShaResolver([
        'memory/clientes/larissa/contrato.md' => 'shaPII',  // git tem, índice (corretamente) não
        'memory/feedback/2026-05.md' => 'shaFeedback',
    ]);

    expect((new ContentReconciler())->analisar($dbDocs, $resolver))->toBeEmpty();
});

it('analisar: 1 drift por path; um path em drift não mascara o outro; ordenado por path', function () {
    $dbDocs = [
        'memory/b.md' => contentDbDoc('memory/b.md', 'oldB', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00'),  // (b) sha divergente
        'memory/c.md' => contentDbDoc('memory/c.md', 'okC', '2026-05-30T10:00:00+00:00', '2026-05-30T09:00:00+00:00'),   // synced
        'memory/a.md' => contentDbDoc('memory/a.md', 'okA', '2026-05-30T09:00:00+00:00', '2026-05-30T12:00:00+00:00'),   // (c) updated>indexed
    ];
    $resolver = contentGitShaResolver([
        'memory/b.md' => 'newB', // diverge de oldB → (b)
        'memory/c.md' => 'okC',  // igual → synced
        'memory/a.md' => 'okA',  // sha igual, mas updated>indexed → (c)
    ]);

    $drifts = (new ContentReconciler())->analisar($dbDocs, $resolver);

    // 2 drifts (a.md updated>indexed + b.md sha); c.md synced. Ordenado por path: a, b.
    expect($drifts)->toHaveCount(2)
        ->and($drifts[0]->target)->toBe('memory/a.md')
        ->and($drifts[1]->target)->toBe('memory/b.md')
        ->and($drifts[0]->healable)->toBeFalse()
        ->and($drifts[1]->healable)->toBeFalse();
});

// ── reconcile() com observação injetada ──────────────────────────────────────

it('reconcile: observações injetadas em sync → ReconcileResult inSync (driftCount 0)', function () {
    $r = new ContentReconciler(
        dbDocsObserver: static fn (): array => ['memory/decisions/0237.md' => contentDbDoc('memory/decisions/0237.md', 'sha', '2026-05-30T10:00:00+00:00', '2026-05-30T09:00:00+00:00')],
        gitShaResolver: contentGitShaResolver(['memory/decisions/0237.md' => 'sha']),
    );

    $result = $r->reconcile();

    expect($result)->toBeInstanceOf(ReconcileResult::class)
        ->and($result->name)->toBe('content')
        ->and($result->inSync)->toBeTrue()
        ->and($result->driftCount)->toBe(0)
        ->and($result->healedCount)->toBe(0)
        // alerta-only: heal desligado por enquanto (Tier 0 — ver docblock).
        ->and($result->metadata['heal_supported'] ?? null)->toBeFalse()
        ->and($result->metadata['coverage'] ?? null)->toBe('db_first')
        ->and($result->metadata['corpus'] ?? null)->toBe('global'); // cross-tenant na leitura by design
});

it('reconcile: drift detectado SEM --heal → reporta (alerta), não cura, healedCount 0', function () {
    $r = new ContentReconciler(
        dbDocsObserver: static fn (): array => ['memory/d.md' => contentDbDoc('memory/d.md', 'oldD', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00')],
        gitShaResolver: contentGitShaResolver(['memory/d.md' => 'newD']), // sha divergente → drift (b)
    );

    $result = $r->reconcile(); // sem heal

    expect($result->inSync)->toBeFalse()
        ->and($result->driftCount)->toBe(1)
        ->and($result->healedCount)->toBe(0)
        ->and($result->drifts[0]->healable)->toBeFalse()
        ->and($result->drifts[0]->healed)->toBeFalse();
});

it('reconcile: --heal NÃO dispara o healer destrutivo (alerta-only) e healedCount fica 0', function () {
    // Regressão Tier-0 (ADR 0093): o healer (IndexarMemoryGitParaDb::run) faz delete
    // global sem business_id. A faceta NÃO o injeta mais nem o dispara. Garantimos
    // que --heal NÃO cura (drifts seguem healable=false, healed=false, count 0).
    $r = new ContentReconciler(
        dbDocsObserver: static fn (): array => [
            'memory/a.md' => contentDbDoc('memory/a.md', 'oldA', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00'),
            'memory/b.md' => contentDbDoc('memory/b.md', 'sameB', '2026-05-30T09:00:00+00:00', '2026-05-30T12:00:00+00:00'),
        ],
        gitShaResolver: contentGitShaResolver([
            'memory/a.md' => 'newA',  // (b) sha divergente
            'memory/b.md' => 'sameB', // (c) updated>indexed
        ]),
    );

    $result = $r->reconcile(['heal' => true]);

    expect($result->driftCount)->toBe(2)
        ->and($result->healedCount)->toBe(0)              // alerta-only: NADA é curado
        ->and($result->drifts[0]->healed)->toBeFalse()
        ->and($result->drifts[1]->healed)->toBeFalse()
        ->and($result->drifts[0]->healable)->toBeFalse()
        ->and($result->drifts[1]->healable)->toBeFalse()
        ->and($result->metadata['heal_supported'] ?? null)->toBeFalse()
        ->and($result->metadata['healed_docs'] ?? null)->toBe(0)
        ->and($result->metadata['heal_blocked_reason'] ?? null)->toContain('business_id');
});

it('reconcile: --dry-run com --heal também não escreve nada (alerta-only, healedCount 0)', function () {
    $r = new ContentReconciler(
        dbDocsObserver: static fn (): array => ['memory/d.md' => contentDbDoc('memory/d.md', 'oldD', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00')],
        gitShaResolver: contentGitShaResolver(['memory/d.md' => 'newD']), // drift (b)
    );

    $result = $r->reconcile(['heal' => true, 'dry_run' => true]);

    expect($result->driftCount)->toBe(1)
        ->and($result->healedCount)->toBe(0)
        ->and($result->drifts[0]->healed)->toBeFalse();
});

it('reconcile é idempotente: 2× com as mesmas observações = mesmo resultado', function () {
    $r = new ContentReconciler(
        dbDocsObserver: static fn (): array => ['memory/b.md' => contentDbDoc('memory/b.md', 'oldB', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00')],
        gitShaResolver: contentGitShaResolver(['memory/b.md' => 'newB']),
    );

    $a = $r->reconcile();
    $b = $r->reconcile();

    expect($a->inSync)->toBe($b->inSync)
        ->and($a->driftCount)->toBe($b->driftCount)
        ->and($a->healedCount)->toBe($b->healedCount)
        ->and($a->drifts[0]->toArray())->toBe($b->drifts[0]->toArray());
});
