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
 * puro `analisar(gitDocs, dbDocs)` + observação do DB INJETÁVEL = testável SEM DB
 * real e SEM git real. Espelha o padrão do DeployReconcilerTest.
 *
 * Drift coberto: (a) doc git ausente no DB · (b) git_sha divergente ·
 * (c) updated_at > indexed_at. Iguais = synced. Corpus GLOBAL (sem business_id).
 */

/**
 * Helper: monta um doc do git (desired) chaveado por path.
 *
 * @return array{git_path: string, git_sha: ?string}
 */
function contentGitDoc(string $path, ?string $sha): array
{
    return ['git_path' => $path, 'git_sha' => $sha];
}

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

it('implementa Reconciler, name()=content, registrado em copiloto.reconcilers', function () {
    $r = new ContentReconciler(
        gitDocsObserver: static fn (): array => [],
        dbDocsObserver: static fn (): array => [],
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
    $gitDocs = ['memory/decisions/0237.md' => contentGitDoc('memory/decisions/0237.md', 'aaa111')];
    $dbDocs = ['memory/decisions/0237.md' => contentDbDoc('memory/decisions/0237.md', 'aaa111', '2026-05-30T10:00:00+00:00', '2026-05-30T09:00:00+00:00')];

    expect((new ContentReconciler())->analisar($gitDocs, $dbDocs))->toBeEmpty();
});

it('analisar (a): doc do git ausente no DB → drift healable', function () {
    $gitDocs = ['memory/decisions/0999.md' => contentGitDoc('memory/decisions/0999.md', 'newdoc')];
    $dbDocs = []; // DB vazio — nunca indexado

    $drifts = (new ContentReconciler())->analisar($gitDocs, $dbDocs);

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0])->toBeInstanceOf(ReconcileDrift::class)
        ->and($drifts[0]->target)->toBe('memory/decisions/0999.md')
        ->and($drifts[0]->healable)->toBeTrue()
        ->and($drifts[0]->healed)->toBeFalse()
        ->and($drifts[0]->observed)->toBe('ausente no DB')
        ->and($drifts[0]->detail)->toContain('ausente no índice MCP');
});

it('analisar (b): git_sha git != git_sha DB → drift healable (sync silenciou)', function () {
    $gitDocs = ['memory/sessions/s.md' => contentGitDoc('memory/sessions/s.md', 'HEADsha')];
    $dbDocs = ['memory/sessions/s.md' => contentDbDoc('memory/sessions/s.md', 'oldSHA', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00')];

    $drifts = (new ContentReconciler())->analisar($gitDocs, $dbDocs);

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0]->target)->toBe('memory/sessions/s.md')
        ->and($drifts[0]->healable)->toBeTrue()
        ->and($drifts[0]->desired)->toBe('git_sha=HEADsha')
        ->and($drifts[0]->observed)->toBe('git_sha=oldSHA')
        ->and($drifts[0]->detail)->toContain('diverge do HEAD');
});

it('analisar (c): updated_at > indexed_at → drift healable (Scout não re-embeddou)', function () {
    $gitDocs = ['memory/reference/r.md' => contentGitDoc('memory/reference/r.md', 'samesha')];
    // sha igual, mas updated_at (12:00) > indexed_at (09:00) → DB mudou, índice stale.
    $dbDocs = ['memory/reference/r.md' => contentDbDoc('memory/reference/r.md', 'samesha', '2026-05-30T09:00:00+00:00', '2026-05-30T12:00:00+00:00')];

    $drifts = (new ContentReconciler())->analisar($gitDocs, $dbDocs);

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0]->target)->toBe('memory/reference/r.md')
        ->and($drifts[0]->healable)->toBeTrue()
        ->and($drifts[0]->detail)->toContain('updated_at > indexed_at');
});

it('analisar: sha null em git OU DB → NÃO inventa drift de sha (Hostinger sem shell_exec)', function () {
    // git_sha null (shell_exec ausente) + mesmo conteúdo lógico, indexed >= updated.
    $gitDocs = ['memory/x.md' => contentGitDoc('memory/x.md', null)];
    $dbDocs = ['memory/x.md' => contentDbDoc('memory/x.md', 'qualquer', '2026-05-30T10:00:00+00:00', '2026-05-30T08:00:00+00:00')];

    expect((new ContentReconciler())->analisar($gitDocs, $dbDocs))->toBeEmpty();

    // E o simétrico: DB sem sha.
    $gitDocs2 = ['memory/x.md' => contentGitDoc('memory/x.md', 'HEADsha')];
    $dbDocs2 = ['memory/x.md' => contentDbDoc('memory/x.md', null, '2026-05-30T10:00:00+00:00', '2026-05-30T08:00:00+00:00')];

    expect((new ContentReconciler())->analisar($gitDocs2, $dbDocs2))->toBeEmpty();
});

it('analisar: path só no DB (órfão, não está no git) → fora do escopo (sem drift)', function () {
    $gitDocs = []; // git não tem o doc
    $dbDocs = ['memory/antigo.md' => contentDbDoc('memory/antigo.md', 'sha', '2026-05-30T10:00:00+00:00', '2026-05-30T09:00:00+00:00')];

    // O sync já soft-deleta órfãos; esta faceta não reporta path-só-no-DB.
    expect((new ContentReconciler())->analisar($gitDocs, $dbDocs))->toBeEmpty();
});

