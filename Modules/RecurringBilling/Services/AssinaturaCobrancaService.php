<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\RecurringBilling\Events\AssinaturaAtualizada;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

/**
 * Service thin de orquestração de cobrança de assinatura (US-RB-042 extraído).
 *
 * Extrai lógica de cancelamento de invoice antes inline no InvoiceController.
 * SoC brutal (Constituição v2 princípio §5) — controller fica thin HTTP-only;
 * service orquestra gateway + persistência + audit.
 *
 * Multi-tenant Tier 0 (ADR 0093): toda operação recebe businessId explícito.
 * Tests biz=1 (ADR 0101): nunca biz=4 (cliente ROTA LIVRE).
 */
class AssinaturaCobrancaService
{
    public function __construct(
        private readonly BoletoService $boletos,
    ) {}

    /**
     * Cancela invoice no gateway + marca status=canceled idempotente.
     *
     * Retorno:
     *   ['ok' => true,  'gateway_call' => bool, 'skipped' => 'already_canceled'?]
     *   ['ok' => false, 'error' => string, 'http_status' => int]
     *
     * Idempotência: se já canceled, retorna ok sem repetir.
     * Guard: invoice paga retorna 422 (use estorno em vez de cancelamento).
     * Fallback: sem gateway_ref, cancela só local.
     */
    public function cancelInvoice(
        int $businessId,
        int $invoiceId,
        string $motivo = 'ACERTOS',
    ): array {
        return OtelHelper::spanBiz('rb.invoice.cancel', function () use ($businessId, $invoiceId, $motivo) {
            return $this->cancelInvoiceInternal($businessId, $invoiceId, $motivo);
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'invoice.cancel',
            'business_id' => $businessId,
            'invoice_id'  => $invoiceId,
        ]);
    }

    private function cancelInvoiceInternal(
        int $businessId,
        int $invoiceId,
        string $motivo,
    ): array {
        $invoice = Invoice::where('business_id', $businessId)
            ->whereKey($invoiceId)
            ->firstOrFail();

        if ($invoice->status === 'canceled') {
            return [
                'ok' => true,
                'gateway_call' => false,
                'skipped' => 'already_canceled',
                'invoice' => $invoice,
            ];
        }

        if ($invoice->status === 'paid') {
            return [
                'ok' => false,
                'error' => 'Invoice já paga. Use estorno em vez de cancelamento.',
                'http_status' => 422,
                'invoice' => $invoice,
            ];
        }

        if (! $invoice->gateway || ! $invoice->gateway_ref) {
            // Nunca foi tentada cobrança no gateway — só marca local
            $invoice->update(['status' => 'canceled']);

            return [
                'ok' => true,
                'gateway_call' => false,
                'gateway_used' => null,
                'invoice' => $invoice->refresh(),
            ];
        }

        try {
            DB::transaction(function () use ($invoice, $motivo) {
                $this->boletos->cancelar(
                    $invoice->business_id,
                    $invoice->gateway_ref,
                    $motivo,
                );
                $invoice->update(['status' => 'canceled']);
            });
        } catch (\BadMethodCallException $e) {
            // C6Driver — cancelamento manual obrigatório no portal banco
            return [
                'ok' => false,
                'gateway_call' => false,
                'error' => $e->getMessage(),
                'requires_manual_action' => true,
                'http_status' => 501,
                'invoice' => $invoice,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'gateway' => $invoice->gateway,
                'error' => $e->getMessage(),
                'http_status' => 502,
                'invoice' => $invoice,
            ];
        }

        return [
            'ok' => true,
            'gateway_call' => true,
            'gateway_used' => $invoice->gateway,
            'invoice' => $invoice->refresh(),
        ];
    }

