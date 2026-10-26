<?php

declare(strict_types=1);

namespace Modules\Marketplaces\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Marketplaces\Jobs\AvaliarNfeDoPedidoJob;

/**
 * Efeito colateral da ação `marcar_enviado` do processo `venda_marketplace` (US-MKT-009):
 * põe na fila a AVALIAÇÃO da NF-e do pedido (a emissão em si ainda não está ligada — ver o job). Com `marketplaces.nfe.automatica` desligada
 * (padrão até [W] conferir o CFOP com o contador), não faz nada — a transição anda igual.
 */
class AvaliarNfeAoEnviar implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! config('marketplaces.nfe.automatica', false)) {
            return;
        }

        AvaliarNfeDoPedidoJob::dispatch((int) $subject->business_id, (int) $subject->getKey())->afterCommit();
    }
}
