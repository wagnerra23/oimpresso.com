<?php

declare(strict_types=1);

namespace Modules\Marketplaces\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Marketplaces\Jobs\EmitirNfeDoPedidoJob;

/**
 * Efeito colateral da ação `marcar_enviado` do processo `venda_marketplace` (US-MKT-009):
 * põe na fila a emissão da NF-e do pedido. Com `marketplaces.nfe.automatica` desligada
 * (padrão até [W] conferir o CFOP com o contador), não faz nada — a transição anda igual.
 */
class EmitirNfeAoEnviar implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! config('marketplaces.nfe.automatica', false)) {
            return;
        }

<<<<<<< HEAD:Modules/Marketplaces/SideEffects/EmitirNfeAoEnviar.php
        EmitirNfeDoPedidoJob::dispatch((int) $subject->getAttribute('business_id'), (int) $subject->getKey())->afterCommit();
=======
        AvaliarNfeDoPedidoJob::dispatch((int) $subject->getAttribute('business_id'), (int) $subject->getKey())->afterCommit();
>>>>>>> claude/mkt-009b-destinatario:Modules/Marketplaces/SideEffects/AvaliarNfeAoEnviar.php
    }
}
