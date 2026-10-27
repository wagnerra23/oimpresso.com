<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Listeners;

use App\Events\RoteiroDaOsConcluido;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Services\RoteiroPadraoDaOs;

/**
 * A última etapa do roteiro concluída no PCP fecha a OS da gráfica: `status_etapa = concluida` e
 * `data_conclusao` = hoje (decisão [W] 2026-10-27: "siga com o fechamento da OS pela última etapa").
 * É o passo antes da venda derivada (ADR 0192).
 *
 * Atrás de `comunicacao-visual.fecha_os_pelo_roteiro` (COMVIS_FECHA_OS_PELO_ROTEIRO, desligada).
 * Só mexe em OS aberta: concluída ou cancelada fica como está, e repetir o evento não muda nada.
 * Não mexe em valor nem em estoque. Fica no log de atividade do Os (LogsActivity).
 *
 * Tier 0: a OS é buscada pelo `businessId` do evento, sem o escopo de sessão (o evento pode vir
 * de fila ou CLI); OS de outra empresa não é encontrada.
 */
final class FechaOsPeloRoteiro
{
    public function handle(RoteiroDaOsConcluido $evento): void
    {
        if (! config('comunicacao-visual.fecha_os_pelo_roteiro') || $evento->osTipo !== RoteiroPadraoDaOs::OS_TIPO) {
            return;
        }

        // SUPERADMIN: o escopo da sessão não vale aqui; o business_id do evento filtra explicitamente.
        $os = Os::withoutGlobalScope('business_id')->where('business_id', $evento->businessId)->find($evento->osId);
        if ($os === null || in_array($os->status_etapa, ['concluida', 'cancelada'], true)) {
            return;
        }

        $os->status_etapa = 'concluida';
        $os->data_conclusao = now()->toDateString();
        $os->save();
    }
}
