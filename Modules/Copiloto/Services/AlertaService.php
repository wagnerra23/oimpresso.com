<?php

namespace Modules\Copiloto\Services;

use Carbon\Carbon;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Events\CopilotoDesvioDetectado;

/**
 * AlertaService — compara realizado × projetado e dispara notificações.
 *
 * Projeção: linear entre data_ini e data_fim do MetaPeriodo vigente.
 * Desvio: ((realizado - projetado) / projetado) * 100
 * Se |desvio| > threshold → dispara CopilotoDesvioDetectado.
 */
class AlertaService
{
    public function avaliar(Meta $meta): void
    {
        $meta->loadMissing(['periodoAtual', 'ultimaApuracao']);

        $periodo = $meta->periodoAtual;

        if (! $periodo) {
            return; // Sem período ativo — nada a comparar
        }

        $ultimaApuracao = $meta->ultimaApuracao;

        if (! $ultimaApuracao) {
            return; // Sem apuração — nada a comparar
        }

        $hoje  = Carbon::today();
        $ini   = Carbon::parse($periodo->data_ini);
        $fim   = Carbon::parse($periodo->data_fim);
        $alvo  = (float) $periodo->valor_alvo;

        // Projeção linear: quanto deveria ter sido realizado até hoje
        $totalDias      = max(1, $ini->diffInDays($fim));
        $diasDecorridos = min($ini->diffInDays($hoje), $totalDias);
        $projetado      = $alvo * ($diasDecorridos / $totalDias);

        if ($projetado <= 0) {
            return;
        }

        $realizado  = (float) $ultimaApuracao->valor_realizado;
        $desvioPct  = (($realizado - $projetado) / $projetado) * 100;
        $threshold  = (float) config('copiloto.alertas.desvio_threshold_default', 10);

        if (abs($desvioPct) <= $threshold) {
            return;
        }

        $severidade = $this->calcularSeveridade($desvioPct, $threshold);
        $dataRef    = $ultimaApuracao->data_ref instanceof Carbon
            ? $ultimaApuracao->data_ref->toDateString()
            : (string) $ultimaApuracao->data_ref;

        event(new CopilotoDesvioDetectado(
            meta_id:    $meta->id,
            desvio_pct: round($desvioPct, 2),
            severidade: $severidade,
            data_ref:   $dataRef,
        ));
    }

    /**
     * Calcula severidade com base no tamanho do desvio relativo ao threshold.
     */
    protected function calcularSeveridade(float $desvioPct, float $threshold): string
    {
        $abs = abs($desvioPct);

        if ($abs >= $threshold * 3) {
            return 'alta';
        }

        if ($abs >= $threshold * 1.5) {
            return 'media';
        }

        return 'baixa';
    }
}