    /**
     * FIN-004 — Atualiza cobranca de uma assinatura recorrente (valor / ciclo /
     * forma_pagamento) refletindo no gateway externo + persistencia local.
     *
     * Caller (Controller) JA validou auth/permission. Service:
     *  1. Carrega Subscription scoped por businessId (multi-tenant Tier 0)
     *  2. Idempotencia: se nada mudou efetivamente, retorna ok skipped
     *  3. Tenta refletir no gateway (Asaas / Inter) — best-effort, falha nao-fatal
     *     volta no payload com `gateway_call=false` pro caller decidir UI
     *  4. Atualiza tabela local rb_subscriptions + recalcula next_due_date se ciclo mudou
     *  5. Dispara evento AssinaturaAtualizada (audit + listeners)
     *
     * Retorno:
     *   ['ok' => true,  'gateway_call' => bool, 'subscription' => Subscription, 'skipped' => string?]
     *   ['ok' => false, 'error' => string, 'http_status' => int]
     *
     * Payload aceito:
     *   ['valor' => float?, 'ciclo' => string?, 'forma_pagamento' => string?]
     *   Pelo menos UM campo deve estar presente — caso contrario, retorna 422.
     *
     * Multi-tenant Tier 0 (ADR 0093): subscription escopada por businessId.
     * Tests biz=1 (ADR 0101): NUNCA biz=4 (ROTA LIVRE PROD).
     * Logger: ZERO valor real, ZERO CPF — apenas IDs e flags.
     */
    public function atualizarCobrancaAssinatura(
        int $businessId,
        int $assinaturaId,
        array $payload,
    ): array {
        return OtelHelper::spanBiz('rb.subscription.update', function () use ($businessId, $assinaturaId, $payload) {
            return $this->atualizarCobrancaAssinaturaInternal($businessId, $assinaturaId, $payload);
        }, [
            'module'        => 'RecurringBilling',
            'op'            => 'subscription.update',
            'business_id'   => $businessId,
            'assinatura_id' => $assinaturaId,
            'mudou_valor'   => isset($payload['valor']),
            'mudou_ciclo'   => isset($payload['ciclo']),
            'mudou_forma'   => isset($payload['forma_pagamento']),
        ]);
    }

    private function atualizarCobrancaAssinaturaInternal(
        int $businessId,
        int $assinaturaId,
        array $payload,
    ): array {
        $subscription = Subscription::where('business_id', $businessId)
            ->whereKey($assinaturaId)
            ->first();

        if (! $subscription) {
            return [
                'ok' => false,
                'error' => 'Assinatura nao encontrada no business.',
                'http_status' => 404,
            ];
        }

        if (! in_array($subscription->status, ['active', 'trialing', 'past_due', 'paused'], true)) {
            return [
                'ok' => false,
                'error' => 'Assinatura cancelada nao pode ser atualizada. Crie uma nova.',
                'http_status' => 422,
                'subscription' => $subscription,
            ];
        }

        $valor = isset($payload['valor']) ? (float) $payload['valor'] : null;
        $ciclo = $payload['ciclo'] ?? null;
        $formaPagamento = $payload['forma_pagamento'] ?? null;

        if ($valor === null && $ciclo === null && $formaPagamento === null) {
            return [
                'ok' => false,
                'error' => 'Pelo menos um campo (valor / ciclo / forma_pagamento) deve ser enviado.',
                'http_status' => 422,
                'subscription' => $subscription,
            ];
        }

        $metadata = $subscription->metadata ?? [];
        $valorAtual = (float) ($metadata['valor'] ?? $subscription->plan?->valor ?? 0);
        $cicloAtual = $metadata['ciclo'] ?? $subscription->plan?->ciclo ?? null;
        $formaAtual = $metadata['forma_pagamento'] ?? 'boleto';

        $mudouValor = $valor !== null && abs($valor - $valorAtual) > 0.001;
        $mudouCiclo = $ciclo !== null && $ciclo !== $cicloAtual;
        $mudouForma = $formaPagamento !== null && $formaPagamento !== $formaAtual;

        if (! $mudouValor && ! $mudouCiclo && ! $mudouForma) {
            return [
                'ok' => true,
                'gateway_call' => false,
                'skipped' => 'no_changes',
                'subscription' => $subscription,
            ];
        }

        $gatewayCall = false;
        $gatewayError = null;
        $gateway = $metadata['gateway'] ?? null;

        if ($gateway && ! empty($metadata['gateway_subscription_ref'])) {
            try {
                $this->refletirNoGateway(
                    $gateway,
                    $businessId,
                    (string) $metadata['gateway_subscription_ref'],
                    array_filter([
                        'valor' => $mudouValor ? $valor : null,
                        'ciclo' => $mudouCiclo ? $ciclo : null,
                    ], fn ($v) => $v !== null),
                );
                $gatewayCall = true;
            } catch (\Throwable $e) {
                $gatewayError = $e->getMessage();
            }
        }

        try {
            DB::transaction(function () use (
                $subscription,
                $mudouValor,
                $mudouCiclo,
                $mudouForma,
                $valor,
                $ciclo,
                $formaPagamento,
                $metadata,
            ) {
                $novoMetadata = $metadata;
                if ($mudouValor) {
                    $novoMetadata['valor'] = $valor;
                }
                if ($mudouCiclo) {
                    $novoMetadata['ciclo'] = $ciclo;
                }
                if ($mudouForma) {
                    $novoMetadata['forma_pagamento'] = $formaPagamento;
                }

                $update = ['metadata' => $novoMetadata];

                if ($mudouCiclo) {
                    $update['next_due_date'] = $this->recalcularProximaCobranca(
                        $subscription->next_due_date?->toDateString() ?? now()->toDateString(),
                        (string) $ciclo,
                    );
                }

                $subscription->update($update);
            });
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Falha persistindo atualizacao local: ' . $e->getMessage(),
                'http_status' => 500,
                'subscription' => $subscription,
            ];
        }

