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
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

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
    config()->set('copiloto.freshness.thresholds_days.critical', 30);
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

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

/**
 * Nivela indexed_at = updated_at (mesmo instante) pra um doc — isola o drift
 * tipo-B (git-SHA) do tipo-A (updated_at > indexed_at). Usa update direto no DB
 * pra não re-bumpar updated_at via Eloquent.
 */
function freshNivelaTimestamps(int $id): void
{
    $ts = now();
    DB::table('mcp_memory_documents')
        ->where('id', $id)
        ->update(['indexed_at' => $ts, 'updated_at' => $ts]);
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

// ─── BUG-2 regression — STALE (7-30d) isolado de CRITICAL (>=30d) ─────────────

it('BUG-2: doc de 15d é detectStale mas NÃO detectCritical (faixa STALE 7-30d isolada)', function () {
    $doc = freshFazDoc('stale-15d', now()->subDays(15));

    $detector = new StalenessDetectorService();

    // detectStale (cutoff = warm = 7d) deve pegar o doc de 15d.
    $stale = $detector->detectStale();
    expect($stale)->toHaveCount(1);
    expect($stale[0]->slug)->toBe('stale-15d');

    // detectCritical (cutoff = critical = 30d) NÃO deve pegar 15d.
    $critical = $detector->detectCritical();
    expect($critical)->toHaveCount(0);

    // staleness() confirma classificação STALE (não CRITICAL).
    expect($detector->staleness($doc))->toBe(StalenessDetectorService::NIVEL_STALE);
});

it('BUG-2: doc de 40d é CRITICAL (detectCritical pega, staleness classifica CRITICAL)', function () {
    $doc = freshFazDoc('critical-40d', now()->subDays(40));

    $detector = new StalenessDetectorService();

    $critical = $detector->detectCritical();
    expect($critical)->toHaveCount(1);
    expect($critical[0]->slug)->toBe('critical-40d');

    // 40d também é stale (superset), mas o ponto é: NÃO some do CRITICAL.
    expect($detector->detectStale())->toHaveCount(1);

    expect($detector->staleness($doc))->toBe(StalenessDetectorService::NIVEL_CRITICAL);
});

it('BUG-2: faixa 7-30d separa STALE de CRITICAL (8d/15d/29d stale-only; 31d/45d critical)', function () {
    // Valores afastados das fronteiras exatas (7/30) — as queries usam `<` no
    // cutoff (strict), então um doc indexed_at EXATO em now-30d cai na borda do
    // microssegundo. Os cenários reais nunca batem o limite no microssegundo.
    foreach ([8, 15, 29] as $d) {
        freshFazDoc("warn-{$d}d", now()->subDays($d));
    }
    foreach ([31, 45] as $d) {
        freshFazDoc("crit-{$d}d", now()->subDays($d));
    }

    $detector = new StalenessDetectorService();

    // detectStale pega todos >= 7d (3 stale-only + 2 critical = 5).
    expect($detector->detectStale())->toHaveCount(5);

    // detectCritical pega só >= 30d (2).
    $critical = $detector->detectCritical();
    expect($critical)->toHaveCount(2);
    expect(collect($critical)->pluck('slug')->sort()->values()->all())
        ->toBe(['crit-31d', 'crit-45d']);
});

// ─── BUG-1 regression — drift tipo-SHA (git↔DB) ──────────────────────────────
//
// StalenessDetectorService é `final` (não subclassável de propósito). Em vez de
// stubar lerGitShaAtual, exercitamos o ramo git real montando um repo git
// temporário (fixture). Pula gracioso quando git/shell_exec ausente — que é
// exatamente o cenário de degradação coberto pelo último teste.

function freshGitDisponivel(): bool
{
    if (! function_exists('shell_exec')) {
        return false;
    }
    $disabled = explode(',', (string) ini_get('disable_functions'));
    if (in_array('shell_exec', $disabled, true)) {
        return false;
    }
    $probe = @shell_exec('git --version 2>&1');

    return is_string($probe) && str_contains($probe, 'git version');
}

function freshMontaRepoGitTemp(): string
{
    $dir = sys_get_temp_dir() . '/fresh_git_' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    mkdir($dir . '/memory/decisions', 0777, true);
    file_put_contents($dir . '/memory/decisions/drift-sha-doc.md', "# doc\nv1\n");

    $q = escapeshellarg($dir);
    shell_exec("git -C {$q} init -q 2>&1");
    shell_exec("git -C {$q} config user.email t@t.dev 2>&1");
    shell_exec("git -C {$q} config user.name tester 2>&1");
    shell_exec("git -C {$q} add . 2>&1");
    shell_exec("git -C {$q} commit -q -m init 2>&1");

    return $dir;
}

function freshRemoveDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

it('BUG-1: drift por SHA detectado quando repoBasePath setado e git_sha diverge', function () {
    if (! freshGitDisponivel()) {
        $this->markTestSkipped('git/shell_exec indisponível — ramo git-SHA coberto pelo teste de degradação.');
    }

    $repo = freshMontaRepoGitTemp();

    try {
        // git_sha no DB diverge do HEAD real do arquivo no repo → drift tipo-B.
        // indexed_at >= updated_at isola o cenário (evita drift tipo-A).
        $doc = freshFazDoc('drift-sha-doc', now(), [
            'git_sha'  => 'staleshanaobate0000000000000000000000000',
            'git_path' => 'memory/decisions/drift-sha-doc.md',
        ]);
        freshNivelaTimestamps($doc->id);

        $detector = (new StalenessDetectorService())->comRepoBasePath($repo);
        $drift = $detector->detectDrift();

        expect($drift)->toHaveCount(1);
        expect($drift[0]->slug)->toBe('drift-sha-doc');
    } finally {
        freshRemoveDir($repo);
    }
});

it('BUG-1: NÃO acusa drift-SHA quando git_sha bate com HEAD real do repo', function () {
    if (! freshGitDisponivel()) {
        $this->markTestSkipped('git/shell_exec indisponível.');
    }

    $repo = freshMontaRepoGitTemp();

    try {
        $q = escapeshellarg($repo);
        $headSha = trim((string) shell_exec(
            "git -C {$q} log -n 1 --format=%H -- memory/decisions/drift-sha-doc.md 2>&1"
        ));

        // git_sha do DB == HEAD real → sem divergência. indexed_at >= updated_at.
        $doc = freshFazDoc('drift-sha-doc', now(), [
            'git_sha'  => $headSha,
            'git_path' => 'memory/decisions/drift-sha-doc.md',
        ]);
        freshNivelaTimestamps($doc->id);

        $detector = (new StalenessDetectorService())->comRepoBasePath($repo);

        expect($detector->detectDrift())->toHaveCount(0);
    } finally {
        freshRemoveDir($repo);
    }
});

it('BUG-1: setter comRepoBasePath é fluent e retorna o próprio service', function () {
    $detector = new StalenessDetectorService();

    expect($detector->comRepoBasePath(base_path()))
        ->toBeInstanceOf(StalenessDetectorService::class);
});

it('BUG-1: degrada gracioso quando repoBasePath aponta pra dir sem git (Hostinger sem shell_exec)', function () {
    // Sem repoBasePath (default null) o ramo git-SHA nem roda. E apontando pra
    // um dir que NÃO é repo git, lerGitShaAtual retorna null → nenhum drift-SHA
    // acusado e zero crash (fallback NULL preservado).
    $dirSemGit = sys_get_temp_dir() . '/fresh_nogit_' . bin2hex(random_bytes(4));
    mkdir($dirSemGit, 0777, true);

    try {
        $doc = freshFazDoc('no-git-doc', now()); // sem drift tipo-A
        freshNivelaTimestamps($doc->id);

        $detector = (new StalenessDetectorService())->comRepoBasePath($dirSemGit);

        // Não lança, e como git_sha não bate com "HEAD inexistente" (null),
        // o doc NÃO entra em drift-SHA (null != sha → pulado).
        expect($detector->detectDrift())->toHaveCount(0);
    } finally {
        freshRemoveDir($dirSemGit);
    }
});
