<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

uses(\Tests\TestCase::class);

/**
 * Regressão incidente 2026-08-12 — o loop eterno do mcp:sync-memory.
 *
 * `lerGitSha()` é best-effort e devolve null onde `shell_exec`/git não existem
 * (Hostinger shared hosting, que é onde o webhook do GitHub roda). O serviço
 * tratava esse null como se o SHA tivesse MUDADO, com dois efeitos que se
 * realimentavam:
 *
 *   1. todo documento entrava no ramo "mudou" a cada execução; e
 *   2. o UPSERT gravava null por cima do SHA válido que o CT 100 (onde git
 *      existe) tinha acabado de escrever.
 *
 * Com os dois ambientes se desfazendo mutuamente, o sync nunca convergia:
 * 2485 de 2488 docs marcados como "atualizados" em toda passada, 99% com
 * git_sha vazio, e cada passada reenviando o índice inteiro ao Meilisearch —
 * que ficou com 100% de CPU de forma contínua e derrubou a latência do MCP
 * server (5,2s por request, estourando o handshake do cliente).
 *
 * A regra que estes testes travam: leitura indisponível não é mudança, e não
 * se apaga um valor bom porque não foi possível lê-lo.
 *
 * @see Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php@indexarArquivo
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos — mesma convenção do
    // IndexarMemoryGitSoftDeleteRestoreTest, que criou este schema primeiro.
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
 * Repo temporário SEM `.git` — é o que reproduz o ambiente do incidente: sem
 * repositório git alcançável, `lerGitSha()` devolve null exatamente como no
 * Hostinger, sem precisar mexer em `disable_functions`.
 *
 * @return array{base:string, cleanup:\Closure}
 */
function montarRepoTmpSemGit(string $slugFile, string $conteudo): array
{
    $tmpBase = storage_path('app/test-gitsha-' . uniqid());
    @mkdir($tmpBase . '/memory/sessions', 0777, true);
    file_put_contents($tmpBase . '/memory/sessions/' . $slugFile . '.md', $conteudo);

    $cleanup = function () use ($tmpBase) {
        if (is_dir($tmpBase)) {
            foreach (glob($tmpBase . '/memory/sessions/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpBase . '/memory/sessions');
            @rmdir($tmpBase . '/memory');
            @rmdir($tmpBase);
        }
    };

    return ['base' => $tmpBase, 'cleanup' => $cleanup];
}

it('preserva git_sha existente quando a leitura do git falha', function () {
    $conteudo = "# Doc de teste\n\nCorpo estável, não muda entre execuções.\n";
    $shaBom   = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';

    McpMemoryDocument::create([
        'slug'                 => 'session-git-sha-preserva',
        'business_id'          => 98,
        'type'                 => 'session',
        'title'                => 'Doc de teste',
        'content_md'           => $conteudo,
        'git_sha'              => $shaBom,
        'git_path'             => 'memory/sessions/git-sha-preserva.md',
        'admin_only'           => false,
        'metadata'             => [],
        'pii_redactions_count' => 0,
        'indexed_at'           => now(),
    ]);

    $repo = montarRepoTmpSemGit('git-sha-preserva', $conteudo);

    try {
        (new IndexarMemoryGitParaDb($repo['base'], 'webhook', null, 98))->run();

        $doc = McpMemoryDocument::where('slug', 'session-git-sha-preserva')->first();

        // O coração do incidente: sem git alcançável o campo era zerado, e o
        // próximo run no CT 100 via "null != SHA" e reescrevia tudo de novo.
        expect($doc->git_sha)->toBe($shaBom);
    } finally {
        $repo['cleanup']();
    }
});

it('não marca documento como atualizado quando só o git_sha ficou ilegível', function () {
    $conteudo = "# Doc de teste\n\nCorpo estável, não muda entre execuções.\n";

    McpMemoryDocument::create([
        'slug'                 => 'session-git-sha-sem-churn',
        'business_id'          => 98,
        'type'                 => 'session',
        'title'                => 'Doc de teste',
        'content_md'           => $conteudo,
        'git_sha'              => 'b2c3d4e5f60718293a4b5c6d7e8f901234567890',
        'git_path'             => 'memory/sessions/git-sha-sem-churn.md',
        'admin_only'           => false,
        'metadata'             => [],
        'pii_redactions_count' => 0,
        'indexed_at'           => now(),
    ]);

    $repo = montarRepoTmpSemGit('git-sha-sem-churn', $conteudo);

    try {
        $stats = (new IndexarMemoryGitParaDb($repo['base'], 'webhook', null, 98))->run();

        // Era daqui que saía o "2485 atualizados" com zero mudança real — o
        // churn que reenviava o índice inteiro ao Meilisearch a cada passada.
        expect($stats['atualizados'])->toBe(0)
            ->and($stats['novos'])->toBe(0);
    } finally {
        $repo['cleanup']();
    }
});
