<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile\Reconcilers;

use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;

/**
 * ContentReconciler — faceta 'content' do loop `jana:reconcile` (ADR 0237).
 *
 * Garante que o git (`memory/**`, fonte da verdade ADR 0061) == o índice de busca
 * que o MCP server serve (`mcp_memory_documents`). Quando um doc canônico do git
 * chegou ao DB com `git_sha` velho, ou o DB sabe que mudou mas o Scout não
 * re-embeddou, o MCP serve conteúdo STALE. Esta faceta torna esse drift VISÍVEL
 * todo dia (alerta-only — ver "Tier 0" abaixo).
 *
 * ── DB-FIRST (observed-driven): por que NÃO enumera o git inteiro ────────────
 * A versão anterior montava `desired` varrendo TODO `memory/**` e flagava
 * "ausente no DB" pra cada path que não estava no índice. Isso gerava
 * PHANTOM-DRIFT: o healer canônico ({@see IndexarMemoryGitParaDb::coletarArquivos})
 * só ingere um SUBCONJUNTO whitelisted de globs (decisions, sessions, requisitos,
 * handoffs, reference, sprints, governance, _DesignSystem, audits-raiz) e EXCLUI
 * de propósito `memory/clientes/**` e `memory/feedback/**` por LGPD (PII de
 * cliente que o redactor não cobre). Resultado: ~1000+ paths que o índice NUNCA
 * deveria conter eram reportados como "faltando" — drift que `--heal` jamais
 * curava → `jana:reconcile --check` em exit 1 PERMANENTE (gate de CI quebrado) +
 * ruído diário que afoga o sinal real.
 *
 * Correção: ITERAR o OBSERVED (linhas que JÁ estão em `mcp_memory_documents`) e
 * checar cada uma contra o git. Assim a cobertura da faceta == a cobertura do
 * healer por construção (só olhamos o que está no índice). Espelha o
 * {@see \Modules\Jana\Services\Memoria\Freshness\StalenessDetectorService}, que a
 * ADR 0237 manda consolidar — ele também parte do DB (`McpMemoryDocument::query()`),
 * não do git.
 *
 * ── O que reconcilia (observed × git) ────────────────────────────────────────
 *  - observed = linhas de `mcp_memory_documents` (git_path, git_sha, indexed_at,
 *               updated_at), chaveadas por git_path — a base da iteração.
 *  - desired  = o estado do git PARA CADA path observado: o `git_sha` HEAD daquele
 *               arquivo (lido on-demand, best-effort via shell_exec).
 *  - drift:
 *      (b) `git_sha` git ≠ `git_sha` DB        → sync git→DB silenciou (perdeu
 *                                                webhook + cron).
 *      (c) `updated_at > indexed_at` no DB     → DB sabe que mudou, Scout não
 *                                                re-embeddou.
 *
 * O caso (a) da versão antiga — "doc NOVO no git ainda não indexado" — saiu de
 * escopo de PROPÓSITO: detectá-lo com segurança exige um PATH-LISTER CANÔNICO
 * COMPARTILHADO com o healer (mesma whitelist + mesmas exclusões LGPD), senão
 * volta o phantom-drift. Isso é FOLLOW-UP documentado (ver docblock de
 * {@see analisar()}), não dá pra fazer com segurança agora.
 *
 * ── heal DESLIGADO por enquanto (alerta-only) — risco Tier 0 latente ─────────
 * Os drifts saem `healable=false`: a faceta DETECTA + ALERTA, humano decide (R10).
 * Por quê: o único healer disponível é o {@see IndexarMemoryGitParaDb::run()}, que
 * ao final faz `McpMemoryDocument::whereNotIn('slug', $vistos)->delete()` SEM
 * escopo de `business_id`. Hoje o corpus é mono-tenant (só biz=1), mas no dia em
 * que um segundo tenant (ex biz=4 / Larissa) popular `mcp_memory_documents`, um
 * `--heal` DIÁRIO (cron) soft-deletaria TODOS os docs do outro tenant — vazamento
 * DESTRUTIVO cross-tenant (ADR 0093 Tier 0). Disparar `run()` daqui, num caller
 * diário, materializa esse risco. Logo NÃO disparamos: detecção+alerta já entregam
 * valor sem o risco.
 *
 * Auto-heal SEGURO depende de DOIS pré-requisitos (FOLLOW-UP, fora de escopo):
 *   (a) path-lister CANÔNICO compartilhado com o healer (resolve o phantom-drift
 *       do caso (a) E garante que heal e check enxergam o mesmo conjunto);
 *   (b) `business_id` no soft-delete do healer (whereNotIn slug escopado por
 *       tenant) — sem isso o delete global é Tier-0-inseguro.
 * Enquanto (a)+(b) não existirem, `healable=false` e `--heal` é no-op aqui.
 *
 * ── Multi-tenant Tier 0 (ADR 0093) — corpus GLOBAL na LEITURA ────────────────
 * `mcp_memory_documents` é o corpus de DOCUMENTAÇÃO DE PROGRAMAÇÃO da plataforma
 * (ADRs, sessions, reference, specs) — NÃO carrega dados de business. Por design
 * ele é GLOBAL/cross-tenant na LEITURA: a nota canônica vive em
 * `config('copiloto.meilisearch_indexes.mcp_memory_documents')` —
 * "corpus MCP é GLOBAL — NÃO inclui business_id (ADR 0093 não se aplica: docs de
 * programação)" (filterableAttributes = status/type/module/slug, SEM business_id).
 * Logo a OBSERVAÇÃO aqui NÃO aplica `doBusiness()`/scope de tenant e isso NÃO é
 * vazamento — é o contrato do corpus pra LER.
 *
 * ⚠️ A assimetria que motiva o `healable=false`: ler global é seguro, mas
 * DELETAR global NÃO é. Um `delete()` sem `whereNotIn(... business_id)` apaga
 * linhas de OUTROS tenants. Por isso a leitura pode ser cross-tenant enquanto a
 * cura (que deleta) fica bloqueada até o healer ganhar escopo de business_id.
 *
 * ── Testabilidade (sem DB / sem git real) ────────────────────────────────────
 * Espelha o "método puro injetável" do {@see DeployReconciler} /
 * `DeployDriftChecker::analisar`. Duas observações INJETÁVEIS por closure no
 * construtor (default = I/O real):
 *   - $dbDocsObserver  : observed (query real em `mcp_memory_documents`).
 *   - $gitShaResolver  : desired-por-path (git_sha HEAD de um git_path, on-demand).
 * O núcleo `analisar(array $dbDocs, \Closure $gitShaResolver): array` é PURO (sem
 * DB, sem clock; o git entra SÓ pelo resolver injetado): o teste injeta dbDocs
 * fake + um resolver determinístico e exercita (b)/(c) direto.
 * `observarDocsIndexados()` expõe a observação do DB como método (default = query
 * real) pro teste substituir via o closure do construtor.
 *
 * Refs:
 * - ADR 0237 (jana:reconcile loop único — contrato Reconciler)
 * - ADR 0061 (git canônico = fonte da verdade; MCP é cache governado)
 * - ADR 0053 (mcp_memory_documents — cache governado da memory/)
 * - ADR 0093 (multi-tenant Tier 0; corpus global na leitura é exceção documentada)
 * - Modules/Jana/Services/Memoria/Freshness/StalenessDetectorService (lógica
 *   de drift git↔DB DB-first que esta faceta CONSOLIDA no contrato Reconciler)
 * - Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb (healer — NÃO disparado aqui
 *   por enquanto: delete global sem business_id, FOLLOW-UP).
 */
