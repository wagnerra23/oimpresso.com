<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * FluxoCaixaService — projeção de fluxo de caixa por dia.
 *
 * Read-only orchestrator: agrega Titulo (futuros) + TituloBaixa (histórico) +
 * ContaBancaria.saldo_cached em estrutura array pronta pro Inertia render.
 *
 * Sem mutação financeira. Sem schema novo. Não exige ADR arq/* (read-side puro).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id explícito como 1º arg. Models
 * usam BusinessScope global scope, então queries filtram automaticamente — mas
 * forçamos businessId no scope local também (defesa em profundidade).
 *
 * Decisões aplicadas (Wagner aprovou 2026-05-14):
 *  Q1: Saldo hoje = SUM(ContaBancaria.saldo_cached) WHERE ativo
 *  Q2: 35 dias fixo (parametrizável em F2 via arg $dias)
 *  Q3: Margem mínima R$ [redacted Tier 0] hardcode (configurável em F2 via business_settings)
 *  Q4: Histórico -2 dias com label "últimas 48h"
 */
class FluxoCaixaService
{
    private const MARGEM_MINIMA_PADRAO = 5000.0;

    private const HISTORICO_DIAS = 2;

    /**
     * Projeta fluxo de caixa pro business nos próximos N dias.
     *
     * Retorna estrutura pronta pra Inertia::render('Financeiro/Fluxo/Index', $shape).
     *
     * @return array{
     *   saldo_hoje: float,
     *   saldo_30d: float,
     *   pior_dia: array{saldo: float, data_label: string},
     *   margem_minima: float,
     *   conta: string,
     *   dias: array<int, array{
     *     data: string, data_label: string, is_today: bool, is_past: bool,
     *     entradas: float, saidas: float, liquido: float, saldo_acumulado: float,
     *     eventos: array<int, array{id: int, kind: string, descricao: string, contraparte: string, categoria: string, valor: float}>
     *   }>
     * }
     */
    public function projetar(int $businessId, int $dias = 35): array
    {
        $hoje = CarbonImmutable::today();
        $inicioHistorico = $hoje->subDays(self::HISTORICO_DIAS);
        $fimProjecao = $hoje->addDays($dias);

        // ─────────────────────────── Saldo hoje (Q1) ───────────────────────────
        // SUM(saldo_cached) das contas ativas. Skip null.
        $saldoHoje = (float) ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNotNull('saldo_cached')
            ->sum('saldo_cached');

        $contaPrincipal = $this->resolverContaPrincipal($businessId);

        // ─────────────────────── Títulos futuros (vencimento) ───────────────────────
        $titulosFuturos = Titulo::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['aberto', 'parcial'])
            ->whereBetween('vencimento', [$hoje->toDateString(), $fimProjecao->toDateString()])
            ->whereNull('deleted_at')
            ->with('categoria:id,nome')
            ->orderBy('vencimento')
            ->get();

        // ─────────────────────── Baixas históricas (data_baixa) ───────────────────────
        $baixasHistoricas = TituloBaixa::query()
            ->where('business_id', $businessId)
            ->whereBetween('data_baixa', [$inicioHistorico->toDateString(), $hoje->subDay()->toDateString()])
            ->whereNull('estorno_de_id') // ignora estornos
            ->with(['titulo:id,tipo,cliente_descricao,categoria_id', 'titulo.categoria:id,nome'])
            ->orderBy('data_baixa')
            ->get();

        // ───────────────── Monta array de dias (histórico + hoje + projeção) ─────────────────
        $diasArray = [];
        $saldoAcumulado = $saldoHoje;

        // Snapshot do saldo no início do histórico (saldo hoje − baixas históricas)
        // (Aproximação simples: assume que saldo_cached é "agora" e baixas dos últimos 2d já refletiram)
        $totalBaixasHistoricas = (float) $baixasHistoricas->sum(
            fn (TituloBaixa $b) => $this->valorComSinal($b)
        );
        $saldoNoInicio = $saldoHoje - $totalBaixasHistoricas;

        $saldoAcumulado = $saldoNoInicio;

