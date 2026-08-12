<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

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
 * que ficou com 100% de CPU contínuo e derrubou a latência do MCP server.
 *
 * A regra que estes testes travam: leitura indisponível não é mudança, e não
 * se apaga um valor bom porque não foi possível lê-lo.
 *
 * ── Por que roda na lane MySQL, e não em sqlite ──────────────────────────────
 * Testes irmãos deste diretório criam o schema mcp_* à mão e pulam quando o
 * driver não é sqlite, declarando que "a cobertura real é na lane sqlite
 * (per-PR)". Medido em 2026-08-12: essa lane NÃO EXISTE — o CI tem 15 lanes de
 * Pest, todas MySQL (mais a Unit). Um teste com esse skip nunca executa e passa
 * por não-execução. Este aqui usa o schema real provisionado pela lane e roda.
 *
 * O `onlyType` no construtor não é detalhe: em sync COMPLETO o serviço
 * soft-deleta todo doc ausente do filesystem varrido, o que apagaria o resto da
 * tabela ao rodar contra um repo temporário de um arquivo só.
 *
 * @see Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php@indexarArquivo
 */

/** Tenant fictício canônico de teste (ADR 0358). Nunca biz=4 (cliente real). */
const BIZ_TESTE = 98;

afterEach(function () {
    McpMemoryDocument::withTrashed()
        ->whereIn('slug', ['session-git-sha-preserva', 'session-git-sha-sem-churn'])
        ->forceDelete();
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
        'business_id'          => BIZ_TESTE,
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
        (new IndexarMemoryGitParaDb($repo['base'], 'webhook', null, BIZ_TESTE, 'session'))->run();

        $doc = McpMemoryDocument::withoutGlobalScopes()
            ->where('slug', 'session-git-sha-preserva')
            ->first();

        // O coração do incidente: sem git alcançável o campo era zerado, e o
        // próximo run no CT 100 via "null != SHA" e reescrevia tudo de novo.
        expect($doc)->not->toBeNull()
            ->and($doc->git_sha)->toBe($shaBom);
    } finally {
        $repo['cleanup']();
    }
});

it('não marca documento como atualizado quando só o git_sha ficou ilegível', function () {
    $conteudo = "# Doc de teste\n\nCorpo estável, não muda entre execuções.\n";

    McpMemoryDocument::create([
        'slug'                 => 'session-git-sha-sem-churn',
        'business_id'          => BIZ_TESTE,
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
        $stats = (new IndexarMemoryGitParaDb($repo['base'], 'webhook', null, BIZ_TESTE, 'session'))->run();

        // Era daqui que saía o "2485 atualizados" com zero mudança real — o
        // churn que reenviava o índice inteiro ao Meilisearch a cada passada.
        expect($stats['indexados'])->toBe(1)
            ->and($stats['atualizados'])->toBe(0)
            ->and($stats['novos'])->toBe(0);
    } finally {
        $repo['cleanup']();
    }
});
