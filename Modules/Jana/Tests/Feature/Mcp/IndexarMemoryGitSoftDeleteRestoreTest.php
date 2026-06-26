<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

uses(\Tests\TestCase::class);

/**
 * Regressão hotfix 2026-05-21:
 *
 * `IndexarMemoryGitParaDb::indexarArquivo()` usava `McpMemoryDocument::firstOrNew()`
 * sem `withTrashed()`. Como o modelo usa `SoftDeletes`, rows soft-deletados eram
 * ignorados → `$novo = true` → `create()` violava UNIQUE constraint do slug.
 *
 * Sintoma prod (cron mcp:sync-memory a cada 5min):
 *   SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
 *   'session-2026-05-13-agents-canonicos-meta-degradacao'
 *   for key 'mcp_memory_documents_slug_unique'
 *
 * Cenário: arquivo removido em sync prévio (soft-delete via whereNotIn linha 84),
 * voltou ao filesystem em sync subsequente → deve RESTAURAR row existente, não
 * tentar criar nova.
 *
 * @see Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php@indexarArquivo
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // mcp_memory_documents (mesmo pattern do StalenessDetectorTest — manual create
    // pra rodar em sqlite :memory: do phpunit.xml).
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
 * Helper: cria filesystem tmp com um arquivo .md em memory/sessions/.
 *
 * @return array{base:string, cleanup:Closure}
 */
function montarRepoTmp(string $slugFile, string $conteudo): array
{
    $tmpBase = storage_path('app/test-indexar-' . uniqid());
    @mkdir($tmpBase . '/memory/sessions', 0777, true);
    file_put_contents($tmpBase . '/memory/sessions/' . $slugFile . '.md', $conteudo);

    $cleanup = function () use ($tmpBase) {
        if (is_dir($tmpBase)) {
            foreach (glob($tmpBase . '/memory/sessions/*') ?: [] as $f) @unlink($f);
            @rmdir($tmpBase . '/memory/sessions');
            @rmdir($tmpBase . '/memory');
            @rmdir($tmpBase);
        }
    };

    return ['base' => $tmpBase, 'cleanup' => $cleanup];
}

it('restaura McpMemoryDocument soft-deletado em vez de violar UNIQUE constraint', function () {
    // Setup: cria doc + soft-delete
    $existente = McpMemoryDocument::create([
        'slug'        => 'session-hotfix-restore-test',
        'business_id' => 1,
        'type'        => 'session',
        'title'       => 'Conteúdo antigo',
        'content_md'  => '# Antigo',
        'git_path'    => 'memory/sessions/hotfix-restore-test.md',
        'admin_only'  => false,
        'metadata'    => [],
        'pii_redactions_count' => 0,
        'indexed_at'  => now(),
    ]);
    $existente->delete();

    expect(McpMemoryDocument::withTrashed()->where('slug', 'session-hotfix-restore-test')->first()?->trashed())
        ->toBeTrue('pre-condição: doc soft-deletado');

    $repo = montarRepoTmp('hotfix-restore-test', "# Hotfix restore\n\nConteúdo NOVO após reaparecer.\n");

    try {
        $service = new IndexarMemoryGitParaDb($repo['base'], 'test-hotfix', null, 1);
        $stats = $service->run();

        // Antes do fix: aqui já teria lançado QueryException 1062.
        expect($stats)->toBeArray();

        $restaurada = McpMemoryDocument::where('slug', 'session-hotfix-restore-test')->first();
        expect($restaurada)->not->toBeNull('doc restaurada aparece em query default');
        expect($restaurada->trashed())->toBeFalse('soft-delete removido');
        expect($restaurada->content_md)->toContain('NOVO');
        expect($stats['atualizados'])->toBeGreaterThanOrEqual(1);

        // Opção (c) metadata-only (incidente 2026-06-26): o restore gera snapshot
        // no history, mas SEM o conteúdo (content_md vazio) — só metadado; o git é
        // canônico pro conteúdo (ADR 0061). É o que impede o bloat que revogou a
        // escrita do ERP. O doc CURRENT (acima) segue com o content_md cheio.
        $hist = \Illuminate\Support\Facades\DB::table('mcp_memory_documents_history')
            ->where('document_id', $restaurada->id)->orderByDesc('id')->first();
        expect($hist)->not->toBeNull('restore deve gerar snapshot no history');
        expect($hist->content_md)->toBe('', 'metadata-only: history não guarda content_md');
    } finally {
        $repo['cleanup']();
    }
});

it('cria novo McpMemoryDocument quando slug não existe (path feliz)', function () {
    $repo = montarRepoTmp('novo-doc-pristine', "# Doc novo pristine\n\nNunca foi sincronizado.\n");

    try {
        $service = new IndexarMemoryGitParaDb($repo['base'], 'test-novo', null, 1);
        $stats = $service->run();

        $novo = McpMemoryDocument::where('slug', 'session-novo-doc-pristine')->first();
        expect($novo)->not->toBeNull();
        expect($novo->trashed())->toBeFalse();
        expect($stats['novos'])->toBeGreaterThanOrEqual(1);
    } finally {
        $repo['cleanup']();
    }
});
