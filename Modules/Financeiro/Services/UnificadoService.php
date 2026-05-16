<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Carbon\CarbonImmutable;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;

/**
 * UnificadoService — facade read-only de KPIs do Cockpit Unificado V2.
 *
 * Thin facade — extrai blocos de cálculo de KPI que estavam inline no
 * UnificadoController (US-FIN-013 / US-FIN-020). Não muta estado.
 * Persona-foco: Eliana [E] (escritório financeiro, densidade alta).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id explícito no 1º arg.
 * Models usam BusinessScope global scope (defesa em profundidade).
 *
 * Coexiste com FluxoCaixaService (projeção 35d) e TituloService (boletos);
 * este aqui só serve a tela /financeiro/unificado.
 */
class UnificadoService
{
    /**
     * KPIs agregados pra header do Cockpit Unificado.
     *
     * @return array{
     *   total_receber: float,
     *   total_pagar: float,
     *   atrasados_count: int,
     *   atrasados_valor: float,
     *   saldo_bancario: float,
     *   conta_label: string
     * }
     */
    public function kpis(int $businessId, ?CarbonImmutable $hoje = null): array
    {
        $hoje = $hoje ?? CarbonImmutable::today();
        $hojeIso = $hoje->toDateString();

        $totalReceber = (float) Titulo::query()
            ->where('business_id', $businessId)
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial'])
            ->whereNull('deleted_at')
            ->sum('valor_aberto');

        $totalPagar = (float) Titulo::query()
            ->where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereIn('status', ['aberto', 'parcial'])
            ->whereNull('deleted_at')
            ->sum('valor_aberto');

        $atrasados = Titulo::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('vencimento', '<', $hojeIso)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(valor_aberto), 0) as total')
            ->first();

        $saldoBancario = (float) ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->whereNotNull('saldo_cached')
            ->sum('saldo_cached');

        $contaLabel = $this->contaLabel($businessId);

        return [
            'total_receber'   => round($totalReceber, 2),
            'total_pagar'     => round($totalPagar, 2),
            'atrasados_count' => (int) ($atrasados->cnt ?? 0),
            'atrasados_valor' => round((float) ($atrasados->total ?? 0), 2),
            'saldo_bancario'  => round($saldoBancario, 2),
            'conta_label'     => $contaLabel,
        ];
    }

    /**
     * Resolve label da conta principal (primeira ativa-pra-boleto + sufixo "+N").
     */
    private function contaLabel(int $businessId): string
    {
        $conta = ContaBancaria::query()
            ->where('business_id', $businessId)
            ->where('ativo_para_boleto', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first()
            ?: ContaBancaria::query()
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

        if (! $conta) {
            return 'Sem conta cadastrada';
        }

        $qtd = ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->count();

        return $qtd > 1
            ? $conta->nome.' (+ '.($qtd - 1).' outras)'
            : (string) $conta->nome;
    }
}
