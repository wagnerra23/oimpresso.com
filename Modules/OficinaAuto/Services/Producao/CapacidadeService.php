<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services\Producao;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * CapacidadeService — calcula capacidade ocupada/disponível da oficina pra
 * tomada de decisão diária (Wagner / Martinho aceitar nova OS hoje?).
 *
 * Wave 18 saturation D4 Architecture + D9 +5 spans observability.
 *
 * Stateless puro. Multi-tenant Tier 0 (ADR 0093): query usa global scope
 * de ServiceOrder (filtra por business_id da sessão automaticamente).
 * Spans canon `oficinaauto.producao.*` zero-cost se otel.enabled=false.
 *
 * Heurística V0 (sem coluna `duration_estimate_hours` ainda):
 *  - 1 OS aberta = 4h capacity-blocking
 *  - 1 OS em_servico/em_producao = 6h capacity-blocking
 *  - Capacidade diária default 32h (4 mecânicos × 8h) configurável via param
 *
 * Quando US-OFICINA-007 entregar `duration_estimate_hours` em service_orders,
 * trocar heurística por sum real (mantém contrato métodos públicos).
 *
 * @see Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php
 * @see Modules/OficinaAuto/Tests/Feature/CapacidadeServiceTest.php
 */
class CapacidadeService
{
    /** Capacidade diária default (4 mecânicos × 8h). */
    public const CAPACIDADE_DIARIA_HORAS_DEFAULT = 32;

    /** Heurística V0: horas estimadas por OS em status aberta. */
    public const HORAS_OS_ABERTA = 4;

    /** Heurística V0: horas estimadas por OS em produção. */
    public const HORAS_OS_PRODUCAO = 6;

    /**
     * Calcula capacidade ocupada hoje (heurística V0 — pré-US-OFICINA-007).
     */
    public function capacidadeOcupadaHoje(): int
    {
        return OtelHelper::spanBiz('oficinaauto.producao.capacidade_ocupada_hoje', function () {
            $abertas = ServiceOrder::query()
                ->whereIn('status', ['aberta', 'orcamento', 'aprovada'])
                ->count();

            $emProducao = ServiceOrder::query()
                ->whereIn('status', ['em_servico', 'em_producao'])
                ->count();

            return ($abertas * self::HORAS_OS_ABERTA) + ($emProducao * self::HORAS_OS_PRODUCAO);
        }, ['module' => 'OficinaAuto']);
    }

    /**
     * Capacidade disponível hoje (default − ocupada). Nunca < 0.
     */
    public function capacidadeDisponivelHoje(int $capacidadeDiaria = self::CAPACIDADE_DIARIA_HORAS_DEFAULT): int
    {
        return OtelHelper::spanBiz('oficinaauto.producao.capacidade_disponivel_hoje', function () use ($capacidadeDiaria) {
            $ocupada = $this->capacidadeOcupadaHoje();

            return max(0, $capacidadeDiaria - $ocupada);
        }, ['module' => 'OficinaAuto', 'capacidade_diaria' => $capacidadeDiaria]);
    }

    /**
     * Taxa de ocupação (% 0-100). 100% = lotada. >100% indica
     * overcommit (acima da capacidade — sinalizar UI).
     */
    public function taxaOcupacao(int $capacidadeDiaria = self::CAPACIDADE_DIARIA_HORAS_DEFAULT): float
    {
        return OtelHelper::spanBiz('oficinaauto.producao.taxa_ocupacao', function () use ($capacidadeDiaria) {
            if ($capacidadeDiaria <= 0) {
                return 0.0;
            }
            $ocupada = $this->capacidadeOcupadaHoje();

            return round(($ocupada / $capacidadeDiaria) * 100, 2);
        }, ['module' => 'OficinaAuto', 'capacidade_diaria' => $capacidadeDiaria]);
    }

    /**
     * Pode aceitar nova OS X horas estimadas hoje?
     */
    public function podeAceitarNovaOs(int $horasEstimadas, int $capacidadeDiaria = self::CAPACIDADE_DIARIA_HORAS_DEFAULT): bool
    {
        return OtelHelper::spanBiz('oficinaauto.producao.pode_aceitar_nova_os', function () use ($horasEstimadas, $capacidadeDiaria) {
            $disponivel = $this->capacidadeDisponivelHoje($capacidadeDiaria);

            return $horasEstimadas <= $disponivel;
        }, ['module' => 'OficinaAuto', 'horas_estimadas' => $horasEstimadas]);
    }

    /**
     * Resumo combinado pra dashboard.
     *
     * @return array{ocupada:int,disponivel:int,capacidade:int,taxa:float,status:string}
     */
    public function resumoCapacidade(int $capacidadeDiaria = self::CAPACIDADE_DIARIA_HORAS_DEFAULT): array
    {
        return OtelHelper::spanBiz('oficinaauto.producao.resumo_capacidade', function () use ($capacidadeDiaria) {
            $ocupada = $this->capacidadeOcupadaHoje();
            $disponivel = max(0, $capacidadeDiaria - $ocupada);
            $taxa = $capacidadeDiaria > 0 ? round(($ocupada / $capacidadeDiaria) * 100, 2) : 0.0;

            $status = match (true) {
                $taxa > 100  => 'overcommit',
                $taxa >= 90  => 'lotada',
                $taxa >= 70  => 'apertada',
                $taxa >= 30  => 'normal',
                default      => 'ociosa',
            };

            return [
                'ocupada'    => $ocupada,
                'disponivel' => $disponivel,
                'capacidade' => $capacidadeDiaria,
                'taxa'       => $taxa,
                'status'     => $status,
            ];
        }, ['module' => 'OficinaAuto']);
    }
}
