<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado depois que a instalação da CV é concluída com foto antes, foto depois e assinatura
 * (US-COMVIS-007 · UC-PCV-25 · 1d).
 *
 * Leva os ids e o link público do protocolo (assinado e com validade, `ProtocoloInstalacao::linkPublico`),
 * para quem ouve não precisar depender do serviço da CV. O link não é dado pessoal: sem a assinatura ele
 * não abre nada. Quem ouve relê a instalação filtrando `business_id` (ADR 0093).
 * Ouvinte: `Modules\Whatsapp\Listeners\NotifyInstalacaoConcluidaCvCustomer` (protocolo ao cliente, opt-in LGPD).
 */
final class InstalacaoConcluida
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly int $instalacaoId,
        public readonly string $linkProtocolo,
        public readonly int $diasDoLink,
    ) {}
}
