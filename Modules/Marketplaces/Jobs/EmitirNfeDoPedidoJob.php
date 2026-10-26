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
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Throwable;

/**
 * Emite a NF-e (modelo 55) de uma venda de marketplace (US-MKT-009).
 *
 * Só emite quando TUDO vale, e a decisão fica em `avaliar()` (testável sem SEFAZ): flag
 * `marketplaces.nfe.automatica` ligada, venda da empresa vinda de pedido de marketplace, fora do
 * Full, destinatário completo vindo do faturamento do ML e CFOP configurado pelo contador.
 * A emissão usa a interface combinada com a frente Fiscal (#9921):
 * `NfeService::emitirParaTransaction($venda, '55', false, $destinatario, $cfop)` — o destinatário
 * nas chaves de `contacts` e o CFOP vencendo o da regra em todos os itens.
 * Venda que já tem NF-e 55 não é emitida de novo. Falha é registrada sem dado pessoal.
 */
class EmitirNfeDoPedidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $businessId, public int $transactionId)
    {
    }

    /** @return array{pode: bool, motivo: ?string, cfop: ?string, destinatario?: array<string, mixed>} */
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
        if (empty($destinatario['documento']) || empty($destinatario['uf']) || empty($destinatario['destinatario']['tax_number'])) {
            return $nao('destinatário incompleto (documento e UF vêm do billing_info do ML)');
        }
        if ($cfop === null) {
            return $nao('CFOP do marketplace não configurado (depende do contador)');
        }

        return ['pode' => true, 'motivo' => null, 'cfop' => $cfop, 'destinatario' => (array) $destinatario['destinatario']];
    }

    public function handle(): void
    {
        $avaliacao = self::avaliar($this->businessId, $this->transactionId);
        if (! $avaliacao['pode']) {
            Log::info('marketplaces: NF-e do pedido não emitida', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
                'motivo' => $avaliacao['motivo'],
            ]);

            return;
        }

        $jaTem = NfeEmissao::query()->where('business_id', $this->businessId)->where('transaction_id', $this->transactionId)
            ->where('modelo', '55')->whereNotIn('status', ['rejeitada', 'denegada', 'erro_envio', 'inutilizada'])->exists();
        if ($jaTem) {
            return;
        }

        // SUPERADMIN: job sem sessão; empresa explícita na consulta.
        $venda = Transaction::withoutGlobalScopes()->where('business_id', $this->businessId)->where('id', $this->transactionId)->firstOrFail();

        try {
            $emissao = app(NfeService::class)->emitirParaTransaction($venda, '55', false, $avaliacao['destinatario'], $avaliacao['cfop']);
            Log::info('marketplaces: NF-e do pedido processada', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
                'status' => $emissao->status,
                'cfop' => $avaliacao['cfop'],
            ]);
        } catch (Throwable $e) {
            Log::warning('marketplaces: NF-e do pedido falhou', [
                'business_id' => $this->businessId,
                'transaction_id' => $this->transactionId,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
