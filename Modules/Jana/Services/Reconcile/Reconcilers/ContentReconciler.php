<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile\Reconcilers;

use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;

/**
 * ContentReconciler — faceta 'content' do loop `jana:reconcile` (ADR 0237).
 *
 * Garante que o git (`memory/**`, fonte da verdade ADR 0061) == o índice de busca
 * que o MCP server serve (`mcp_memory_documents`). Quando um doc canônico do git
 * não chegou ao DB — ou chegou com `git_sha` velho, ou o DB sabe que mudou mas o
 * Scout não re-embeddou — o MCP serve conteúdo STALE. Esta faceta torna esse drift
 * VISÍVEL todo dia e (com --heal) re-sincroniza o que está claramente atrasado.
 *
 * ── O que reconcilia (desired × observed) ────────────────────────────────────
 *  - desired  = docs em `memory/**` com seu `git_sha` (HEAD), chaveados por path.
 *  - observed = linhas de `mcp_memory_documents` (git_path, git_sha, indexed_at,
 *               updated_at), chaveadas por path.
 *  - drift:
 *      (a) doc no git AUSENTE no DB           → nunca indexado (ou soft-deleted).
 *      (b) `git_sha` git ≠ `git_sha` DB        → sync git→DB silenciou (perdeu
 *                                                webhook + cron).
 *      (c) `updated_at > indexed_at` no DB     → DB sabe que mudou, Scout não
 *                                                re-embeddou.
 *  Todos os três são HEALABLE: a fonte-de-verdade é o git e o re-sync é
 *  idempotente + append-only (snapshot em history antes de sobrescrever).
 *
 * ── heal = re-sync git→DB + re-embed (reusa a lógica existente) ───────────────
 * `heal=true` delega ao {@see IndexarMemoryGitParaDb::run()} — o MESMO sync
 * canônico do webhook GitHub→MCP e do cron `mcp:sync-memory`. Ele re-lê o `.md`
 * do git, recalcula o `git_sha`, faz UPSERT por slug (restaura soft-deleted),
 * snapshota a versão anterior em `mcp_memory_documents_history` e dispara o Scout
 * (re-embed Meilisearch). NÃO reimplementa parsing/embedding aqui — só consome.
 * `dry_run=true` DETECTA e reporta sem escrever (não chama run()).
 *
 * Idempotência: o sync é no-op quando o sha já bate (o próprio serviço só
 * atualiza `indexed_at` sem disparar Scout pra doc inalterado). Rodar 2× = mesmo
 * estado final. Após heal, os drifts ficam `healed=true` (o re-sync os cobriu).
 *
 * ── Multi-tenant Tier 0 (ADR 0093) — corpus GLOBAL, NÃO filtra business_id ────
 * `mcp_memory_documents` é o corpus de DOCUMENTAÇÃO DE PROGRAMAÇÃO da plataforma
 * (ADRs, sessions, reference, specs) — NÃO carrega dados de business. Por design
 * ele é GLOBAL/cross-tenant: a nota canônica vive em
 * `config('copiloto.meilisearch_indexes.mcp_memory_documents')` —
 * "corpus MCP é GLOBAL — NÃO inclui business_id (ADR 0093 não se aplica: docs de
 * programação)" (filterableAttributes = status/type/module/slug, SEM business_id).
 * Logo este Reconciler NÃO aplica `doBusiness()`/scope de tenant e isso NÃO é
 * vazamento — é o contrato do corpus. O sync (IndexarMemoryGitParaDb) grava
 * `business_id = 1` por compat de schema (coluna nullable, legado pré-MEM-MULTI-1),
 * mas a faceta NÃO segmenta por tenant.
 *
 * ── Testabilidade (sem DB / sem git real) ────────────────────────────────────
 * Espelha o "método puro injetável" do {@see DeployReconciler} /
 * `DeployDriftChecker::analisar`. Três observações INJETÁVEIS por closure no
 * construtor (default = I/O real):
 *   - $gitDocsObserver : desired (git HEAD).
 *   - $dbDocsObserver  : observed (query real em `mcp_memory_documents`).
 *   - $healer          : ação de cura (run() do sync) — devolve quantos docs tocou.
 * O núcleo `analisar(array $gitDocs, array $dbDocs): array` é PURO (sem DB, sem
 * git, sem clock): o teste injeta gitDocs+dbDocs fake e exercita os 3 tipos de
 * drift direto. `observarDocsIndexados()` expõe a observação do DB como método
 * (default = query real) pro teste substituir via o closure do construtor.
 *
 * Refs:
 * - ADR 0237 (jana:reconcile loop único — contrato Reconciler)
 * - ADR 0061 (git canônico = fonte da verdade; MCP é cache governado)
 * - ADR 0053 (mcp_memory_documents — cache governado da memory/)
 * - ADR 0093 (multi-tenant Tier 0; corpus global é exceção documentada)
 * - Modules/Jana/Services/Memoria/Freshness/StalenessDetectorService (lógica
 *   de drift git↔DB que esta faceta CONSOLIDA no contrato Reconciler)
 */
