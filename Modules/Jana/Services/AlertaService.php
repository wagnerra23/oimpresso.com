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
    /**
     * A CONTA — projeção linear × realizado, sem efeito colateral.
     *
     * Extraída de `avaliar()` em 2026-09-02 pra tela `/ia/alertas` listar o MESMO
     * desvio que dispara a notificação (uma fórmula, dois consumidores — senão a
     * lista e o sino discordariam no primeiro ajuste). `null` = sem base pra
     * comparar (sem período vigente, sem apuração ou projetado ≤ 0): o serviço
     * volta calado, nunca inventa alerta.
     *
     * @return array{projetado: float, realizado: float, desvio_pct: float, severidade: string, threshold: float, data_ref: string, dispara: bool}|null
     */
    public function calcular(Meta $meta, ?Carbon $hoje = null): ?array
    {
        $meta->loadMissing(['periodoAtual', 'ultimaApuracao']);

        $periodo        = $meta->periodoAtual;
        $ultimaApuracao = $meta->ultimaApuracao;

        if (! $periodo || ! $ultimaApuracao) {
            return null; // Sem período ativo ou sem apuração — nada a comparar
        }

        $hoje  = $hoje ?: Carbon::today();
        $ini   = Carbon::parse($periodo->data_ini);
        $fim   = Carbon::parse($periodo->data_fim);
        $alvo  = (float) $periodo->valor_alvo;

        // Projeção linear: quanto deveria ter sido realizado até hoje
        $totalDias      = max(1, $ini->diffInDays($fim));
        $diasDecorridos = min($ini->diffInDays($hoje), $totalDias);
        $projetado      = $alvo * ($diasDecorridos / $totalDias);

        if ($projetado <= 0) {
            return null;
        }

        $realizado = (float) $ultimaApuracao->valor_realizado;
        $desvioPct = (($realizado - $projetado) / $projetado) * 100;
        $threshold = (float) config('copiloto.alertas.desvio_threshold_default', 10);
        $dataRef   = $ultimaApuracao->data_ref instanceof Carbon
            ? $ultimaApuracao->data_ref->toDateString()
            : (string) $ultimaApuracao->data_ref;

        return [
            'projetado'  => $projetado,
            'realizado'  => $realizado,
            'desvio_pct' => $desvioPct,
            'severidade' => $this->calcularSeveridade($desvioPct, $threshold),
            'threshold'  => $threshold,
            'data_ref'   => $dataRef,
            'dispara'    => abs($desvioPct) > $threshold,
        ];
    }

    public function avaliar(Meta $meta): void
    {
        // D9.a (Wave 14 governance v3) — span observability zero-cost quando
        // OTel disabled (default). Quando ligado, exporta business_id + meta_id
        // pra correlacionar desvios disparados com tenant. Tier 0 ADR 0093.
        OtelHelper::spanBiz('jana.alerta.avaliar', function () use ($meta) {
            $c = $this->calcular($meta);

            if (! $c || ! $c['dispara']) {
                return;
            }

            event(new CopilotoDesvioDetectado(
                meta_id:    $meta->id,
                desvio_pct: round($c['desvio_pct'], 2),
                severidade: $c['severidade'],
                data_ref:   $c['data_ref'],
            ));
        }, [
            'meta_id' => $meta->id,
            'meta_slug' => $meta->slug,
        ]);
    }

    /**
     * Lista, pra `/ia/alertas`, o desvio de cada meta ativa do business.
     *
     * Escopo Tier 0 (ADR 0093): `business_id` da sessão OU `NULL` (metas da
     * plataforma), o MESMO recorte do `IndexController::buildMetasPayload`.
     * Meta sem base (`calcular` = null) fica de fora — não existe alerta sem com
     * o que comparar. Quem decide se a linha "dispara" é o servidor (`dispara`);
     * a tela só filtra e formata.
     *
     * `status` = novo|lido lê a `MetaDesvioNotification` do usuário logado
     * (`read_at`): é a única forma de "lido" que o sistema tem hoje.
     *
     * @param  array<int, string|null>  $lidasPorMeta  meta_id => read_at (ou null)
     * @return list<array<string, mixed>>
     */
    public function listar(int $businessId, array $lidasPorMeta = []): array
    {
        $metas = Meta::where('ativo', true)
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                  ->orWhereNull('business_id');
            })
            ->with(['periodoAtual', 'ultimaApuracao'])
            ->orderBy('nome')
            ->get();

        $linhas = [];

        foreach ($metas as $meta) {
            $c = $this->calcular($meta);

            if (! $c) {
                continue;
            }

            $linhas[] = [
                'id'         => $meta->id,
                'meta'       => $meta->nome,
                'slug'       => $meta->slug,
                'unidade'    => $meta->unidade,
                'data_ref'   => $c['data_ref'],
                'projetado'  => round($c['projetado'], 2),
                'realizado'  => round($c['realizado'], 2),
                'desvio_pct' => round($c['desvio_pct'], 1),
                'severidade' => $c['severidade'],
                'dispara'    => $c['dispara'],
                'status'     => array_key_exists($meta->id, $lidasPorMeta) && $lidasPorMeta[$meta->id] ? 'lido' : 'novo',
            ];
        }

        return $linhas;
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
