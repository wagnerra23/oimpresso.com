<?php

namespace Modules\PontoWr2\Services;

use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Modules\PontoWr2\Entities\ApuracaoDia;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Marcacao;
use RuntimeException;

/**
 * Gerador de relatórios do módulo Ponto.
 *
 * Hoje:
 *   - espelhoPdf(): espelho mensal por colaborador (PDF via barryvdh/laravel-dompdf)
 *
 * Stub (retorna 501):
 *   - afd(), afdt(), aej(), he(), bancoHoras(), atrasos(), esocial()
 */
class ReportService
{
    /**
     * Gera PDF do espelho de ponto de um colaborador no mês informado.
     *
     * @param Colaborador $colaborador
     * @param string $mes formato YYYY-MM
     * @return \Barryvdh\DomPDF\PDF (objeto DomPDF — chamar ->stream() ou ->download())
     */
    public function espelhoPdf(Colaborador $colaborador, $mes)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            throw new RuntimeException('Mês inválido (formato esperado YYYY-MM).');
        }

        list($ano, $mesNum) = explode('-', $mes);
        $inicio = Carbon::createFromDate((int) $ano, (int) $mesNum, 1)->startOfMonth();
        $fim    = $inicio->copy()->endOfMonth();

        $apuracoes = ApuracaoDia::where('colaborador_config_id', $colaborador->id)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->orderBy('data')
            ->get();

        $marcacoes = Marcacao::where('colaborador_config_id', $colaborador->id)
            ->whereBetween('momento', [$inicio->toDateTimeString(), $fim->copy()->endOfDay()->toDateTimeString()])
            ->whereNotIn('origem', [Marcacao::ORIGEM_ANULACAO])
            ->orderBy('momento')
            ->get()
            ->groupBy(function ($m) {
                return $m->momento->toDateString();
            });

        // Totais mensais
        $totais = [
            'trabalhado'       => 0,
            'atraso'           => 0,
            'falta'            => 0,
            'he_diurna'        => 0,
            'he_noturna'       => 0,
            'adicional_not'    => 0,
            'bh_credito'       => 0,
            'bh_debito'        => 0,
            'dsr_repercussao'  => 0,
        ];
        foreach ($apuracoes as $a) {
            $totais['trabalhado']      += (int) $a->realizada_trabalhada_minutos;
            $totais['atraso']          += (int) $a->atraso_minutos;
            $totais['falta']           += (int) $a->falta_minutos;
            $totais['he_diurna']       += (int) $a->he_diurna_minutos;
            $totais['he_noturna']      += (int) $a->he_noturna_minutos;
            $totais['adicional_not']   += (int) $a->adicional_noturno_minutos;
            $totais['bh_credito']      += (int) $a->banco_horas_credito_minutos;
            $totais['bh_debito']       += (int) $a->banco_horas_debito_minutos;
            $totais['dsr_repercussao'] += (int) $a->dsr_repercussao_minutos;
        }

        $data = [
            'colaborador' => $colaborador,
            'mes'         => $mes,
            'inicio'      => $inicio,
            'fim'         => $fim,
            'apuracoes'   => $apuracoes,
            'marcacoes'   => $marcacoes,
            'totais'      => $totais,
            'gerado_em'   => now(),
        ];

        $pdf = PDF::loadView('pontowr2::reports.espelho-pdf', $data);
        $pdf->setPaper('a4', 'portrait');
        return $pdf;
    }

    /**
     * Nome de arquivo sugerido para o espelho.
     */
    public function espelhoPdfNome(Colaborador $colaborador, $mes)
    {
        $matricula = $colaborador->matricula ?: ('colab-' . $colaborador->id);
        return "espelho-ponto_{$matricula}_{$mes}.pdf";
    }

    // ---- Stubs (501) ----

    public function afd($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Gerador AFD ainda não implementado.');
    }

    public function afdt($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Gerador AFDT ainda não implementado.');
    }

    public function aej($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Gerador AEJ ainda não implementado.');
    }

    public function he($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Relatório de HE ainda não implementado.');
    }

    public function bancoHoras($businessId, Carbon $data)
    {
        throw new RuntimeException('Relatório de BH ainda não implementado.');
    }

    public function atrasos($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Relatório de atrasos/faltas ainda não implementado.');
    }

    public function esocial($businessId, Carbon $competencia)
    {
        throw new RuntimeException('Geração de eventos eSocial ainda não implementada.');
    }
}