final class ContentReconciler implements Reconciler
{
    /**
     * @var \Closure(): array<string, array{git_path: string, git_sha: ?string}>
     *   Desired: docs do git (`memory/**`) chaveados por git_path → {git_path, git_sha HEAD}.
     */
    private \Closure $gitDocsObserver;

    /**
     * @var \Closure(): array<string, array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}>
     *   Observed: linhas de `mcp_memory_documents` chaveadas por git_path.
     */
    private \Closure $dbDocsObserver;

    /**
     * @var \Closure(): int Ação de cura: re-sincroniza git→DB (+ re-embed) e devolve
     *   quantos docs foram efetivamente tocados (novos + atualizados). Default delega
     *   ao IndexarMemoryGitParaDb::run().
     */
    private \Closure $healer;

    /**
     * Closures default fecham sobre I/O real. Teste injeta stubs determinísticos.
     *
     * @param (\Closure(): array<string, array{git_path: string, git_sha: ?string}>)|null $gitDocsObserver
     * @param (\Closure(): array<string, array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}>)|null $dbDocsObserver
     * @param (\Closure(): int)|null $healer
     */
    public function __construct(
        ?\Closure $gitDocsObserver = null,
        ?\Closure $dbDocsObserver = null,
        ?\Closure $healer = null,
    ) {
        $this->gitDocsObserver = $gitDocsObserver
            ?? fn (): array => $this->coletarGitDocs(base_path());

        $this->dbDocsObserver = $dbDocsObserver
            ?? fn (): array => $this->observarDocsIndexados();

        $this->healer = $healer
            ?? static function (): int {
                // MESMO sync do webhook GitHub→MCP / cron mcp:sync-memory. Idempotente:
                // só toca o que mudou (sha != ou novo) e re-embeda via Scout. business_id=1
                // por compat de schema (coluna nullable, legado) — corpus é global por design.
                $stats = (new IndexarMemoryGitParaDb(base_path(), 'reconcile', null, 1))->run();

                // run() garante as chaves 'novos'/'atualizados' (sempre presentes, int).
                return $stats['novos'] + $stats['atualizados'];
            };
    }

    public function name(): string
    {
        return 'content';
    }

    public function description(): string
    {
        return 'git (memory/**) == índice MCP (mcp_memory_documents): doc faltando / git_sha velho / updated_at>indexed_at';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tier_1', 'content', 'memory', 'mcp'];
    }

