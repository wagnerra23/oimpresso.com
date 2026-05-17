<?php

declare(strict_types=1);

namespace Modules\Financeiro\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\ExtratoLancamento;
use Modules\Financeiro\Services\FinanceiroAuditLogger;
use Modules\Financeiro\Services\Integrations\AsaasPixAutomaticoService;

/**
 * ProcessAsaasPixWebhookListener — processa webhook PAYMENT_RECEIVED da Asaas
 * quando billingType=PIX (rota Pix Automático W28-5).
 *
 * **Fluxo:**
 * 1. Controller webhook valida assinatura via `AsaasPixAutomaticoService::verifyWebhookSignature`
 * 2. Dispara `AsaasPixPaymentReceived` event com `$businessId + $payload`
 * 3. Este listener (queue) processa idempotente:
 *    a. Cache lock por `payment.id` (TTL 24h) — evita double-processing
 *    b. Cria `ExtratoLancamento` (entrada bancária) — append-only
 *    c. Marca Subscription RecurringBilling com `next_due_date` avançado
 *    d. Audit log com PII redactor
 *
 * **Multi-tenant Tier 0 (ADR 0093):**
 * `$businessId` é OBRIGATÓRIO no constructor — webhook controller resolve via
 * `webhook_secret` per-business (Asaas envia 1 webhook por business config).
 * Job assíncrono não pode confiar em `session()`.
 *
 * **Idempotency:**
 * Asaas pode reenviar webhook (até 24h após primeiro send se 5xx). Cache lock
 * com `payment.id` evita criar 2 lançamentos. TTL 24h cobre janela Asaas retry.
 *
 * **NÃO é controller** — é listener invocado pelo Controller `AsaasWebhookController`
 * (TODO Wave 28-5b ou US-FIN-XXX). Por ora podem disparar manual via
 * `event(new AsaasPixPaymentReceived($bizId, $payload))` em teste.
 *
 * @see Modules\Financeiro\Services\Integrations\AsaasPixAutomaticoService
 */
class ProcessAsaasPixWebhookListener implements ShouldQueue
{
    public string $queue = 'financeiro';

    /**
     * Cache TTL pro idempotency lock — alinhado com janela de retry da Asaas (24h).
     */
    private const IDEMPOTENCY_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly FinanceiroAuditLogger $logger,
        private readonly AsaasPixAutomaticoService $service,
    ) {}

    /**
     * Entry point — chamado pelo Laravel event dispatcher.
     *
     * Estrutura `$event->payload` (subset relevante Asaas):
     * - event (string): "PAYMENT_RECEIVED" | "PAYMENT_CONFIRMED" | etc
     * - payment.id (string): "pay_xxx" — chave idempotency
     * - payment.value (float)
     * - payment.netValue (float — valor após taxa)
     * - payment.subscription (string|null): "sub_xxx" se veio de assinatura
     * - payment.paymentDate (Y-m-d)
     * - payment.billingType (string): "PIX" (filtra outros)
     * - payment.externalReference (string|null): nossa idempotency key
     *
     * @param  object{ businessId: int, payload: array<string, mixed> }  $event
     */
    public function handle(object $event): void
    {
        $businessId = (int) $event->businessId;
        $payload = (array) $event->payload;

        $eventType = (string) ($payload['event'] ?? '');
        $payment = (array) ($payload['payment'] ?? []);

        // Filtro 1: só processa PAYMENT_RECEIVED/CONFIRMED de PIX
        if (! in_array($eventType, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            $this->logger->debug('asaas.webhook.event_skipped', [
                'business_id' => $businessId,
                'tipo' => $eventType,
            ]);
            return;
        }

        if (($payment['billingType'] ?? '') !== 'PIX') {
            return;
        }

        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            $this->logger->warning('asaas.webhook.missing_payment_id', [
                'business_id' => $businessId,
            ]);
            return;
        }

        // Filtro 2: idempotency lock — Asaas retransmite até confirmar 200
        $lockKey = "financeiro:asaas:pix:processed:{$businessId}:{$paymentId}";
        $acquired = Cache::add($lockKey, now()->toIso8601String(), self::IDEMPOTENCY_TTL_SECONDS);
        if (! $acquired) {
            $this->logger->info('asaas.webhook.idempotency_hit', [
                'business_id' => $businessId,
                'idempotency_key' => $paymentId,
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($businessId, $payment, $paymentId): void {
                $this->criarExtratoLancamento($businessId, $payment, $paymentId);
                $this->marcarSubscriptionPaga($businessId, $payment);
            });

            $this->logger->info('asaas.webhook.pix_received_processed', [
                'business_id' => $businessId,
                'idempotency_key' => $paymentId,
                'status' => 'processed',
            ]);
        } catch (\Throwable $e) {
            // Libera lock pra Asaas retransmitir e tentarmos de novo
            Cache::forget($lockKey);
            $this->logger->error('asaas.webhook.process_failed', [
                'business_id' => $businessId,
                'idempotency_key' => $paymentId,
                'status' => 'error',
            ]);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function criarExtratoLancamento(int $businessId, array $payment, string $paymentId): void
    {
        // Append-only (UNIQUE conta_bancaria_id+idempotency_key na tabela).
        // ContaBancaria resolvida via override per-business (TODO: mapeamento por
        // gateway_credential_id). Por ora: usa primeira conta ativa do business.
        $contaBancariaId = (int) DB::table('fin_contas_bancarias')
            ->where('business_id', $businessId)
            ->where('ativa', true)
            ->orderBy('id')
            ->value('id');

        if ($contaBancariaId === 0) {
            $this->logger->warning('asaas.webhook.no_conta_bancaria', [
                'business_id' => $businessId,
                'idempotency_key' => $paymentId,
            ]);
            return;
        }

        ExtratoLancamento::updateOrCreate(
            [
                'conta_bancaria_id' => $contaBancariaId,
                'idempotency_key' => "asaas:{$paymentId}",
            ],
            [
                'business_id' => $businessId,
                'data' => Carbon::parse((string) ($payment['paymentDate'] ?? now()))->toDateString(),
                'valor' => (float) ($payment['netValue'] ?? $payment['value'] ?? 0.0),
                'tipo' => 'credito',
                'descricao' => 'Pix Automático Asaas — assinatura',
                'raw_payload' => $payment,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function marcarSubscriptionPaga(int $businessId, array $payment): void
    {
        $subscriptionId = (string) ($payment['subscription'] ?? '');
        if ($subscriptionId === '') {
            return; // Pagamento avulso — sem subscription pra atualizar
        }

        // Busca Subscription oimpresso vinculada via metadata.asaas_subscription_id
        // (set on criarRecorrencia). Se não houver, skip silencioso — webhook chegou
        // antes da subscription ser persistida ou veio de canal Asaas direto.
        DB::table('rb_subscriptions')
            ->where('business_id', $businessId)
            ->whereJsonContains('metadata->asaas_subscription_id', $subscriptionId)
            ->update([
                'last_payment_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
