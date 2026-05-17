<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria\Freshness;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * GAP D7 #2 (auditoria memoria-senior 2026-05-15) — Freshness pipeline ativo.
 *
 * Detecta documentos do `mcp_memory_documents` que estão STALE (sem update há tempo)
 * ou em DRIFT (DB diverge do canônico no git). Sync git→DB já existe (ADR 0053),
 * mas até agora não havia pipeline ativo que medisse a frescura dos docs nem
 * dispatchasse re-index quando o sync silenciou.
 *
 * Complementa o TimeDecay (MeilisearchDriver::applyTimeDecay — query-time scoring)
 * com observability de pipeline (index-time / health).
 *
 * Níveis de staleness (thresholds config jana.freshness.thresholds_days):
 *   - FRESH    → indexed_at >= NOW - 1d
 *   - WARM     → 1d < age < 7d
 *   - STALE    → 7d <= age < 30d  (warning)
 *   - CRITICAL → age >= 30d       (alerta em mcp_alertas_eventos)
 *
 * Detector de drift:
 *   - `updated_at > indexed_at`           → DB sabe que doc mudou mas Scout não reindexou
 *   - `git_sha != HEAD do arquivo no git` → sync git→DB silenciou (perdeu webhook + cron)
 *
 * Repo-wide (cross-tenant): `mcp_memory_documents` não tem business_id (compartilhada
 * entre todos businesses — ADR 0053 §pilar 6). Pest cross-tenant valida que detector
 * não vaza scope.
 */
final class StalenessDetectorService
{
    public const NIVEL_FRESH    = 'FRESH';
    public const NIVEL_WARM     = 'WARM';
    public const NIVEL_STALE    = 'STALE';
    public const NIVEL_CRITICAL = 'CRITICAL';

    public function __construct(
        protected ?string $repoBasePath = null,
    ) {
    }

    /**
     * Retorna documentos com indexed_at acima do threshold de STALE/CRITICAL.
     *
     * @return array<int, McpMemoryDocument>
     */
    public function detectStale(): array
    {
        return OtelHelper::spanBiz('jana.freshness.detect_stale', function () {
            $staleDays = (int) config('copiloto.freshness.thresholds_days.stale', 7);
            $cutoff = now()->subDays($staleDays);

            return McpMemoryDocument::query()
                ->where(function ($q) use ($cutoff) {
                    $q->where('indexed_at', '<', $cutoff)
                      ->orWhereNull('indexed_at');
                })
                ->orderBy('indexed_at', 'asc')
                ->get()
                ->all();
        });
    }

    /**
     * Retorna documentos com drift:
     *  - updated_at > indexed_at (DB mudou, Scout não re-embeddou)
     *  - git_sha != HEAD git (se conseguir ler git via shell_exec)
     *
     * @return array<int, McpMemoryDocument>
     */
    public function detectDrift(): array
    {
        return OtelHelper::spanBiz('jana.freshness.detect_drift', function () {
            return $this->doDetectDrift();
        });
    }

    private function doDetectDrift(): array
    {
        // Drift tipo A: updated_at > indexed_at (sempre consultável)
        $driftDb = McpMemoryDocument::query()
            ->whereColumn('updated_at', '>', 'indexed_at')
            ->get()
            ->all();

        // Drift tipo B: git_sha diverge do HEAD git (best-effort, falha gracioso)
        $driftGit = [];
        if ($this->repoBasePath !== null && function_exists('shell_exec')) {
            foreach (McpMemoryDocument::query()->whereNotNull('git_path')->whereNotNull('git_sha')->cursor() as $doc) {
                $headSha = $this->lerGitShaAtual($doc->git_path);
                if ($headSha !== null && $headSha !== $doc->git_sha) {
                    $driftGit[] = $doc;
                }
            }
        }

        // Dedup pelo id (um doc pode ter os dois tipos de drift)
        $merged = collect([...$driftDb, ...$driftGit])
            ->unique('id')
            ->values()
            ->all();

        return $merged;
    }

    /**
     * Classifica um doc no nível FRESH | WARM | STALE | CRITICAL.
     */
    public function staleness(McpMemoryDocument $doc): string
    {
        $thresholds = (array) config('copiloto.freshness.thresholds_days', []);
        $freshDays  = (int) ($thresholds['fresh'] ?? 1);
        $warmDays   = (int) ($thresholds['warm']  ?? 7);
        $staleDays  = (int) ($thresholds['stale'] ?? 30);

        if ($doc->indexed_at === null) {
            return self::NIVEL_CRITICAL;
        }

        $ageDays = CarbonImmutable::parse($doc->indexed_at)->diffInDays(now());

        if ($ageDays <= $freshDays) {
            return self::NIVEL_FRESH;
        }
        if ($ageDays < $warmDays) {
            return self::NIVEL_WARM;
        }
        if ($ageDays < $staleDays) {
            return self::NIVEL_STALE;
        }
        return self::NIVEL_CRITICAL;
    }

