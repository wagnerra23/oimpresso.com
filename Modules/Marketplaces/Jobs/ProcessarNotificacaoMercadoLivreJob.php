<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;
use Modules\Marketplaces\Services\ContasVendedor;
use RuntimeException;
use Throwable;

/**
 * Processa UMA notificação do Mercado Livre (US-MKT-006): busca o recurso na API com o token da
 * conta e grava/atualiza o pedido em `mkt_orders`. Não cria venda — isso é a US-MKT-007.
 *
 * `business_id` no construtor e em toda consulta. O log de webhook é append-only: o resultado
 * entra como linha nova apontando `superseded_id` para a notificação recebida.
 */
class ProcessarNotificacaoMercadoLivreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const TOPICOS_PEDIDO = ['orders_v2'];

    public int $tries = 3;

    public function __construct(public int $businessId, public int $contaId, public int $logId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ContasVendedor $contas): void
    {
        // SUPERADMIN: job em fila sem sessão; a empresa vem do construtor e entra explícita na consulta.
        $log = WebhookRecebido::withoutGlobalScopes()->where('business_id', $this->businessId)->where('id', $this->logId)->first();
        $conta = ContaVendedor::withoutGlobalScopes()->where('business_id', $this->businessId)->where('id', $this->contaId)->first();
        if ($log === null || $conta === null) {
            return;
        }

        if (! in_array($log->topic, self::TOPICOS_PEDIDO, true) || ! preg_match('#^/orders/(\d+)$#', (string) $log->resource_external, $m)) {
            $this->registrar($log, WebhookRecebido::IGNORADO, 'tópico ou recurso ainda não tratado');

            return;
        }

        try {
            $base = (string) Marketplace::query()->where('id', $conta->marketplace_id)->value('api_base_url');
            $resposta = Http::withToken($contas->tokenValido($conta))->acceptJson()->timeout(15)->get($base.'/orders/'.$m[1]);
            if (! $resposta->successful()) {
                throw new RuntimeException('Mercado Livre devolveu HTTP '.$resposta->status().' para o pedido.');
            }
            $pedido = $resposta->json();
            if ((string) ($pedido['seller']['id'] ?? '') !== (string) $conta->seller_id_external) {
                throw new RuntimeException('Pedido de outro vendedor — ignorado.');
            }

            $this->gravarPedido($conta, $pedido);
            $this->registrar($log, WebhookRecebido::PROCESSADO, null);
        } catch (Throwable $e) {
            $this->registrar($log, WebhookRecebido::FALHOU, $e->getMessage());
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /** @param  array<string, mixed>  $p */
    private function gravarPedido(ContaVendedor $conta, array $p): void
    {
        // firstOrNew com a empresa explícita: a chave única do banco (empresa, marketplace, id)
        // SUPERADMIN: garante que notificação repetida atualiza em vez de duplicar.
        $pedido = PedidoMarketplace::withoutGlobalScopes()->firstOrNew([
            'business_id' => $this->businessId,
            'marketplace_id' => (int) $conta->marketplace_id,
            'order_id_external' => (string) $p['id'],
        ]);

        $pedido->fill([
            'account_id' => (int) $conta->id,
            'status_external' => isset($p['status']) ? (string) $p['status'] : null,
            'buyer_external' => ['id' => $p['buyer']['id'] ?? null, 'nickname' => $p['buyer']['nickname'] ?? null],
            'total_amount' => $p['total_amount'] ?? null,
            'shipping_type' => $p['shipping']['logistic_type'] ?? null,
            'date_created_external' => isset($p['date_created']) ? Carbon::parse($p['date_created']) : null,
            'date_closed_external' => isset($p['date_closed']) ? Carbon::parse($p['date_closed']) : null,
            'raw_payload' => $p,
            'processed_at' => now(),
        ])->save();
    }

    private function registrar(WebhookRecebido $recebido, string $status, ?string $erro): void
    {
        // SUPERADMIN: job em fila sem sessão; a empresa vem do construtor e entra explícita na consulta.
        WebhookRecebido::withoutGlobalScopes()->create([
            'business_id' => $this->businessId,
            'marketplace_id' => (int) $recebido->marketplace_id,
            'topic' => $recebido->topic,
            'resource_external' => $recebido->resource_external,
            'payload' => null,
            'signature_valid' => (bool) $recebido->signature_valid,
            'processing_status' => $status,
            'error_message' => $erro !== null ? mb_substr($erro, 0, 1000) : null,
            'superseded_id' => (int) $recebido->id,
            'received_at' => now(),
        ]);
    }
}
