<?php

declare(strict_types=1);

namespace Modules\Fiscal\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;

/**
 * GAP-FISCAL-002 — Invalida cache KPIs do Cockpit Fiscal quando NFe/NFCe é
 * autorizada (cache vira stale).
 *
 * Cache key padrão: `fiscal:cockpit:kpis:biz:{businessId}`.
 *
 * Registrado em FiscalServiceProvider.boot() pro Laravel Event dispatcher.
 *
 * Listener intencionalmente SÍNCRONO (não implementa ShouldQueue) — invalidar
 * cache key tem custo desprezível (~1ms Redis DEL), latência adicional aceitável
 * dentro do request HTTP que disparou o evento. Async aqui só atrasaria a
 * eventual coerência sem benefício.
 *
 * @see memory/requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-002
 */
class InvalidaCockpitCacheListener
{
    /**
     * Cache key prefix — DEVE bater com CockpitController::kpisCacheKey().
     */
    public const KEY_PREFIX = 'fiscal:cockpit:kpis:biz:';

    public function handle(NFeAutorizada|NFCeAutorizada $event): void
    {
        $businessId = (int) ($event->emissao->business_id ?? 0);
        if ($businessId <= 0) {
            return;
        }

        Cache::forget(self::KEY_PREFIX . $businessId);
    }
}
