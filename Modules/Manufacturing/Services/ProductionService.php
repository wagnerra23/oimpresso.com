<?php

namespace Modules\Manufacturing\Services;

use App\Transaction;
use App\Util\OtelHelper;
use Illuminate\Support\Collection;

/**
 * ProductionService — orquestração thin de queries de produção (Manufacturing).
 *
 * Centraliza leituras de `transactions` type=production_purchase/production_sell
 * com escopo multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093). Substitui queries inline
 * em Controllers conforme migração MWART (Blade -> Inertia/React) avança.
 *
 * Wave J — Manufacturing boost 59 -> meta 70 (Capterra D4.a 2/6 -> 3/6).
 */
class ProductionService
{
    /**
     * Lista produções (production_purchase) do business com filtros opcionais.
     *
     * @param  int  $businessId  Tier 0 — NUNCA omitir, NUNCA usar session() em Job.
     * @param  array{location_id?: int|null, start_date?: string|null, end_date?: string|null, is_final?: bool} $filters
     */
    public function listProductions(int $businessId, array $filters = [], int $perPage = 25): Collection
    {
        return OtelHelper::spanBiz('manufacturing.production.list', function () use ($businessId, $filters, $perPage) {
            $query = Transaction::query()
                ->where('business_id', $businessId)
                ->where('type', 'production_purchase')
                ->select([
                    'id',
                    'ref_no',
                    'transaction_date',
                    'location_id',
                    'final_total',
                    'mfg_is_final',
                    'mfg_wasted_units',
                    'mfg_production_cost',
                    'status',
                ]);

            if (! empty($filters['location_id'])) {
                $query->where('location_id', $filters['location_id']);
            }

            if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
                $query->whereDate('transaction_date', '>=', $filters['start_date'])
                    ->whereDate('transaction_date', '<=', $filters['end_date']);
            }

            if (! empty($filters['is_final'])) {
                $query->where('mfg_is_final', 1);
            }

            return $query->orderByDesc('transaction_date')
                ->limit($perPage)
                ->get();
        }, [
            'per_page' => $perPage,
            'has_location_filter' => ! empty($filters['location_id']),
            'is_final' => ! empty($filters['is_final']),
        ]);
    }

    /**
     * Totais agregados (contagem + valor + finalizadas) — usado no header da Index.
     */
    public function summary(int $businessId): array
    {
        $base = Transaction::query()
            ->where('business_id', $businessId)
            ->where('type', 'production_purchase');

        return [
            'total_count' => (clone $base)->count(),
            'final_count' => (clone $base)->where('mfg_is_final', 1)->count(),
            'pending_count' => (clone $base)->where('mfg_is_final', 0)->count(),
            'total_value' => (clone $base)->sum('final_total'),
        ];
    }
}
