<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use Carbon\Carbon;
use Modules\KB\Entities\KbBridgeState;

/**
 * KbBridgeStateService — encapsula leitura/escrita do estado do bridge job.
 *
 * 1 linha por business em kb_bridge_state. Reset NULL = primeira run vai
 * fazer full sweep (sem WHERE updated_at >).
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
        KbBridgeState::withoutGlobalScopes()->updateOrCreate(
            ['business_id' => $businessId],
            [
                'last_bridge_at'           => now(),
                'docs_processed_last_run'  => $docsProcessed,
                'edges_derived_last_run'   => $edgesDerived,
                'last_error'               => $error,
            ],
        );
    }
}
