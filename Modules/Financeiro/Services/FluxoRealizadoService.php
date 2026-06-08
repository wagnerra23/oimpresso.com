<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * FluxoRealizadoService — visão histórica do fluxo de caixa realizado.
 *
 * Agrega `fin_titulo_baixas` por YYYY-MM nos últimos N meses:
 *   - entradas = SUM(valor_baixa) WHERE titulo.tipo = 'receber'
 *   - saidas   = SUM(valor_baixa) WHERE titulo.tipo = 'pagar'
 *   - saldo    = entradas - saidas
 *
 * Origem: Fase 3 deprecação legacy — absorve Cash Flow legacy do core
 * UltimatePOS (`/account/cash-flow` → `AccountController::cashFlow()`),
 * já redirecionado pra `/financeiro/fluxo` via PR #1283 (Fase 2).
 *
 * Read-only, sem mutação. Sem schema novo.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   - business_id explícito em TODAS queries (defesa em profundidade)
 *   - TituloBaixa usa BusinessScope global scope; ainda assim explicitamos
 *   - Estornos ignorados (estorno_de_id IS NULL) — preserva ledger style
 *
 * Decisões Fase 3 (Wagner aprovou 2026-05-21):
 *   - Janela = 12 meses (hardcode F3; opt-in via ?meses=N range 1..36)
 *   - Granularidade = mês (não diário; Realizado é visão histórica/contábil)
 *   - Ignora estornos (consistente com FluxoCaixaService)
 *   - Inclui mês atual (mes corrente acumula até hoje)
 */
class FluxoRealizadoService
{
    /**
     * Busca movimentações realizadas agrupadas por mês.
     *
     * @return array{
     *   meta: array{
     *     meses_janela: int,
     *     primeiro_mes: string,
     *     ultimo_mes: string,
     *     business_id: int
     *   },
     *   totais: array{
     *     entradas: float,
     *     saidas: float,
     *     saldo: float,
     *     qtd_baixas: int
     *   },
     *   meses: array<int, array{
     *     mes: string,
     *     mes_label: string,
     *     ano: int,
     *     entradas: float,
     *     saidas: float,
     *     saldo: float,
     *     qtd_baixas: int,
     *     is_current: bool
     *   }>
     * }
     */
    public function buscar(int $businessId, int $meses = 12): array
    {
        return OtelHelper::spanBiz('financeiro.fluxo_realizado.buscar', function () use ($businessId, $meses): array {
            return $this->buscarInternal($businessId, $meses);
        }, [
            'business_id' => $businessId,
            'meses' => $meses,
        ]);
    }

    private function buscarInternal(int $businessId, int $meses): array
    {
        $hoje = CarbonImmutable::today();
        $inicio = $hoje->startOfMonth()->subMonths($meses - 1);
        $fim = $hoje->endOfMonth();

        // ───────────────────── Query agregada por mês × tipo ─────────────────────
        // JOIN baixa → titulo (pra ler tipo receber/pagar) com filtro business_id
        // duplicado em ambas tabelas (defesa em profundidade).
        // GROUP BY YEAR(data_baixa), MONTH(data_baixa), titulo.tipo.
        //
        // Estornos (estorno_de_id IS NOT NULL) ficam de fora — consistente com
        // FluxoCaixaService.projetar() histórico.
        $rows = TituloBaixa::query()
            ->from('fin_titulo_baixas as b')
            ->join('fin_titulos as t', function ($join) use ($businessId) {
                $join->on('t.id', '=', 'b.titulo_id')
                     ->where('t.business_id', '=', $businessId);
            })
            ->where('b.business_id', $businessId)
            ->whereBetween('b.data_baixa', [$inicio->toDateString(), $fim->toDateString()])
            ->whereNull('b.estorno_de_id')
            ->whereNull('t.deleted_at')
            ->select([
                DB::raw('YEAR(b.data_baixa) as ano'),
                DB::raw('MONTH(b.data_baixa) as mes_num'),
                't.tipo as tipo',
                DB::raw('SUM(b.valor_baixa) as total'),
                DB::raw('COUNT(*) as qtd'),
            ])
            ->groupBy('ano', 'mes_num', 't.tipo')
            ->orderBy('ano')
            ->orderBy('mes_num')
            ->get();

        // ─────────────────── Indexa rows por chave 'YYYY-MM' ───────────────────
        $idx = [];
        foreach ($rows as $row) {
            $key = sprintf('%04d-%02d', (int) $row->ano, (int) $row->mes_num);
            if (! isset($idx[$key])) {
                $idx[$key] = ['entradas' => 0.0, 'saidas' => 0.0, 'qtd' => 0];
            }
            $valor = (float) $row->total;
            $qtd = (int) $row->qtd;
            if ($row->tipo === 'receber') {
                $idx[$key]['entradas'] += $valor;
            } else {
                $idx[$key]['saidas'] += $valor;
            }
            $idx[$key]['qtd'] += $qtd;
        }

        // ─────────────────── Materializa janela completa (zera meses vazios) ───────────────────
        $mesesArray = [];
        $totaisEntradas = 0.0;
        $totaisSaidas = 0.0;
        $totaisQtd = 0;

        for ($m = $inicio; $m->lte($fim); $m = $m->addMonth()) {
            $key = $m->format('Y-m');
            $entry = $idx[$key] ?? ['entradas' => 0.0, 'saidas' => 0.0, 'qtd' => 0];
            $entradas = round((float) $entry['entradas'], 2);
            $saidas = round((float) $entry['saidas'], 2);
            $saldo = round($entradas - $saidas, 2);
            $qtdBaixas = (int) $entry['qtd'];

            $mesesArray[] = [
                'mes' => $key,
                'mes_label' => $m->locale('pt_BR')->isoFormat('MMM/YY'),
                'ano' => (int) $m->year,
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $saldo,
                'qtd_baixas' => $qtdBaixas,
                'is_current' => $m->isSameMonth($hoje),
            ];

            $totaisEntradas += $entradas;
            $totaisSaidas += $saidas;
            $totaisQtd += $qtdBaixas;
        }

        return [
            'meta' => [
                'meses_janela' => $meses,
                'primeiro_mes' => $inicio->format('Y-m'),
                'ultimo_mes' => $fim->format('Y-m'),
                'business_id' => $businessId,
            ],
            'totais' => [
                'entradas' => round($totaisEntradas, 2),
                'saidas' => round($totaisSaidas, 2),
                'saldo' => round($totaisEntradas - $totaisSaidas, 2),
                'qtd_baixas' => $totaisQtd,
            ],
            'meses' => $mesesArray,
        ];
    }
}
