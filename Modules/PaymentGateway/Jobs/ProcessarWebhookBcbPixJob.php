<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\RecorrenciaPixAtualizada;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Services\ReconciliarCobrancaService;

/**
 * Processa um webhook do Pix Automático BCB já gravado em `gateway_webhook_events` (US-RB-021 ·
 * decisão [W] 2026-10-26). Formatos da API Pix v2.10.0:
 *   - `cobsr[]` (cobrança do ciclo): `CONCLUIDA` → cobrança paga pelo `ReconciliarCobrancaService`
 *     (o mesmo do Inter) → `CobrancaPaga` → a fatura da assinatura vira paga (US-RB-057).
 *     `REJEITADA` → `erro`, `EXPIRADA` → `vencida`, `CANCELADA` → `cancelada` (cobrança já paga não
 *     volta atrás).
 *   - `recs[]` (recorrência): dispara `RecorrenciaPixAtualizada` para o dono do mandato.
 *
 * VALOR (regra mestre): a notificação da cobrança não traz valor; o valor pago é o da própria
 * cobrança (`valor_centavos`), que a spec não deixa o pagador alterar no Pix Automático.
 *
 * Só processa com `RB_COBRAR_PIX_AUTOMATICO=true`; desligado, o evento fica gravado e sem
 * `processed_at` (dá para reprocessar depois). Idempotente: evento com `processed_at` é ignorado.
 * Tier 0 (ADR 0093): `businessId` no construtor e em toda consulta.
 */
class ProcessarWebhookBcbPixJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Ver ProcessarWebhookPixInterJob: o trait Queueable já declara `$queue` — fila via onQueue().
    public int $tries = 3;

    public int $backoff = 60;

    private const STATUS_COBRANCA = ['REJEITADA' => 'erro', 'EXPIRADA' => 'vencida', 'CANCELADA' => 'cancelada'];

    private const STATUS_REC = ['CRIADA', 'APROVADA', 'REJEITADA', 'EXPIRADA', 'CANCELADA'];

    public function __construct(
        public readonly int $webhookEventId,
        public readonly int $businessId,
    ) {
        $this->onQueue('paymentgateway');
    }

    public function handle(ReconciliarCobrancaService $reconciliador): void
    {
        if (! config('recurringbilling.cobrar_pix_automatico', false)) {
            return;
        }

        // SUPERADMIN: queue worker sem session(); o evento é lido pelo business_id propagado.
        $evento = GatewayWebhookEvent::withoutGlobalScopes()
            ->where('id', $this->webhookEventId)
            ->where('business_id', $this->businessId)
            ->where('gateway_key', 'bcb_pix')
            ->first();
        if ($evento === null || $evento->processed_at !== null) {
            return;
        }

        $payload = (array) $evento->payload;
        $credencialId = (int) $evento->payment_gateway_credential_id;

        DB::transaction(function () use ($payload, $credencialId, $reconciliador, $evento): void {
            foreach ((array) ($payload['cobsr'] ?? []) as $item) {
                $this->processarCobranca((array) $item, $credencialId, $reconciliador);
            }

            foreach ((array) ($payload['recs'] ?? []) as $item) {
                $idRec = (string) ($item['idRec'] ?? '');
                $status = strtoupper((string) ($item['status'] ?? ''));
                if ($idRec !== '' && in_array($status, self::STATUS_REC, true)) {
                    event(new RecorrenciaPixAtualizada($this->businessId, $idRec, $status));
                }
            }

            $evento->update(['processed_at' => now(), 'error_message' => null]);
        });
    }

    /** @param array<string, mixed> $item */
    private function processarCobranca(array $item, int $credencialId, ReconciliarCobrancaService $reconciliador): void
    {
        $txid = (string) ($item['txid'] ?? '');
        $status = strtoupper((string) ($item['status'] ?? ''));
        if ($txid === '') {
            return;
        }

        // SUPERADMIN: idem — business + credencial do próprio evento + txid (mesmo trio do Inter).
        $cobranca = Cobranca::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('payment_gateway_credential_id', $credencialId)
            ->where('gateway_external_id', $txid)
            ->where('tipo', 'pix_recv')
            ->lockForUpdate()
            ->first();
        if ($cobranca === null) {
            Log::warning('paymentgateway.bcb_pix.worker.cobranca_nao_encontrada', [
                'business_id' => $this->businessId, 'webhook_event_id' => $this->webhookEventId,
            ]);

            return;
        }

        if ($status === 'CONCLUIDA') {
            $reconciliador->marcarPaga(
                cobranca: $cobranca,
                businessId: $this->businessId,
                valorPagoCentavos: (int) $cobranca->getAttribute('valor_centavos'),
                pagaEm: $this->quando($item, 'CONCLUIDA'),
                formaPagamento: 'pix',
            );

            return;
        }

        $novo = self::STATUS_COBRANCA[$status] ?? null;
        if ($novo !== null && $cobranca->getAttribute('status') !== 'paga') {
            $cobranca->update(['status' => $novo]);
        }
    }

    /** Data da atualização com o status pedido (`atualizacao[]` da spec); sem ela, agora. */
    private function quando(array $item, string $status): \DateTimeImmutable
    {
        foreach ((array) ($item['atualizacao'] ?? []) as $a) {
            if (strtoupper((string) ($a['status'] ?? '')) === $status && ! empty($a['data'])) {
                try {
                    return new \DateTimeImmutable((string) $a['data']);
                } catch (\Throwable) {
                    break;
                }
            }
        }

        return new \DateTimeImmutable();
    }
}