final class ContentReconciler implements Reconciler
{
    /**
     * @var \Closure(): array<string, array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}>
     *   Observed: linhas de `mcp_memory_documents` chaveadas por git_path. É a base
     *   da iteração (DB-FIRST) — só checamos drift de docs que JÁ estão no índice.
     */
    private \Closure $dbDocsObserver;

    /**
     * @var \Closure(string): ?string
     *   Desired-por-path: dado um git_path, devolve o `git_sha` HEAD daquele arquivo
     *   no git (best-effort; null quando shell_exec indisponível — Hostinger). Só é
     *   invocado pros paths observados (DB-first), nunca varre o git inteiro.
     */
    private \Closure $gitShaResolver;

    /**
     * Closures default fecham sobre I/O real. Teste injeta stubs determinísticos.
     *
     * NÃO há mais `$gitDocsObserver` (enumerava o git inteiro = phantom-drift) nem
     * `$healer` (disparava o delete global cross-tenant — Tier-0-inseguro). Ambos
     * removidos de propósito; ver docblock da classe.
     *
     * @param (\Closure(): array<string, array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}>)|null $dbDocsObserver
     * @param (\Closure(string): ?string)|null $gitShaResolver
     */
    public function __construct(
        ?\Closure $dbDocsObserver = null,
        ?\Closure $gitShaResolver = null,
    ) {
        $this->dbDocsObserver = $dbDocsObserver
            ?? fn (): array => $this->observarDocsIndexados();

        $this->gitShaResolver = $gitShaResolver
            ?? fn (string $gitPath): ?string => $this->lerGitSha(base_path(), $gitPath);
    }

