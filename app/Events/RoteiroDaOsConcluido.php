<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A última etapa do roteiro de uma OS foi concluída no PCP (US-COMVIS-003 · fechamento da OS).
 *
 * Quem dispara: Modules\Producao\Services\SituacaoDaOrdem, para OS que não é dele (`os_tipo`
 * diferente de `pcp_ordem`). Quem escuta: o dono da OS — hoje a Comunicação Visual (`comvis_os`).
 * O núcleo não importa módulo (DependencyDirectionTest): o evento só carrega ids.
 *
 * Tier 0: `businessId` vai junto; quem escuta filtra por ele (fila e CLI não têm sessão).
 */
final class RoteiroDaOsConcluido
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly string $osTipo,
        public readonly int $osId,
        public readonly int $userId,
    ) {}
}
