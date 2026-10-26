<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Jobs;

use App\BusinessLocation;
use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Services\Fiscal\CfopDoPedido;

/**
 * Prepara a NF-e de uma venda de marketplace (US-MKT-009 — PARCIAL).
 *
 * Hoje só decide se a nota PODE sair e por quê não: flag ligada, venda da empresa, pedido do
 * marketplace, CFOP resolvido (UF do local × UF do destinatário, sem Full) e destinatário com
 * documento. O envio à SEFAZ ainda não é chamado: falta (1) buscar o destinatário no ML
 * (`/orders/{id}/billing_info` + endereço do envio) e (2) o NfeService aceitar o CFOP do
 * marketplace — os dois entram em PRs próprios, o segundo combinado com a frente Fiscal.
 * Enquanto isso, nenhum caminho deste job emite nota.
 */
class AvaliarNfeDoPedidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $businessId, public int $transactionId)
    {
    }

    /** @return array{pode: bool, motivo: ?string, cfop: ?string} */
    public static function avaliar(int $businessId, int $transactionId): array
    {
        $nao = fn (string $m) => ['pode' => false, 'motivo' => $m, 'cfop' => null];

        if (! config('marketplaces.nfe.automatica', false)) {
            return $nao('emissão automática desligada (marketplaces.nfe.automatica)');
        }
        // SUPERADMIN: job sem sessão; empresa explícita na consulta.
        $venda = Transaction::withoutGlobalScopes()->where('business_id', $businessId)->where('id', $transactionId)->first();
        $pedido = PedidoMarketplace::withoutGlobalScopes()->where('business_id', $businessId)->where('transaction_id', $transactionId)->first();
        if ($venda === null || $pedido === null) {
            return $nao('venda sem pedido de marketplace');
        }

        $ufEmitente = BusinessLocation::query()->where('business_id', $businessId)->where('id', $venda->location_id)->value('state');
        $destinatario = (array) ($pedido->buyer_external ?? []);
        $cfop = CfopDoPedido::resolver($ufEmitente, $destinatario['uf'] ?? null, $pedido->shipping_type);
        if ($pedido->shipping_type === 'fulfillment') {
            return $nao('venda saída do estoque Full: CFOP depende de decisão do contador');
        }
        if (empty($destinatario['documento']) || empty($destinatario['uf'])) {
            return $nao('destinatário incompleto (documento e UF vêm do billing_info do ML)');
        }
        if ($cfop === null) {
            return $nao('CFOP do marketplace não configurado (depende do contador)');
        }

        return ['pode' => true, 'motivo' => null, 'cfop' => $cfop];
    }

    public function handle(): void
    {
        $avaliacao = self::avaliar($this->businessId, $this->transactionId);
        Log::info('marketplaces: NF-e do pedido avaliada (emissão ainda não ligada)', [
            'business_id' => $this->businessId,
            'transaction_id' => $this->transactionId,
            'pode' => $avaliacao['pode'],
            'motivo' => $avaliacao['motivo'],
            'cfop' => $avaliacao['cfop'],
        ]);
    }
}