    public function name(): string
    {
        return 'content';
    }

    public function description(): string
    {
        return 'índice MCP (mcp_memory_documents) coerente com o git (memory/**): git_sha velho / updated_at>indexed_at — alerta-only';
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

        // NOTA: `heal`/`dry_run` são lidos do contrato Reconciler, mas a faceta é
        // ALERTA-ONLY por enquanto (drifts healable=false). Nenhum caminho aqui
        // dispara o healer destrutivo ({@see IndexarMemoryGitParaDb::run()} — delete
        // global sem business_id). healedCount é SEMPRE 0 (honesto). Ver docblock.
        $heal = (bool) ($opts['heal'] ?? false);
        $dryRun = (bool) ($opts['dry_run'] ?? false);
        unset($heal, $dryRun); // explicitamente ignorados (alerta-only) — sem efeito.

        $dbDocs = ($this->dbDocsObserver)();

        $drifts = $this->analisar($dbDocs, $this->gitShaResolver);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $metadata = [
            'db_docs' => count($dbDocs),
            // Alerta-only por enquanto: heal desligado até o healer ganhar (a)
            // path-lister compartilhado e (b) escopo business_id no soft-delete.
            'heal_supported' => false,
            'heal_blocked_reason' => 'healer (IndexarMemoryGitParaDb) faz delete global sem business_id — Tier-0-inseguro (ADR 0093); FOLLOW-UP',
            'healed_docs' => 0,
            // Cobertura DB-first: só docs JÁ indexados (evita phantom-drift do git inteiro).
            'coverage' => 'db_first',
            // Documenta que a faceta LÊ cross-tenant por design (corpus global, ADR 0093).
            'corpus' => 'global',
        ];

        return ReconcileResult::from($this->name(), $drifts, $durationMs, $metadata);
    }

