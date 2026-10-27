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
use RuntimeException;
use Throwable;

/**
 * Processa UMA notificação de pedido da Nuvemshop (ADR 0443, fatia c): busca o pedido na API da loja
 * com o token da conta e grava/atualiza `mkt_orders`. Pago (`payment_status = paid`) e ainda sem
 * venda dispara o mesmo `VirarVendaJob` do Mercado Livre.
 *
 * `business_id` no construtor e em toda consulta. O log de webhook é append-only: o resultado entra
 * como linha nova apontando `superseded_id` para a notificação recebida.
 * API: GET {api_base}/{store_id}/orders/{id}, `Authorization: Bearer` + `User-Agent` obrigatório.
 */
class ProcessarNotificacaoNuvemshopJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $businessId, public int $contaId, public int $logId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        // SUPERADMIN: job em fila sem sessão; a empresa vem do construtor e entra explícita na consulta.
        $log = WebhookRecebido::withoutGlobalScopes()->where('business_id', $this->businessId)->where('id', $this->logId)->first();
        $conta = ContaVendedor::withoutGlobalScopes()->where('business_id', $this->businessId)->where('id', $this->contaId)
            ->where('status', ContaVendedor::STATUS_ATIVA)->first();
        if ($log === null || $conta === null) {
            return;
        }
        if (! str_starts_with((string) $log->topic, 'order/') || ! preg_match('#^/orders/(\d+)$#', (string) $log->resource_external, $m)) {
            $this->registrar($log, WebhookRecebido::IGNORADO, 'evento ainda não tratado');

            return;
        }

        try {
            $base = rtrim((string) Marketplace::query()->where('id', $conta->marketplace_id)->value('api_base_url'), '/');
            $resposta = Http::withToken((string) $conta->access_token)->acceptJson()->timeout(15)
                ->withHeaders(['User-Agent' => (string) config('marketplaces.nuvemshop.user_agent')])
                ->get($base.'/'.$conta->seller_id_external.'/orders/'.$m[1]);
            if (! $resposta->successful()) {
                throw new RuntimeException('Nuvemshop devolveu HTTP '.$resposta->status().' para o pedido.');
            }
            $pedido = $resposta->json();
            if ((string) ($pedido['store_id'] ?? $conta->seller_id_external) !== (string) $conta->seller_id_external) {
                throw new RuntimeException('Pedido de outra loja — ignorado.');
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
        // SUPERADMIN: firstOrNew com a empresa explícita; a chave única (empresa, canal, id) impede duplicar.
        $pedido = PedidoMarketplace::withoutGlobalScopes()->firstOrNew([
            'business_id' => $this->businessId,
            'marketplace_id' => (int) $conta->marketplace_id,
            'order_id_external' => (string) $p['id'],
        ]);

        $pedido->fill([
            'account_id' => (int) $conta->id,
            // Na Nuvemshop o "pago" é o payment_status; o status do pedido (open/closed/cancelled) fica no raw.
            'status_external' => isset($p['payment_status']) ? (string) $p['payment_status'] : null,
            'buyer_external' => ['id' => $p['customer']['id'] ?? null],
            'total_amount' => $p['total'] ?? null,
            'date_created_external' => isset($p['created_at']) ? Carbon::parse($p['created_at']) : null,
            'date_closed_external' => isset($p['paid_at']) ? Carbon::parse($p['paid_at']) : null,
            'raw_payload' => $p,
            'processed_at' => now(),
        ])->save();

        if ($pedido->status_external === 'paid' && $pedido->transaction_id === null) {
            VirarVendaJob::dispatch($this->businessId, (int) $pedido->id);
        }
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
