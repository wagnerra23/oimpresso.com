<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado depois que a agenda grava uma instalação da CV (US-COMVIS-007 · UC-FLX-21).
 *
 * Leva só ids: quem ouve relê a instalação filtrando `business_id` (ADR 0093), e o evento não
 * carrega dado pessoal. Ouvinte: `Modules\Whatsapp\Listeners\NotifyInstalacaoCvCustomer` (aviso ao
 * cliente, com opt-in LGPD).
 */
final class InstalacaoAgendada
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly int $instalacaoId,
    ) {}
}
