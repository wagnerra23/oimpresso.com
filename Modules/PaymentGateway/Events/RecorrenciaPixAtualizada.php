<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * O banco avisou que uma recorrência do Pix Automático mudou de status (webhook `recs[]` da API
 * Pix BCB). Status da spec: CRIADA · APROVADA · REJEITADA · EXPIRADA · CANCELADA.
 *
 * O PaymentGateway não sabe de assinatura: quem guarda o mandato (o RecurringBilling) escuta e
 * atualiza o próprio registro.
 */
class RecorrenciaPixAtualizada
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly string $idRec,
        public readonly string $status,
    ) {
    }
}
