<?php

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Events\CopilotoDesvioDetectado;

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
        // D9.a (Wave 14 governance v3) — span observability zero-cost quando
        // OTel disabled (default). Quando ligado, exporta business_id + meta_id
        // pra correlacionar desvios disparados com tenant. Tier 0 ADR 0093.
        OtelHelper::spanBiz('jana.alerta.avaliar', function () use ($meta) {
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
        }, [
            'meta_id' => $meta->id,
            'meta_slug' => $meta->slug,
        ]);
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
