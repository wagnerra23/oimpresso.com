<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

uses(\Tests\TestCase::class);

/**
 * Sync robusto (handoff 2026-07-05-0130 — deadlock + OOM no sync completo).
 *
 * CONTRATO (âncora — proibições "teste sem âncora = rejeitado"):
 *   - handoff `memory/handoffs/2026-07-05-0130-rag-investigacao-profunda-sync-fix.md`
 *     next_step #1: "Indexar os 73 BRIEFINGs restantes exige rodar em janela sem
 *     concorrência OU batch/retry/lock".
 *   - ADR 0053 (MCP server): sync é idempotente e o índice é cache governado —
 *     um sync PARCIAL jamais pode apagar docs fora do seu subconjunto.
 *
 * Cobertura:
 *   (a) `--only=<type>` filtra a coleta pelo type (sync parcial barato)
 *   (b) sync parcial NÃO roda soft-delete (não apaga o resto do índice)
 *   (c) sync completo mantém o soft-delete (comportamento legado preservado)
 *   (d) deadlock MySQL (1213) transitório → retry e sucesso
 *   (e) exceção não-deadlock propaga imediatamente (sem retry cego)
 *   (f) lock `mcp:sync-memory` ativo → segundo run pula sem tocar o índice
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos — sqlite-only (mesmo skip do
    // IndexarMemoryGitSoftDeleteRestoreTest).
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only.');
    }

    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('slug', 200)->unique();
        $t->string('type', 30);
        $t->string('module', 50)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->mediumText('contextual_context')->nullable();
        $t->boolean('contextual_indexed')->default(false);
        $t->timestamp('contextualized_at')->nullable();
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

    Schema::create('mcp_memory_documents_history', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('document_id');
        $t->string('slug', 200);
        $t->string('git_sha', 40)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->json('metadata')->nullable();
        $t->timestamp('changed_at');
        $t->unsignedInteger('changed_by_user_id')->nullable();
        $t->string('change_reason', 50);
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_memory_documents_history');
    Schema::dropIfExists('mcp_memory_documents');
});

/**
 * Helper: repo tmp com 1 BRIEFING + 1 session (dois types distintos).
 *
 * @return array{base:string, cleanup:\Closure}
 */
function montarRepoRobustez(): array
{
    $tmpBase = storage_path('app/test-sync-robusto-' . uniqid());
    @mkdir($tmpBase . '/memory/requisitos/Financeiro', 0777, true);
    @mkdir($tmpBase . '/memory/sessions', 0777, true);
    file_put_contents($tmpBase . '/memory/requisitos/Financeiro/BRIEFING.md', "# Briefing Financeiro\n\nEstado consolidado.\n");
    file_put_contents($tmpBase . '/memory/sessions/2026-07-05-robustez.md', "# Session robustez\n\nTexto.\n");

    $cleanup = function () use ($tmpBase) {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpBase, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmpBase);
    };

    return ['base' => $tmpBase, 'cleanup' => $cleanup];
}

/** Helper: QueryException com errno de deadlock MySQL (1213). */
function excecaoDeadlock(): QueryException
{
    $pdo = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');
    $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];

    return new QueryException('mysql', 'update mcp_memory_documents set ...', [], $pdo);
}

it('(a) --only=briefing filtra a coleta pelo type', function () {
    $repo = montarRepoRobustez();

    try {
        $service = new IndexarMemoryGitParaDb($repo['base'], 'test-only', null, 1, 'briefing');

        $ref = new \ReflectionMethod($service, 'coletarArquivos');
        $todos = $ref->invoke($service);
        $refFiltro = new \ReflectionMethod($service, 'filtrarPorTipo');
        $filtrados = $refFiltro->invoke($service, $todos);

        expect(count($todos))->toBeGreaterThan(count($filtrados), 'coleta completa tem mais types que o filtro');
        expect($filtrados)->not->toBeEmpty();
        foreach ($filtrados as $info) {
            expect($info['type'])->toBe('briefing');
        }
        expect($filtrados[0]['slug'])->toBe('briefing:Financeiro');
    } finally {
        $repo['cleanup']();
    }
});

