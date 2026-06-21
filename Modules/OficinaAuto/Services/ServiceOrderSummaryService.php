<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * ServiceOrderSummaryService — agregação stateless de ServiceOrder
 * pra Dashboard ProducaoOficina + index dashboard combinada
 * (extraído de ServiceOrderController + ProducaoOficinaController, Wave 18 D4).
 *
 * Stateless puro. Multi-tenant Tier 0 (ADR 0093): query usa global scope.
 * Spans OtelHelper::spanBiz (D9.a) — zero-cost se otel.enabled=false.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php
 * @see Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php
 */
class ServiceOrderSummaryService
{
    /**
     * KPIs de reparo (manutenção mecânica pesada) pra dashboard Martinho.
     *
     * Pós-ADR 0265 (2026-06-09): locação ERRADICADA — Oficina = reparo, ponto.
     * `order_type ∈ {manutencao, mecanica}`. O KPI de locação foi removido (a 0194
     * preservava nullable; a 0265 apaga o resíduo).
     *
     * @return array{manutencao_ativa:int,concluida_mes:int,atrasada:int}
     */
    public function kpisDashboard(): array
    {
        return OtelHelper::spanBiz('oficinaauto.so.kpis_dashboard', function () {
            $hasOrderType = Schema::hasColumn('service_orders', 'order_type');
            $hasReturnDate = Schema::hasColumn('service_orders', 'expected_return_date');

            $manutencaoAtiva = ServiceOrder::query()
                ->when($hasOrderType, fn ($q) => $q->where('order_type', 'manutencao'))
                ->whereIn('status', ['aberta', 'em_servico', 'em_producao', 'orcamento'])
                ->count();

            $concluidaMes = ServiceOrder::query()
                ->where('status', 'concluida')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count();

            $atrasada = $hasReturnDate
                ? ServiceOrder::query()
                    ->whereIn('status', ['aberta', 'em_servico'])
                    ->whereDate('expected_return_date', '<', now())
                    ->count()
                : 0;

            return [
                'manutencao_ativa' => $manutencaoAtiva,
                'concluida_mes'   => $concluidaMes,
                'atrasada'        => $atrasada,
            ];
        }, ['module' => 'OficinaAuto']);
    }

    /**
     * Contagem por status (FSM aware).
     *
     * @return array<string,int>
     */
    public function contagemPorStatus(): array
    {
        return OtelHelper::spanBiz('oficinaauto.so.contagem_por_status', function () {
            return ServiceOrder::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($v) => (int) $v)
                ->toArray();
        }, ['module' => 'OficinaAuto']);
    }

    /**
     * Lista OS próximas a vencer (janela em dias).
     *
     * @return iterable<ServiceOrder>
     */
    public function proximasAVencer(int $janelaDias = 3): iterable
    {
        return OtelHelper::spanBiz('oficinaauto.so.proximas_vencer', function () use ($janelaDias) {
            if (! Schema::hasColumn('service_orders', 'expected_return_date')) {
                return collect();
            }

            return ServiceOrder::query()
                ->whereIn('status', ['aberta', 'em_servico'])
                ->whereDate('expected_return_date', '>=', now())
                ->whereDate('expected_return_date', '<=', now()->addDays($janelaDias))
                ->orderBy('expected_return_date')
                ->limit(50)
                ->get();
        }, ['module' => 'OficinaAuto', 'janela_dias' => $janelaDias]);
    }
}