        for ($d = $inicioHistorico; $d->lte($fimProjecao); $d = $d->addDay()) {
            $isToday = $d->isSameDay($hoje);
            $isPast = $d->lt($hoje);
            $dataIso = $d->toDateString();

            $eventos = [];
            $entradasDia = 0.0;
            $saidasDia = 0.0;

            if ($isPast) {
                // Histórico: baixas realizadas
                foreach ($baixasHistoricas->where('data_baixa', $d->toDateString()) as $b) {
                    /** @var TituloBaixa $b */
                    $titulo = $b->titulo;
                    if (! $titulo) {
                        continue;
                    }
                    $valor = (float) $b->valor_baixa;
                    $kind = $titulo->tipo === 'receber' ? 'receivable' : 'payable';
                    if ($kind === 'receivable') {
                        $entradasDia += $valor;
                    } else {
                        $saidasDia += $valor;
                    }
                    $eventos[] = [
                        'id'          => (int) $b->id,
                        'kind'        => $kind,
                        'descricao'   => $this->descricaoBaixa($b, $titulo),
                        'contraparte' => (string) ($titulo->cliente_descricao ?? '—'),
                        'categoria'   => (string) ($titulo->categoria?->nome ?? '—'),
                        'valor'       => round($valor, 2),
                    ];
                }
            } else {
                // Futuro: títulos com vencimento neste dia
                foreach ($titulosFuturos->where('vencimento', $d->toDateString()) as $t) {
                    /** @var Titulo $t */
                    $valor = (float) $t->valor_aberto;
                    $kind = $t->tipo === 'receber' ? 'receivable' : 'payable';
                    if ($kind === 'receivable') {
                        $entradasDia += $valor;
                    } else {
                        $saidasDia += $valor;
                    }
                    $eventos[] = [
                        'id'          => (int) $t->id,
                        'kind'        => $kind,
                        'descricao'   => $this->descricaoTitulo($t),
                        'contraparte' => (string) ($t->cliente_descricao ?? '—'),
                        'categoria'   => (string) ($t->categoria?->nome ?? '—'),
                        'valor'       => round($valor, 2),
                    ];
                }
            }

            $liquido = $entradasDia - $saidasDia;
            $saldoAcumulado += $liquido;

            $diasArray[] = [
                'data'            => $dataIso,
                'data_label'      => $d->locale('pt_BR')->isoFormat('DD MMM'),
                'is_today'        => $isToday,
                'is_past'         => $isPast,
                'entradas'        => round($entradasDia, 2),
                'saidas'          => round($saidasDia, 2),
                'liquido'         => round($liquido, 2),
                'saldo_acumulado' => round($saldoAcumulado, 2),
                'eventos'         => $eventos,
            ];
        }

        // ─────────────────────────── KPIs derivados ───────────────────────────
        $saldo30d = $this->saldoEm($diasArray, $hoje->addDays(30));
        $piorDia = $this->piorDia($diasArray, $hoje);

        return [
            'saldo_hoje'    => round($saldoHoje, 2),
            'saldo_30d'     => round($saldo30d, 2),
            'pior_dia'      => $piorDia,
            'margem_minima' => self::MARGEM_MINIMA_PADRAO,
            'conta'         => $contaPrincipal,
            'dias'          => $diasArray,
        ];
    }

    /**
     * Resolve nome da conta principal pro caption do KPI "Saldo hoje".
     * Estratégia: conta ativa pra boleto + ID mais baixo; fallback primeira conta.
     */
    private function resolverContaPrincipal(int $businessId): string
    {
        $conta = ContaBancaria::query()
            ->where('business_id', $businessId)
            ->where('ativo_para_boleto', true)
            ->whereNull('deleted_at')
            ->with('account:id,name')
            ->orderBy('id')
            ->first()
            ?: ContaBancaria::query()
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->with('account:id,name')
                ->orderBy('id')
                ->first();

        if (! $conta) {
            return 'Sem conta cadastrada';
        }

        $qtdContas = ContaBancaria::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->count();

        if ($qtdContas > 1) {
            return $conta->nome.' (+ '.($qtdContas - 1).' outras)';
        }

        return $conta->nome;
    }

    /**
     * Valor com sinal pra cálculo do saldo no início do histórico:
     * baixa de a-receber soma positivo; baixa de a-pagar soma negativo.
     */
    private function valorComSinal(TituloBaixa $baixa): float
    {
        $titulo = $baixa->titulo;
        if (! $titulo) {
            return 0.0;
        }
        $valor = (float) $baixa->valor_baixa;

        return $titulo->tipo === 'receber' ? $valor : -$valor;
    }

    private function descricaoBaixa(TituloBaixa $baixa, Titulo $titulo): string
    {
        $tipo = $titulo->tipo === 'receber' ? 'Recebimento' : 'Pagamento';
        $numero = $titulo->numero ? ' #'.$titulo->numero : '';

        return $tipo.$numero;
    }

    private function descricaoTitulo(Titulo $titulo): string
    {
        $tipo = $titulo->tipo === 'receber' ? 'A receber' : 'A pagar';
        $numero = $titulo->numero ? ' #'.$titulo->numero : '';

        return $tipo.$numero;
    }

    private function saldoEm(array $dias, CarbonImmutable $target): float
    {
        $alvo = $target->toDateString();
        foreach ($dias as $dia) {
            if ($dia['data'] === $alvo) {
                return (float) $dia['saldo_acumulado'];
            }
        }

        // Se a data alvo está fora da projeção, retorna o último saldo conhecido
        return ! empty($dias) ? (float) end($dias)['saldo_acumulado'] : 0.0;
    }

    /**
     * @return array{saldo: float, data_label: string}
     */
    private function piorDia(array $dias, CarbonImmutable $hoje): array
    {
        $pior = null;
        foreach ($dias as $dia) {
            if ($dia['is_past']) {
                continue;
            }
            if ($pior === null || $dia['saldo_acumulado'] < $pior['saldo_acumulado']) {
                $pior = $dia;
            }
        }

        if ($pior === null) {
            return ['saldo' => 0.0, 'data_label' => '—'];
        }

        return [
            'saldo'      => (float) $pior['saldo_acumulado'],
            'data_label' => (string) $pior['data_label'],
        ];
    }
}
