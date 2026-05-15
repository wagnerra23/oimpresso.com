<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria\Freshness;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Jobs\Mcp\ReindexarDocumentoJob;

/**
 * GAP D7 #2 (auditoria memoria-senior 2026-05-15) — Dispatcher de re-index.
 *
 * Enfileira `ReindexarDocumentoJob` pra todos os docs detectados em stale OU
 * drift pelo `StalenessDetectorService`. Limita a `$limit` jobs por execução
 * pra não saturar a queue `jana-index` no horário do cron.
 *
 * Dedup: docs que aparecem em ambos (stale + drift) só geram 1 job.
 *
 * Idempotência: job em si é idempotente (só dá bump em indexed_at + Scout sync);
 * disparar 2x não causa dano.
 */
final class ReindexJobDispatcher
{
    public function __construct(
        protected StalenessDetectorService $detector,
    ) {
    }

    /**
     * Detecta stale + drift, deduplica, enfileira até $limit jobs.
     *
     * @return int Quantidade de jobs efetivamente enfileirados.
     */
    public function dispatchStaleAndDrift(int $limit = 100): int
    {
        $stale = $this->detector->detectStale();
        $drift = $this->detector->detectDrift();

        $toReindex = collect([...$stale, ...$drift])
            ->unique('id')
            ->take($limit)
            ->values();

        foreach ($toReindex as $doc) {
            ReindexarDocumentoJob::dispatch($doc->id, 'freshness')
                ->onQueue('jana-index');
        }

        $count = $toReindex->count();

        if ($count > 0) {
            Log::channel('copiloto-ai')->info('ReindexJobDispatcher.dispatched', [
                'count'      => $count,
                'limit'      => $limit,
                'stale_qty'  => count($stale),
                'drift_qty'  => count($drift),
            ]);
        }

        return $count;
    }
}