it('analisar: 1 drift por path; um path em drift não mascara o outro; ordenado por path', function () {
    $gitDocs = [
        'memory/b.md' => contentGitDoc('memory/b.md', 'newB'),  // (b) sha divergente
        'memory/a.md' => contentGitDoc('memory/a.md', 'okA'),   // (a) ausente no DB
        'memory/c.md' => contentGitDoc('memory/c.md', 'okC'),   // synced
    ];
    $dbDocs = [
        'memory/b.md' => contentDbDoc('memory/b.md', 'oldB', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00'),
        'memory/c.md' => contentDbDoc('memory/c.md', 'okC', '2026-05-30T10:00:00+00:00', '2026-05-30T09:00:00+00:00'),
        // a.md ausente
    ];

    $drifts = (new ContentReconciler())->analisar($gitDocs, $dbDocs);

    // 2 drifts (a.md ausente + b.md sha); c.md synced. Ordenado por path: a, b.
    expect($drifts)->toHaveCount(2)
        ->and($drifts[0]->target)->toBe('memory/a.md')
        ->and($drifts[1]->target)->toBe('memory/b.md');
});

// ── reconcile() com observação injetada ──────────────────────────────────────

it('reconcile: observações injetadas em sync → ReconcileResult inSync (driftCount 0)', function () {
    $synced = ['memory/decisions/0237.md' => contentGitDoc('memory/decisions/0237.md', 'sha')];

    $r = new ContentReconciler(
        gitDocsObserver: static fn (): array => $synced,
        dbDocsObserver: static fn (): array => ['memory/decisions/0237.md' => contentDbDoc('memory/decisions/0237.md', 'sha', '2026-05-30T10:00:00+00:00', '2026-05-30T09:00:00+00:00')],
        healer: static fn (): int => 0,
    );

    $result = $r->reconcile();

    expect($result)->toBeInstanceOf(ReconcileResult::class)
        ->and($result->name)->toBe('content')
        ->and($result->inSync)->toBeTrue()
        ->and($result->driftCount)->toBe(0)
        ->and($result->healedCount)->toBe(0)
        ->and($result->metadata['heal_supported'] ?? null)->toBeTrue()
        ->and($result->metadata['corpus'] ?? null)->toBe('global'); // cross-tenant by design
});

it('reconcile: drift detectado mas SEM --heal → reporta, não cura (healer nem chamado)', function () {
    $healerChamado = false;

    $r = new ContentReconciler(
        gitDocsObserver: static fn (): array => ['memory/novo.md' => contentGitDoc('memory/novo.md', 'sha')],
        dbDocsObserver: static fn (): array => [], // ausente → drift (a)
        healer: function () use (&$healerChamado): int {
            $healerChamado = true;

            return 1;
        },
    );

    $result = $r->reconcile(); // sem heal

    expect($result->inSync)->toBeFalse()
        ->and($result->driftCount)->toBe(1)
        ->and($result->healedCount)->toBe(0)
        ->and($result->drifts[0]->healed)->toBeFalse();
    expect($healerChamado)->toBeFalse(); // não curou
});

it('reconcile: --heal re-sincroniza, marca healed e reporta resynced_docs', function () {
    $r = new ContentReconciler(
        gitDocsObserver: static fn (): array => [
            'memory/a.md' => contentGitDoc('memory/a.md', 'sha'),  // ausente → drift
            'memory/b.md' => contentGitDoc('memory/b.md', 'newB'), // sha divergente → drift
        ],
        dbDocsObserver: static fn (): array => [
            'memory/b.md' => contentDbDoc('memory/b.md', 'oldB', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00'),
        ],
        healer: static fn (): int => 2, // re-sync tocou 2 docs
    );

    $result = $r->reconcile(['heal' => true]);

    expect($result->driftCount)->toBe(2)
        ->and($result->healedCount)->toBe(2)          // ambos healable → healed após re-sync
        ->and($result->drifts[0]->healed)->toBeTrue()
        ->and($result->drifts[1]->healed)->toBeTrue()
        ->and($result->metadata['resynced_docs'] ?? null)->toBe(2);
});

it('reconcile: --dry-run com --heal DETECTA mas NÃO escreve (healer nem chamado)', function () {
    $healerChamado = false;

    $r = new ContentReconciler(
        gitDocsObserver: static fn (): array => ['memory/novo.md' => contentGitDoc('memory/novo.md', 'sha')],
        dbDocsObserver: static fn (): array => [], // drift (a)
        healer: function () use (&$healerChamado): int {
            $healerChamado = true;

            return 1;
        },
    );

    $result = $r->reconcile(['heal' => true, 'dry_run' => true]);

    expect($result->driftCount)->toBe(1)
        ->and($result->healedCount)->toBe(0)          // dry-run não cura
        ->and($result->drifts[0]->healed)->toBeFalse();
    expect($healerChamado)->toBeFalse();
});

it('reconcile é idempotente: 2× com as mesmas observações = mesmo resultado', function () {
    $git = ['memory/b.md' => contentGitDoc('memory/b.md', 'newB')];
    $db = ['memory/b.md' => contentDbDoc('memory/b.md', 'oldB', '2026-05-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00')];

    $r = new ContentReconciler(
        gitDocsObserver: static fn (): array => $git,
        dbDocsObserver: static fn (): array => $db,
        healer: static fn (): int => 0,
    );

    $a = $r->reconcile();
    $b = $r->reconcile();

    expect($a->inSync)->toBe($b->inSync)
        ->and($a->driftCount)->toBe($b->driftCount)
        ->and($a->healedCount)->toBe($b->healedCount)
        ->and($a->drifts[0]->toArray())->toBe($b->drifts[0]->toArray());
});
