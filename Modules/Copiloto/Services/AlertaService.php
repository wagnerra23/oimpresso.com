<?php

namespace Modules\Copiloto\Services;

use Modules\Copiloto\Entities\Meta;

/**
 * AlertaService — compara realizado × projetado e dispara notificações.
 * STUB spec-ready.
 */
class AlertaService
{
    public function avaliar(Meta $meta): void
    {
        // TODO:
        // 1. Calcular projetado até a data atual a partir do MetaPeriodo (trajetória linear default).
        // 2. Comparar com última MetaApuracao.
        // 3. Se |desvio| > threshold (config), emitir event CopilotoDesvioDetectado.
        // 4. Notificar pelos canais configurados (in_app padrão; email/whatsapp opcional).
    }
}