    public function reconcile(array $opts = []): ReconcileResult
    {
        $start = microtime(true);

        $heal = (bool) ($opts['heal'] ?? false);
        $dryRun = (bool) ($opts['dry_run'] ?? false);

        $gitDocs = ($this->gitDocsObserver)();
        $dbDocs = ($this->dbDocsObserver)();

        $drifts = $this->analisar($gitDocs, $dbDocs);

        $healedCount = 0;
        if ($heal && ! $dryRun && $drifts !== []) {
            // Re-sync único cobre TODOS os drifts de conteúdo (faltando/sha/updated):
            // o IndexarMemoryGitParaDb varre memory/** inteiro e reconcilia cada doc.
            $healedCount = ($this->healer)();

            // Após o re-sync, marca os drifts como curados (a fonte-de-verdade git foi
            // reaplicada no DB). healed=true alimenta ReconcileResult::healedCount.
            $drifts = array_map(
                static fn (ReconcileDrift $d): ReconcileDrift => new ReconcileDrift(
                    target: $d->target,
                    detail: $d->detail,
                    desired: $d->desired,
                    observed: $d->observed,
                    healable: $d->healable,
                    healed: $d->healable,
                ),
                $drifts,
            );
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $metadata = [
            'git_docs' => count($gitDocs),
            'db_docs' => count($dbDocs),
            'heal_supported' => true,
            'resynced_docs' => $healedCount,
            // Documenta que a faceta é cross-tenant por design (corpus global, ADR 0093).
            'corpus' => 'global',
        ];

        return ReconcileResult::from($this->name(), $drifts, $durationMs, $metadata);
    }

    /**
     * Núcleo PURO + determinístico (sem DB, sem git, sem clock): cruza desired (git)
     * × observed (DB) e devolve os ReconcileDrift. Testável direto — espelha
     * DeployReconciler::analisar / DesignDocsFreshnessChecker::analisarDoc.
     *
     * Chave de junção = git_path (caminho POSIX relativo ao repo, ex
     * "memory/decisions/0237-...md"). Ordena por path pra resultado determinístico.
     *
     * Drift (todos HEALABLE — fonte-de-verdade é o git, re-sync idempotente):
     *  (a) path no git, ausente no DB     → nunca indexado.
     *  (b) git_sha git != git_sha DB       → sync silenciou (sha velho).
     *  (c) updated_at > indexed_at no DB   → DB mudou, Scout não re-embeddou.
     *
     * Casos NÃO-drift (não reporta):
     *  - path igual em ambos com mesmo sha e indexed_at >= updated_at → synced.
     *  - git_sha indeterminado (null) em git OU DB → não dá pra COMPARAR sha
     *    (Hostinger sem shell_exec degrada git_sha pra null). Não inventa drift de
     *    sha nesse caso; (a) e (c) ainda valem. Evita falso-positivo diário.
     *  - path SÓ no DB (não está no git) → fora do escopo desta faceta. O sync já
     *    soft-deleta órfãos (whereNotIn slug); reportar aqui seria ruído.
     *
     * @param array<string, array{git_path: string, git_sha: ?string}> $gitDocs
     *   desired, chaveado por git_path.
     * @param array<string, array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}> $dbDocs
     *   observed, chaveado por git_path.
     * @return array<int, ReconcileDrift>
     */
    public function analisar(array $gitDocs, array $dbDocs): array
    {
        $paths = array_keys($gitDocs);
        sort($paths); // determinístico

        $drifts = [];

        foreach ($paths as $path) {
            $git = $gitDocs[$path];
            $gitSha = $git['git_sha'];

            // (a) doc do git ausente no DB → nunca indexado (ou soft-deleted).
            if (! array_key_exists($path, $dbDocs)) {
                $drifts[] = new ReconcileDrift(
                    target: $path,
                    detail: 'Doc do git ausente no índice MCP (mcp_memory_documents) — nunca indexado '
                        . 'ou soft-deletado. Re-sync git→DB indexa + embeda.',
                    desired: $gitSha !== null ? "git_sha={$gitSha}" : 'presente no git',
                    observed: 'ausente no DB',
                    healable: true,
                );

                continue;
            }

            $db = $dbDocs[$path];
            $dbSha = $db['git_sha'];

            // (b) git_sha diverge → sync git→DB silenciou. Só compara quando AMBOS
            // os SHAs são conhecidos (Hostinger sem shell_exec → sha null = pula).
            if ($gitSha !== null && $dbSha !== null && $gitSha !== $dbSha) {
                $drifts[] = new ReconcileDrift(
                    target: $path,
                    detail: 'git_sha do índice MCP diverge do HEAD do git — sync git→DB silenciou '
                        . '(perdeu webhook + cron). Re-sync reescreve content_md + re-embeda.',
                    desired: "git_sha={$gitSha}",
                    observed: "git_sha={$dbSha}",
                    healable: true,
                );

                continue;
            }

            // (c) updated_at > indexed_at → DB sabe que mudou, Scout não re-embeddou.
            if ($this->updatedAposIndexed($db['updated_at'], $db['indexed_at'])) {
                $drifts[] = new ReconcileDrift(
                    target: $path,
                    detail: 'updated_at > indexed_at no índice MCP — o DB registrou mudança mas o Scout '
                        . 'não re-embeddou (índice serve conteúdo stale). Re-sync força re-index.',
                    desired: 'indexed_at >= updated_at (' . ($db['updated_at'] ?? '-') . ')',
                    observed: 'indexed_at=' . ($db['indexed_at'] ?? 'nunca'),
                    healable: true,
                );

                continue;
            }
        }

        return $drifts;
    }

    /**
     * Observação do DB INJETÁVEL (default deste método = query real em
     * `mcp_memory_documents`). Espelha o "método puro injetável" do DeployDriftChecker:
     * o teste substitui via o closure $dbDocsObserver do construtor (não toca DB).
     *
     * Multi-tenant: corpus GLOBAL — NÃO aplica scope de business_id (docs de
     * programação, sem dados de tenant). Ver doc da classe + config
     * `copiloto.meilisearch_indexes.mcp_memory_documents`. Inclui soft-deleted
     * (withTrashed) pra que (a) "ausente no DB" distinga doc nunca-indexado de
     * doc soft-deletado que o git ressuscitou — ambos curam via re-sync.
     *
     * @return array<string, array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}>
     */
    public function observarDocsIndexados(): array
    {
        $linhas = McpMemoryDocument::withTrashed()
            ->select(['git_path', 'git_sha', 'indexed_at', 'updated_at'])
            ->get();

        $out = [];
        foreach ($linhas as $linha) {
            // getAttribute() (Eloquent, retorno mixed) em vez de acesso por propriedade
            // mágica — type-guard explícito mantém PHPStan limpo sem @property no model.
            $path = $this->stringOuVazio($linha->getAttribute('git_path'));
            if ($path === '') {
                continue;
            }
            $out[$path] = [
                'git_path' => $path,
                'git_sha' => $this->stringOuNull($linha->getAttribute('git_sha')),
                'indexed_at' => $this->isoOuNull($linha->getAttribute('indexed_at')),
                'updated_at' => $this->isoOuNull($linha->getAttribute('updated_at')),
            ];
        }

        return $out;
    }

    /**
     * Coleta os docs canônicos do git (`memory/**`) com seu `git_sha` HEAD, chaveados
     * por git_path. Default de produção do desired — reusa a MESMA lista de paths que
     * o sync (IndexarMemoryGitParaDb) cobre + o MESMO leitor de SHA best-effort (git
     * log via shell_exec, degrada pra null no Hostinger). NÃO duplica parsing de
     * frontmatter/embedding: só precisa de path + sha pra COMPARAR.
     *
     * @return array<string, array{git_path: string, git_sha: ?string}>
     */
    private function coletarGitDocs(string $base): array
    {
        $out = [];
        foreach ($this->listarPathsGit($base) as $relPath) {
            $out[$relPath] = [
                'git_path' => $relPath,
                'git_sha' => $this->lerGitSha($base, $relPath),
            ];
        }

        return $out;
    }

    /**
     * Lista os paths POSIX-relativos dos docs canônicos sob `memory/**` (e a raiz
     * CLAUDE/DESIGN/INFRA) que o índice MCP deve refletir. Varre recursivo via
     * RecursiveDirectoryIterator (glob do PHP não recursa) — pula `_*`/README como
     * o sync faz. Ordenado pra determinismo.
     *
     * @return array<int, string>
     */
    private function listarPathsGit(string $base): array
    {
        $baseNorm = rtrim(str_replace('\\', '/', $base), '/');
        $memoryDir = $baseNorm . '/memory';

        $paths = [];

        if (is_dir($memoryDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($memoryDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo) {
                    continue;
                }
                if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                    continue;
                }
                $name = $file->getBasename('.md');
                if (str_starts_with($name, '_') || $name === 'README') {
                    continue;
                }
                $full = str_replace('\\', '/', $file->getPathname());
                $paths[$full] = ltrim(substr($full, strlen($baseNorm)), '/');
            }
        }

        // Docs canônicos da raiz que o sync também indexa (reference).
        foreach (['CLAUDE.md', 'DESIGN.md', 'INFRA.md'] as $raiz) {
            $full = $baseNorm . '/' . $raiz;
            if (is_file($full)) {
                $paths[$full] = $raiz;
            }
        }

        $rel = array_values($paths);
        sort($rel);

        return $rel;
    }

