<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Financeiro\Services\FinanceiroAuditLogger;
use RuntimeException;

/**
 * AsaasPixAutomaticoService — wrapper HTTP do recurso Pix Automático da Asaas.
 *
 * **Wave 28-5 — Estado-da-arte W27 functional G2.**
 *
 * Pix Automático Bacen entrou em produção 2024-09; Asaas expôs a API em 2025
 * (subscriptions com `billingType=PIX` + `pixAutomaticAuthorizationId`). Tendência
 * 2026 BR: concorrentes do Financeiro (Bling, Tiny, Omie) adotaram via Asaas/MP.
 *
 * Esta implementação cobre **apenas o caminho Asaas** — não vincula API Bacen
 * direto (PSP intermediário absorve homologação SPI). Cliente assina QR Code
 * inicial (Jornada 3 Asaas), nosso backend cria subscriptions periódicas
 * referenciando o `pixAutomaticAuthorizationId` retornado.
 *
 * ## Multi-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
 *
 * `$businessId` exigido no 1º arg de todo método público. API key + webhook secret
 * vivem em `business_payment_credentials` (TODO Wave 29 — por ora usa env como
 * fallback dev/sandbox). Logs vão pelo `FinanceiroAuditLogger` (redaciona PII BR
 * antes de gravar — CPF/CNPJ/email/telefone do payer aparecem em payload Asaas).
 *
 * ## Idempotency + circuit breaker
 *
 * - Cada `criarRecorrencia` aceita `$externalReference` (idempotency key oimpresso).
 *   Asaas trata duplicatas server-side via mesmo `externalReference`.
 * - Endpoint Asaas com timeout 10s + retry exponencial 3x (Http::retry).
 * - Default disabled via `financeiro.asaas.pix_automatico_enabled=false` —
 *   Wagner habilita por business após validação sandbox.
 *
 * ## Webhook signature
 *
 * Asaas envia header `asaas-access-token` (assinatura simétrica) — método
 * `verifyWebhookSignature` compara em tempo constante via `hash_equals`.
 *
 * @see https://docs.asaas.com/docs/pix-automatico-implementacao
 * @see https://docs.asaas.com/docs/criando-uma-assinatura
 */
class AsaasPixAutomaticoService
{
    private const TIMEOUT_SECONDS = 10;

    private const RETRIES = 3;

    private const ENVIRONMENTS = [
        'sandbox' => 'https://api-sandbox.asaas.com',
        'production' => 'https://api.asaas.com',
    ];

    public function __construct(
        private readonly FinanceiroAuditLogger $logger,
        private ?string $apiKey = null,
        private string $environment = 'sandbox',
    ) {
        $this->apiKey ??= (string) config('financeiro.asaas.api_key', '');
        $this->environment = config('financeiro.asaas.environment', $environment);
    }

    /**
     * Cria uma assinatura recorrente (subscription) com billingType=PIX.
     *
     * Payload mínimo esperado:
     * - customer (string Asaas customer id — pré-criado via /v3/customers)
     * - value (float — valor de cada cobrança)
     * - cycle (string — WEEKLY, BIWEEKLY, MONTHLY, QUARTERLY, SEMIANNUALLY, YEARLY)
     * - nextDueDate (Y-m-d — primeira cobrança)
     * - description (string opcional)
     * - endDate (Y-m-d opcional — null = open-ended)
     * - externalReference (idempotency key — recomendado UUID v4 oimpresso)
     * - pixAutomaticAuthorizationId (string opcional — se já tem autorização ativa)
     *
     * @param  int  $businessId  scope Tier 0 — exigido pra logging + futuras credenciais per-business
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> resposta Asaas decodificada (id, status, dateCreated, etc)
     *
     * @throws RuntimeException quando feature flag desabilitada ou Asaas retorna 4xx/5xx
     */
    public function criarRecorrencia(int $businessId, array $payload): array
    {
        $this->guardEnabled($businessId);
        $this->guardRequiredFields($payload, ['customer', 'value', 'cycle', 'nextDueDate']);

        $payload['billingType'] = 'PIX';

        $response = $this->request('POST', '/v3/subscriptions', $payload);

        $this->logger->info('asaas.pix_automatico.subscription_created', [
            'business_id' => $businessId,
            'idempotency_key' => $payload['externalReference'] ?? null,
            'subscription_id' => $response['id'] ?? null,
            'status' => $response['status'] ?? null,
        ]);

        return $response;
    }