    /**
     * Núcleo PURO + determinístico (sem DB, sem clock): para CADA doc OBSERVED
     * (linha de `mcp_memory_documents`), checa drift contra o git. DB-FIRST —
     * espelha StalenessDetectorService::doDetectDrift (que também parte do DB).
     *
     * Chave de iteração = git_path (caminho POSIX relativo ao repo, ex
     * "memory/decisions/0237-...md"). Ordena por path pra resultado determinístico.
     *
     * O git entra SÓ pelo `$gitShaResolver` injetado (default = `git log` por path,
     * best-effort): chamado UMA vez por doc observado. NÃO enumera o git inteiro —
     * é exatamente isso que elimina o phantom-drift (não há mais caso "ausente no
     * DB" varrendo `memory/**` além da whitelist do healer).
     *
     * Drift (todos `healable=false` — alerta-only; ver docblock da classe):
     *  (b) git_sha git != git_sha DB       → sync silenciou (sha velho).
     *  (c) updated_at > indexed_at no DB   → DB mudou, Scout não re-embeddou.
     *
     * Casos NÃO-drift (não reporta):
     *  - mesmo sha e indexed_at >= updated_at → synced.
     *  - git_sha indeterminado (null) no git OU no DB → não dá pra COMPARAR sha
     *    (Hostinger sem shell_exec degrada git_sha pra null). Não inventa drift de
     *    sha nesse caso; (c) ainda vale. Evita falso-positivo diário.
     *
     * FOLLOW-UP (fora de escopo — exige path-lister canônico compartilhado com o
     * healer, mesma whitelist + exclusões LGPD de `memory/clientes` e
     * `memory/feedback`): detectar "doc NOVO no git ainda não indexado". Sem o
     * path-lister compartilhado, reintroduziria o phantom-drift (git enumera milhares
     * de paths que o índice nunca deve conter). Quando existir, vira o caso (a) +
     * habilita auto-heal escopado.
     *
     * @param array<string, array{git_path: string, git_sha: ?string, indexed_at: ?string, updated_at: ?string}> $dbDocs
     *   observed, chaveado por git_path. É a BASE da iteração (DB-first).
     * @param \Closure(string): ?string $gitShaResolver
     *   desired-por-path: git_path → git_sha HEAD (null = indeterminado).
     * @return array<int, ReconcileDrift>
     */
    public function analisar(array $dbDocs, \Closure $gitShaResolver): array
    {
        $paths = array_keys($dbDocs);
        sort($paths); // determinístico

        $drifts = [];

        foreach ($paths as $path) {
            $db = $dbDocs[$path];
            $dbSha = $db['git_sha'];
            $gitSha = $gitShaResolver($path);

            // (b) git_sha diverge → sync git→DB silenciou. Só compara quando AMBOS
            // os SHAs são conhecidos (Hostinger sem shell_exec → sha null = pula).
            if ($gitSha !== null && $dbSha !== null && $gitSha !== $dbSha) {
                $drifts[] = new ReconcileDrift(
                    target: $path,
                    detail: 'git_sha do índice MCP diverge do HEAD do git — sync git→DB silenciou '
                        . '(perdeu webhook + cron). Re-sync reescreve content_md + re-embeda. '
                        . 'ALERTA-ONLY: cura manual via `php artisan mcp:sync-memory` (heal automático '
                        . 'bloqueado — healer faz delete global sem business_id, ADR 0093).',
                    desired: "git_sha={$gitSha}",
                    observed: "git_sha={$dbSha}",
                    healable: false,
                );

                continue;
            }

            // (c) updated_at > indexed_at → DB sabe que mudou, Scout não re-embeddou.
            if ($this->updatedAposIndexed($db['updated_at'], $db['indexed_at'])) {
                $drifts[] = new ReconcileDrift(
                    target: $path,
                    detail: 'updated_at > indexed_at no índice MCP — o DB registrou mudança mas o Scout '
                        . 'não re-embeddou (índice serve conteúdo stale). Re-sync força re-index. '
                        . 'ALERTA-ONLY: cura manual via `php artisan mcp:sync-memory` (heal automático '
                        . 'bloqueado — ver docblock da classe).',
                    desired: 'indexed_at >= updated_at (' . ($db['updated_at'] ?? '-') . ')',
                    observed: 'indexed_at=' . ($db['indexed_at'] ?? 'nunca'),
                    healable: false,
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
     * Multi-tenant: corpus GLOBAL na LEITURA — NÃO aplica scope de business_id (docs
     * de programação, sem dados de tenant). Ver doc da classe + config
     * `copiloto.meilisearch_indexes.mcp_memory_documents`. Inclui soft-deleted
     * (withTrashed) por simetria com o healer; aqui são inertes (sem git_sha
     * divergente acionável até serem restaurados), mas mantém a leitura fiel ao
     * estado do índice.
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
     * SHA do último commit que toca o arquivo (best-effort). MESMA estratégia do
     * IndexarMemoryGitParaDb::lerGitSha / StalenessDetectorService::lerGitShaAtual:
     * Hostinger shared hosting tem shell_exec disabled → degrada pra null (drift de
     * sha simplesmente não é avaliado nesse path; (c) ainda vale). Chamado on-demand
     * pelo resolver default, UMA vez por doc observado (DB-first) — nunca varre o git.
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
     * - indexed_at null  → nunca indexado de fato: conservador = não reporta como
     *   (c) (doc no DB sem indexed_at é raro e ambíguo). Retorna false.
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
