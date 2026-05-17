<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Modules\KB\Entities\KbBridgeState;

/**
 * KbBridgeStateService — encapsula leitura/escrita do estado do bridge job.
 *
 * 1 linha por business em kb_bridge_state. Reset NULL = primeira run vai
 * fazer full sweep (sem WHERE updated_at >).
 *
 * Wave 25 — OTel span em markRun (ADR 0155 D9.a) — útil pra correlate
 * docs_processed/edges_derived com latency total da run (cron daily).
 */
class KbBridgeStateService
{
    public function getLastBridgeAt(int $businessId): ?Carbon
    {
        $state = KbBridgeState::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->first();

        return $state?->last_bridge_at;
    }

    public function markRun(int $businessId, int $docsProcessed, int $edgesDerived, ?string $error = null): void
    {
        // Wave 25 — OTel span (ADR 0155 D9.a). Zero-cost se config('otel.enabled')=false.
        // business_id Tier 0; error nullable pra diferenciar happy-path vs partial-failure.
        OtelHelper::span('kb.bridge_state.mark_run', [
            'module'          => 'KB',
            'business_id'     => $businessId,
            'docs_processed'  => $docsProcessed,
            'edges_derived'   => $edgesDerived,
            'has_error'       => $error !== null,
        ], function () use ($businessId, $docsProcessed, $edgesDerived, $error) {
            KbBridgeState::withoutGlobalScopes()->updateOrCreate(
                ['business_id' => $businessId],
                [
                    'last_bridge_at'           => now(),
                    'docs_processed_last_run'  => $docsProcessed,
                    'edges_derived_last_run'   => $edgesDerived,
                    'last_error'               => $error,
                ],
            );
        });
    }
}