    /**
     * Cancela assinatura recorrente. Asaas marca como CANCELLED; cobranças
     * futuras não geradas. Cobranças já emitidas seguem o curso (não retroage).
     */
    public function cancelarRecorrencia(int $businessId, string $subscriptionId): bool
    {
        $this->guardEnabled($businessId);

        $response = $this->request('DELETE', "/v3/subscriptions/{$subscriptionId}");

        $deleted = (bool) ($response['deleted'] ?? false);

        $this->logger->info('asaas.pix_automatico.subscription_cancelled', [
            'business_id' => $businessId,
            'subscription_id' => $subscriptionId,
            'status' => $deleted ? 'cancelled' : 'failed',
        ]);

        return $deleted;
    }

    /**
     * Lista pagamentos gerados por uma assinatura. Útil pra reconciliação
     * manual e troubleshooting (paginação Asaas: limit/offset).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarPagamentos(int $businessId, string $subscriptionId): array
    {
        $this->guardEnabled($businessId);

        $response = $this->request('GET', "/v3/subscriptions/{$subscriptionId}/payments");

        return $response['data'] ?? [];
    }

    /**
     * Configura URL de webhook para receber eventos Pix Automático
     * (PAYMENT_RECEIVED, PAYMENT_CONFIRMED, PAYMENT_OVERDUE, etc).
     *
     * Idempotente — chamadas repetidas atualizam a URL.
     */
    public function configurarWebhookPix(int $businessId, string $callbackUrl): bool
    {
        $this->guardEnabled($businessId);

        if (! filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('callbackUrl inválido — exige scheme + host.');
        }

        $response = $this->request('POST', '/v3/webhooks', [
            'name' => "oimpresso-pix-automatico-biz-{$businessId}",
            'url' => $callbackUrl,
            'enabled' => true,
            'interrupted' => false,
            'apiVersion' => 3,
            'sendType' => 'NON_SEQUENTIALLY',
            'events' => [
                'PAYMENT_CREATED',
                'PAYMENT_RECEIVED',
                'PAYMENT_CONFIRMED',
                'PAYMENT_OVERDUE',
                'PAYMENT_REFUNDED',
            ],
        ]);

        $configured = isset($response['id']);

        $this->logger->info('asaas.pix_automatico.webhook_configured', [
            'business_id' => $businessId,
            'status' => $configured ? 'configured' : 'failed',
        ]);

        return $configured;
    }

    /**
     * Verifica assinatura HMAC-SHA256 do webhook.
     *
     * Asaas envia o secret cru no header `asaas-access-token` (simétrico). Pra
     * defesa em profundidade, recomendamos rotacionar via dashboard +
     * armazenar HMAC adicional no payload `signature` (próxima iteração).
     * Por ora valida o token simétrico em **tempo constante** via `hash_equals`.
     *
     * Se `signature` (assinatura HMAC opcional) estiver presente, valida HMAC
     * SHA-256 sobre raw body com `webhook_secret` configurado.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = (string) config('financeiro.asaas.webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        // Cenário 1: signature é o access-token simétrico cru
        if (hash_equals($secret, $signature)) {
            return true;
        }

        // Cenário 2: signature é HMAC SHA-256(payload, secret) hex
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function guardEnabled(int $businessId): void
    {
        if (! config('financeiro.asaas.pix_automatico_enabled', false)) {
            $this->logger->warning('asaas.pix_automatico.disabled', [
                'business_id' => $businessId,
            ]);
            throw new RuntimeException('Pix Automático Asaas desabilitado (financeiro.asaas.pix_automatico_enabled=false).');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('Asaas API key ausente — defina ASAAS_API_KEY.');
        }
    }

    /**
     * @param  array<int, string>  $required
     * @param  array<string, mixed>  $payload
     */
    private function guardRequiredFields(array $payload, array $required): void
    {
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                throw new RuntimeException("Campo obrigatório '{$field}' ausente no payload.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $base = self::ENVIRONMENTS[$this->environment] ?? self::ENVIRONMENTS['sandbox'];
        $url = $base . $path;

        /** @var Response $response */
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'oimpresso-financeiro/1.0',
        ])
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(self::RETRIES, 200, throw: false)
            ->send($method, $url, $body === [] ? [] : ['json' => $body]);

        if ($response->failed()) {
            $this->logger->error('asaas.pix_automatico.http_failed', [
                'business_id' => 0,
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
            ]);
            throw new RuntimeException("Asaas HTTP {$response->status()} em {$method} {$path}");
        }

        return (array) $response->json();
    }
}
