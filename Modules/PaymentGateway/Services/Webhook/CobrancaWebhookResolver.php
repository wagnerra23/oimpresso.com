<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Webhook;

use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\PaymentGatewayService;

/**
 * Resolve a Cobranca afetada por um webhook genérico (gateway_webhook_events).
 *
 * ADR 0170 — destrava o linkage `cobranca_id` que faltava: o WebhookProcessor
 * gravava SEMPRE cobranca_id=NULL, deixando a branch de quitação do
 * RetryOrphanWebhookJob INALCANÇÁVEL. Este resolver é o ponto único de
 * "payload de webhook → Cobranca", usado em 2 lugares:
 *   1. WebhookProcessor (receive-time) — linka quando a Cobranca já existe;
 *   2. RetryOrphanWebhookJob (retry-time) — re-tenta o linkage da race
 *      condition (webhook chegou ANTES da emissão gravar a Cobranca).
 *
 * Extração do id externo POR DRIVER: reusa `driver->processWebhook($payload)`
 * — parser puro, sem I/O, já testado por driver (AsaasDriverTest etc) — em vez
 * de re-implementar o `match` por gateway. Isso evita o bug sutil de cada
 * gateway ter um id diferente: Asaas/Pagar.me usam payment.id/data.id (≠ id do
 * EVENTO), e **BCB Pix usa `idRec`** (mandato), NÃO o `txid` que o controller
 * extrai pra idempotência. O driver é o single-source-of-truth.
 *
 * Multi-tenant Tier 0 (ADR 0093): roda em contexto de webhook/cron SEM sessão
 * → withoutGlobalScopes() consciente + business_id explícito.
 *
 * Conservador por VALOR (REGRA MESTRE valor/estoque): escopa por
 * (business_id, payment_gateway_credential_id, gateway_external_id) — espelha
 * ProcessarWebhookPixInterJob:91. Prefere "não acha → fica órfão (Wagner
 * reconcilia)" a "linka a Cobranca errada → quita título errado".
 */
class CobrancaWebhookResolver
{
    public function __construct(private PaymentGatewayService $service)
    {
    }

    /**
     * Acha a Cobranca que o payload referencia, ou null se não resolver.
     *
     * Retorna null (sem lançar) em qualquer caso de não-resolução — gateway
     * desconhecida, payload sem id externo, ou Cobranca ainda não gravada
     * (race). O chamador trata null como "fica órfão".
     */
    public function resolve(
        int $businessId,
        string $gatewayKey,
        array $payload,
        PaymentGatewayCredential $credential,
    ): ?Cobranca {
        $externalId = $this->extractExternalId($gatewayKey, $payload, $credential);
        if ($externalId === '') {
            return null;
        }

        // SUPERADMIN: webhook/cron sem sessão; resolve a Cobranca scopando
        // explicitamente pelo business_id + credencial da própria linha do
        // webhook (ADR 0093). Mesmo trio do ProcessarWebhookPixInterJob.
        return Cobranca::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('payment_gateway_credential_id', $credential->id)
            ->where('gateway_external_id', $externalId)
            ->first();
    }

    /**
     * Extrai o id externo do gateway delegando pro parser do próprio driver
     * (`processWebhook`). Parser puro — sem I/O, sem efeito colateral.
     * Retorna '' quando a gateway é desconhecida ou o payload não traz id.
     */
    private function extractExternalId(string $gatewayKey, array $payload, PaymentGatewayCredential $credential): string
    {
        $driver = $this->service->driverForKey($gatewayKey);
        if ($driver === null) {
            return '';
        }

        $parsed = $driver->processWebhook($payload, $credential);

        // processWebhook retorna ?object (stdClass) com gateway_external_id, ou
        // null pra evento ignorável. Cast pra array é larastan-safe (acesso a
        // chave dinâmica em object é flagged em strict mode).
        $parsedArr = is_object($parsed) ? (array) $parsed : [];

        return trim((string) ($parsedArr['gateway_external_id'] ?? ''));
    }
}