        Event::dispatch(new AssinaturaAtualizada(
            businessId: $businessId,
            subscriptionId: $subscription->id,
            mudouValor: $mudouValor,
            mudouCiclo: $mudouCiclo,
            mudouForma: $mudouForma,
            gatewayCall: $gatewayCall,
        ));

        // D9.b log estruturado update assinatura — útil pra US-RB-044
        // (NFe-de-boleto pago emite quando subscription muda valor/ciclo).
        Log::info('rb.subscription.atualizada', [
            'business_id'    => $businessId,
            'subscription_id' => $subscription->id,
            'mudou_valor'    => $mudouValor,
            'mudou_ciclo'    => $mudouCiclo,
            'mudou_forma'    => $mudouForma,
            'gateway_call'   => $gatewayCall,
            'gateway_warning' => $gatewayError,
        ]);

        return array_filter([
            'ok' => true,
            'gateway_call' => $gatewayCall,
            'gateway_warning' => $gatewayError,
            'subscription' => $subscription->refresh(),
        ], fn ($v) => $v !== null);
    }

    /**
     * Reflete update no gateway externo (Asaas / Inter). Best-effort —
     * lanca em erro pro caller decidir tratamento.
     *
     * Asaas: PUT /v3/subscriptions/{id}  (body: value, cycle)
     * Inter: Inter PJ nao tem API de subscription — Wagner cancela manual + nova
     *        Aqui logamos warning e seguimos com update local apenas.
     */
    private function refletirNoGateway(
        string $gateway,
        int $businessId,
        string $gatewayRef,
        array $changes,
    ): void {
        if ($gateway === 'asaas') {
            $cred = BoletoCredential::where('business_id', $businessId)
                ->where('banco', 'asaas')
                ->where('ativo', true)
                ->first();

            if (! $cred) {
                throw new \DomainException('Sem credencial Asaas configurada para o business.');
            }

            $config = $cred->config_json ?? [];
            $apiKey = $config['api_key'] ?? null;
            $ambiente = $cred->ambiente ?? 'production';

            if (! $apiKey) {
                throw new \DomainException('Credencial Asaas sem api_key.');
            }

            $baseUrl = $ambiente === 'sandbox'
                ? 'https://sandbox.asaas.com/api/v3'
                : 'https://api.asaas.com/v3';

            $body = array_filter([
                'value' => $changes['valor'] ?? null,
                'cycle' => isset($changes['ciclo']) ? $this->mapCicloAsaas($changes['ciclo']) : null,
            ], fn ($v) => $v !== null);

            if (empty($body)) {
                return;
            }

            Http::withHeaders([
                'access_token' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->put("{$baseUrl}/subscriptions/{$gatewayRef}", $body)
                ->throw();

            return;
        }

        if ($gateway === 'inter') {
            // Inter PJ nao expoe API de subscription update — cancelamento + novo contrato manual
            throw new \DomainException(
                'Inter PJ nao expoe API de subscription update. '
                . 'Cancele a assinatura atual e cadastre uma nova com os novos valores.'
            );
        }

        throw new \DomainException("Gateway nao suportado: {$gateway}");
    }

    private function mapCicloAsaas(string $ciclo): string
    {
        return match ($ciclo) {
            'mensal' => 'MONTHLY',
            'trimestral' => 'QUARTERLY',
            'semestral' => 'SEMIANNUALLY',
            'anual' => 'YEARLY',
            default => 'MONTHLY',
        };
    }

    private function recalcularProximaCobranca(string $proximaAtual, string $novoCiclo): string
    {
        $base = \Illuminate\Support\Carbon::parse($proximaAtual);

        return match ($novoCiclo) {
            'mensal' => $base->copy()->addMonth()->toDateString(),
            'trimestral' => $base->copy()->addMonths(3)->toDateString(),
            'semestral' => $base->copy()->addMonths(6)->toDateString(),
            'anual' => $base->copy()->addYear()->toDateString(),
            default => $base->toDateString(),
        };
    }
}