    /**
     * Persiste alerta CRITICAL em `mcp_alertas_eventos` (ADR 0055).
     *
     * Idempotência: chave `memory_staleness:{slug}:{YYYY-MM-DD}` — evita duplicar
     * alerta pro mesmo doc no mesmo dia.
     *
     * @param array<int, McpMemoryDocument> $criticalDocs
     */
    public function alertCritical(array $criticalDocs): int
    {
        if (empty($criticalDocs)) {
            return 0;
        }

        $hoje = now()->format('Y-m-d');
        $inseridos = 0;

        foreach ($criticalDocs as $doc) {
            $chave = "memory_staleness:{$doc->slug}:{$hoje}";

            $existe = DB::table('mcp_alertas_eventos')
                ->where('chave_idempotencia', $chave)
                ->exists();

            if ($existe) {
                continue;
            }

            $idadeDias = $doc->indexed_at
                ? (int) CarbonImmutable::parse($doc->indexed_at)->diffInDays(now())
                : 9999;

            DB::table('mcp_alertas_eventos')->insert([
                'user_id'             => null,
                'business_id'         => null, // repo-wide, cross-tenant
                'tipo'                => 'memory_staleness',
                'severidade'          => 'high',
                'titulo'              => "Doc memory '{$doc->slug}' está CRITICAL ({$idadeDias}d sem reindex)",
                'descricao'           => "Documento {$doc->git_path} não foi reindexado há {$idadeDias} dias. " .
                                         'Pipeline freshness recomenda re-sync git→DB.',
                'chave_idempotencia'  => $chave,
                'metadata'            => json_encode([
                    'doc_id'      => $doc->id,
                    'slug'        => $doc->slug,
                    'git_path'    => $doc->git_path,
                    'indexed_at'  => $doc->indexed_at?->toIso8601String(),
                    'idade_dias'  => $idadeDias,
                    'tipo_alert'  => 'staleness_critical',
                ]),
                'status'              => 'aberto',
                'criado_em'           => now(),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $inseridos++;
        }

        if ($inseridos > 0) {
            Log::channel('copiloto-ai')->warning('StalenessDetectorService.alertCritical', [
                'inseridos' => $inseridos,
                'total_critical' => count($criticalDocs),
                'dia' => $hoje,
            ]);
        }

        return $inseridos;
    }

    /**
     * Retorna contagem por nível (visão de saúde do índice).
     *
     * @return array{FRESH:int, WARM:int, STALE:int, CRITICAL:int, total:int}
     */
    public function contagemPorNivel(): array
    {
        $contagem = [
            self::NIVEL_FRESH    => 0,
            self::NIVEL_WARM     => 0,
            self::NIVEL_STALE    => 0,
            self::NIVEL_CRITICAL => 0,
        ];

        $total = 0;
        foreach (McpMemoryDocument::query()->cursor() as $doc) {
            $contagem[$this->staleness($doc)]++;
            $total++;
        }

        return $contagem + ['total' => $total];
    }

    /**
     * Filtra docs CRITICAL (subset de detectStale) — pra alertar separado de só STALE.
     *
     * @return array<int, McpMemoryDocument>
     */
    public function detectCritical(): array
    {
        $criticalDays = (int) config('copiloto.freshness.thresholds_days.stale', 30);
        $cutoff = now()->subDays($criticalDays);

        return McpMemoryDocument::query()
            ->where(function ($q) use ($cutoff) {
                $q->where('indexed_at', '<', $cutoff)
                  ->orWhereNull('indexed_at');
            })
            ->orderBy('indexed_at', 'asc')
            ->get()
            ->all();
    }

    /**
     * Lê SHA atual do arquivo no git via shell_exec. Best-effort.
     */
    protected function lerGitShaAtual(string $relativePath): ?string
    {
        if (! function_exists('shell_exec') || $this->repoBasePath === null) {
            return null;
        }
        $disabled = explode(',', (string) ini_get('disable_functions'));
        if (in_array('shell_exec', $disabled, true)) {
            return null;
        }

        $cmd = sprintf(
            'git -C %s log -n 1 --format=%%H -- %s 2>/dev/null',
            escapeshellarg($this->repoBasePath),
            escapeshellarg($relativePath)
        );
        try {
            $sha = trim((string) @shell_exec($cmd));
            return $sha !== '' ? $sha : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