    /**
     * SHA do último commit que toca o arquivo (best-effort). MESMA estratégia do
     * IndexarMemoryGitParaDb::lerGitSha / StalenessDetectorService::lerGitShaAtual:
     * Hostinger shared hosting tem shell_exec disabled → degrada pra null (drift de
     * sha simplesmente não é avaliado nesse path; (a)/(c) ainda valem).
     */
    private function lerGitSha(string $base, string $relativePath): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }
        $disabled = explode(',', (string) ini_get('disable_functions'));
        if (in_array('shell_exec', $disabled, true)) {
            return null;
        }

        // stderr cross-platform: 2>NUL (Windows dev) / 2>/dev/null (POSIX prod).
        $nullDevice = stripos(PHP_OS, 'WIN') === 0 ? '2>NUL' : '2>/dev/null';
        $cmd = sprintf(
            'git -C %s log -n 1 --format=%%H -- %s %s',
            escapeshellarg($base),
            escapeshellarg($relativePath),
            $nullDevice,
        );

        $sha = @shell_exec($cmd);
        if (! is_string($sha)) {
            return null;
        }
        $sha = trim($sha);

        return $sha !== '' ? $sha : null;
    }

    /**
     * updated_at > indexed_at? Comparação date-only-safe sobre strings ISO-8601
     * (ou qualquer formato que strtotime entenda). Pura: recebe as duas strings já
     * observadas, não toca clock.
     *
     * - indexed_at null  → nunca indexado: tratado por (a) ausência, NÃO aqui (o doc
     *   existe no DB mas sem indexed_at é raro; conservador = não reporta como (c)
     *   pra não duplicar com casos de borda). Retorna false.
     * - updated_at null  → sem sinal de mudança → false.
     */
    private function updatedAposIndexed(?string $updatedAt, ?string $indexedAt): bool
    {
        if ($updatedAt === null || $indexedAt === null) {
            return false;
        }

        $u = strtotime($updatedAt);
        $i = strtotime($indexedAt);
        if ($u === false || $i === false) {
            return false;
        }

        return $u > $i;
    }

    /**
     * Normaliza um valor de timestamp (Carbon|DateTime|string|null) pra string ISO
     * ou null. Defensivo contra o cast 'datetime' do model (Carbon) e contra select
     * cru (string). Sem `mixed` solto — checa os tipos esperados.
     */
    private function isoOuNull(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format(\DateTimeInterface::ATOM);
        }
        if (is_string($valor)) {
            return $valor !== '' ? $valor : null;
        }

        return null;
    }

    /**
     * Coage um atributo Eloquent (mixed) pra string não-nula ('' quando ausente).
     * Type-guard explícito — evita acesso por propriedade mágica (PHPStan-clean).
     */
    private function stringOuVazio(mixed $valor): string
    {
        return is_string($valor) ? $valor : '';
    }

    /**
     * Coage um atributo Eloquent (mixed) pra string ou null (string vazia → null).
     */
    private function stringOuNull(mixed $valor): ?string
    {
        if (is_string($valor)) {
            return $valor !== '' ? $valor : null;
        }

        return null;
    }
}