it('(b) sync parcial NÃO soft-deleta docs fora do subconjunto', function () {
    // Doc de OUTRO type já no índice — não existe no repo tmp.
    McpMemoryDocument::create([
        'slug'        => 'adr-pre-existente-fora-do-repo',
        'business_id' => 1,
        'type'        => 'adr',
        'title'       => 'ADR fora do repo tmp',
        'content_md'  => '# ADR',
        'admin_only'  => false,
        'metadata'    => [],
        'pii_redactions_count' => 0,
        'indexed_at'  => now(),
    ]);

    $repo = montarRepoRobustez();

    try {
        $stats = (new IndexarMemoryGitParaDb($repo['base'], 'test-parcial', null, 1, 'briefing'))->run();

        expect($stats['removidos'])->toBe(0, 'sync parcial nunca remove');
        expect(McpMemoryDocument::where('slug', 'adr-pre-existente-fora-do-repo')->exists())
            ->toBeTrue('doc fora do subconjunto sobrevive intacto');
        expect(McpMemoryDocument::where('slug', 'briefing:Financeiro')->exists())
            ->toBeTrue('briefing do subconjunto foi indexado');
        // A session do repo tmp NÃO entra (filtrada pelo --only).
        expect(McpMemoryDocument::where('slug', 'session-2026-07-05-robustez')->exists())->toBeFalse();
    } finally {
        $repo['cleanup']();
    }
});

it('(c) sync completo mantém o soft-delete legado', function () {
    McpMemoryDocument::create([
        'slug'        => 'session-sumiu-do-filesystem',
        'business_id' => 1,
        'type'        => 'session',
        'title'       => 'Sumiu',
        'content_md'  => '# Sumiu',
        'admin_only'  => false,
        'metadata'    => [],
        'pii_redactions_count' => 0,
        'indexed_at'  => now(),
    ]);

    $repo = montarRepoRobustez();

    try {
        $stats = (new IndexarMemoryGitParaDb($repo['base'], 'test-completo', null, 1))->run();

        expect($stats['removidos'])->toBeGreaterThanOrEqual(1);
        expect(McpMemoryDocument::where('slug', 'session-sumiu-do-filesystem')->exists())
            ->toBeFalse('sync completo soft-deleta doc que sumiu do filesystem');
    } finally {
        $repo['cleanup']();
    }
});

it('(d) deadlock transitório → retry com sucesso', function () {
    $service = new IndexarMemoryGitParaDb('/tmp/nao-usado', 'test-retry');
    $ref = new \ReflectionMethod($service, 'comRetryDeadlock');

    $chamadas = 0;
    $resultado = $ref->invoke($service, function () use (&$chamadas) {
        $chamadas++;
        if ($chamadas < 3) {
            throw excecaoDeadlock();
        }
        return 'ok-apos-retry';
    });

    expect($chamadas)->toBe(3);
    expect($resultado)->toBe('ok-apos-retry');
});

it('(d2) deadlock persistente além das tentativas propaga', function () {
    $service = new IndexarMemoryGitParaDb('/tmp/nao-usado', 'test-retry');
    $ref = new \ReflectionMethod($service, 'comRetryDeadlock');

    $chamadas = 0;
    expect(fn () => $ref->invoke($service, function () use (&$chamadas) {
        $chamadas++;
        throw excecaoDeadlock();
    }))->toThrow(QueryException::class);
    expect($chamadas)->toBe(3, 'esgotou as 3 tentativas antes de propagar');
});

it('(e) exceção não-deadlock propaga imediatamente, sem retry', function () {
    $service = new IndexarMemoryGitParaDb('/tmp/nao-usado', 'test-retry');
    $ref = new \ReflectionMethod($service, 'comRetryDeadlock');

    $pdo = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
    $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];
    $naoDeadlock = new QueryException('mysql', 'insert ...', [], $pdo);

    $chamadas = 0;
    expect(fn () => $ref->invoke($service, function () use (&$chamadas, $naoDeadlock) {
        $chamadas++;
        throw $naoDeadlock;
    }))->toThrow(QueryException::class);
    expect($chamadas)->toBe(1, 'não-deadlock NÃO ganha retry (mascarar 1062 foi a lição do hotfix 2026-05-21)');
});

it('(f) lock ativo → segundo run pula sem tocar o índice', function () {
    $lock = Cache::lock('mcp:sync-memory', 60);
    expect($lock->get())->toBeTrue('pré-condição: lock adquirido pelo "primeiro run"');

    try {
        $this->artisan('mcp:sync-memory', ['--reason' => 'test-lock'])
            ->expectsOutputToContain('lock mcp:sync-memory ativo')
            ->assertExitCode(0);

        expect(McpMemoryDocument::count())->toBe(0, 'run pulado não indexa nada');
    } finally {
        $lock->release();
    }
});
