<?php

namespace Modules\Copiloto\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando AlertaService detecta desvio acima do threshold.
 * Ver SPEC.md seção "Eventos de domínio".
 */
class CopilotoDesvioDetectado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int    $meta_id    ID da meta com desvio.
     * @param float  $desvio_pct Percentual de desvio (positivo = acima, negativo = abaixo).
     * @param string $severidade 'baixa' | 'media' | 'alta'
     * @param string $data_ref   Data de referência da última apuração (Y-m-d).
     */
    public function __construct(
        public readonly int $meta_id,
        public readonly float $desvio_pct,
        public readonly string $severidade,
        public readonly string $data_ref,
    ) {
    }
}
