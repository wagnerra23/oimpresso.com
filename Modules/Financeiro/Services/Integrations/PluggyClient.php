<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services\Integrations;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente HTTP do Pluggy.ai — Open Banking BR (estado-da-arte W27).
 *
 * Documentação canônica:
 *   - https://docs.pluggy.ai/docs/authentication
 *   - https://docs.pluggy.ai/reference/auth-create
 *   - https://docs.pluggy.ai/docs/connect-an-account
 *
 * Fluxo de auth:
 *   POST {base}/auth  body={clientId, clientSecret}  → { apiKey } (TTL 2h)
 *
 * Endpoints principais usados pelo oimpresso:
 *   GET  /accounts?itemId={id}                      — contas do item
 *   GET  /transactions?accountId={id}&from=&to=     — transações conta
 *   POST /connect_token                              — limited token frontend (30min)
 *
 * Tier 0 IRREVOGÁVEL:
 *   - Credentials NUNCA logados — sempre `[REDACTED]`
 *   - `force_mock` env desabilita HTTP real (Pest local)
 *   - Cache de apiKey (Cache::remember TTL 100min) reduz chamadas auth ~98%
 *
 * @see Modules\Financeiro\Services\Integrations\PluggyBankSyncService
 */
class PluggyClient
{
    private string $baseUrl;

    private int $timeout;

    private int $retryTimes;

    private int $retrySleepMs;

    private int $apiKeyCacheTtl;

    private bool $forceMock;

    public function __construct(
        private ?string $clientId = null,
        private ?string $clientSecret = null,
        ?string $baseUrl = null,
    ) {
        $this->clientId        = $clientId ?: (string) config('financeiro.pluggy.client_id');
        $this->clientSecret    = $clientSecret ?: (string) config('financeiro.pluggy.client_secret');
        $this->baseUrl         = rtrim($baseUrl ?: (string) config('financeiro.pluggy.base_url', 'https://api.pluggy.ai'), '/');
        $this->timeout         = (int) config('financeiro.pluggy.timeout', 15);
        $this->retryTimes      = (int) config('financeiro.pluggy.retry_times', 2);
        $this->retrySleepMs    = (int) config('financeiro.pluggy.retry_sleep_ms', 250);
        $this->apiKeyCacheTtl  = (int) config('financeiro.pluggy.api_key_cache_ttl_sec', 6000);
        $this->forceMock       = (bool) config('financeiro.pluggy.force_mock', false);
    }

    /**
     * Lista contas de um item (conexão user-instituição).
     *
     * @return array<int, array<string, mixed>> results da API Pluggy
     */
    public function listAccounts(string $itemId): array
    {
        if ($this->forceMock) {
            return $this->mockAccounts($itemId);
        }

        $response = $this->http()
            ->get("{$this->baseUrl}/accounts", ['itemId' => $itemId]);

        if (! $response->successful()) {
            $this->logError('listAccounts', $itemId, $response->status(), $response->body());
            throw new RuntimeException("Pluggy listAccounts falhou: HTTP {$response->status()}");
        }

        return (array) ($response->json('results') ?? []);
    }

    /**
     * Lista transações de uma conta no intervalo [$from, $to].
     *
     * Pluggy paginates — coletamos `results` da página atual; sync mais
     * sofisticado (paginate via `?page=`) fica em PluggyBankSyncService
     * futuro quando connector ativo retornar > 200 tx/sync.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTransactions(string $accountId, Carbon $from, Carbon $to): array
    {
        if ($this->forceMock) {
            return $this->mockTransactions($accountId, $from, $to);
        }

        $response = $this->http()->get("{$this->baseUrl}/transactions", [
            'accountId' => $accountId,
            'from'      => $from->toDateString(),
            'to'        => $to->toDateString(),
            'pageSize'  => 200,
        ]);

        if (! $response->successful()) {
            $this->logError('listTransactions', $accountId, $response->status(), $response->body());
            throw new RuntimeException("Pluggy listTransactions falhou: HTTP {$response->status()}");
        }

        return (array) ($response->json('results') ?? []);
    }

    /**
     * Cria um Connect Token (limited 30min) pro frontend abrir o widget Pluggy.
     *
     * Pluggy aceita `clientUserId` pra rastrear qual user do oimpresso. Usamos
     * `business_id` como handle externo (não vaza PII; só ID interno).
     *
     * @return string accessToken pra Pluggy Connect widget
     */
    public function createConnectToken(int $businessId, ?string $itemId = null): string
    {
        if ($this->forceMock) {
            return 'pluggy-connect-mock-token-biz' . $businessId;
        }

        $payload = [
            'clientUserId' => 'biz-' . $businessId,
        ];

        // Se reconnect/MFA, passa item existente; senão é cadastro novo.
        if ($itemId) {
            $payload['itemId'] = $itemId;
        }

        $response = $this->http()
            ->post("{$this->baseUrl}/connect_token", $payload);

        if (! $response->successful()) {
            $this->logError('createConnectToken', "biz={$businessId}", $response->status(), $response->body());
            throw new RuntimeException("Pluggy createConnectToken falhou: HTTP {$response->status()}");
        }

        $token = (string) ($response->json('accessToken') ?? '');
        if ($token === '') {
            throw new RuntimeException('Pluggy createConnectToken: accessToken vazio');
        }

        return $token;
    }

    /**
     * Valida HMAC SHA-256 do webhook Pluggy contra `webhook_secret`.
     *
     * Pluggy assina o body com header `x-pluggy-signature: sha256=<hex>`.
     * Usamos hash_equals (timing-safe) pra evitar timing attacks.
     */
    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = (string) config('financeiro.pluggy.webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * HTTP client autenticado com API key (cache 100min).
     */
    private function http(): PendingRequest
    {
        return Http::withToken($this->apiKey(), 'API-KEY')
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleepMs, function ($exception, $request) {
                // Retry só em 5xx ou network; 4xx é erro do cliente
                if (method_exists($exception, 'response') && $exception->response) {
                    return $exception->response->status() >= 500;
                }

                return true;
            }, throw: false);
    }

    /**
     * Obtém API key via Cache::remember (Pluggy expira em 2h; cache 100min).
     */
    private function apiKey(): string
    {
        $cacheKey = 'pluggy:api_key:' . md5((string) $this->clientId);

        return Cache::remember($cacheKey, $this->apiKeyCacheTtl, function () {
            if ($this->clientId === '' || $this->clientSecret === '') {
                throw new RuntimeException(
                    'Pluggy credenciais ausentes — defina PLUGGY_CLIENT_ID + PLUGGY_CLIENT_SECRET'
                );
            }

            $response = Http::acceptJson()
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/auth", [
                    'clientId'     => $this->clientId,
                    'clientSecret' => $this->clientSecret,
                ]);

            if (! $response->successful()) {
                Log::error('[Pluggy] /auth falhou', [
                    'status'      => $response->status(),
                    'client_id'   => '[REDACTED]',
                    'body_snippet'=> mb_substr((string) $response->body(), 0, 200),
                ]);
                throw new RuntimeException("Pluggy /auth falhou: HTTP {$response->status()}");
            }

            $apiKey = (string) ($response->json('apiKey') ?? '');
            if ($apiKey === '') {
                throw new RuntimeException('Pluggy /auth: apiKey vazia');
            }

            return $apiKey;
        });
    }

    /**
     * Log de erro sem vazar credenciais. PII de body é truncado.
     */
    private function logError(string $op, string $ref, int $status, string $body): void
    {
        Log::warning("[Pluggy] {$op} falhou", [
            'reference'    => $ref,
            'http_status'  => $status,
            'body_snippet' => mb_substr($body, 0, 200),
        ]);
    }

    /**
     * Mock pra Pest local — payload mínimo conforme docs Pluggy.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mockAccounts(string $itemId): array
    {
        return [
            [
                'id'             => 'acc-mock-' . md5($itemId),
                'type'           => 'BANK',
                'subtype'        => 'CHECKING_ACCOUNT',
                'number'         => '12345-6',
                'name'           => 'Conta Corrente Mock',
                'balance'        => 1500.50,
                'currencyCode'   => 'BRL',
                'itemId'         => $itemId,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mockTransactions(string $accountId, Carbon $from, Carbon $to): array
    {
        return [
            [
                'id'              => 'tx-mock-' . md5($accountId . '-1'),
                'accountId'       => $accountId,
                'date'            => $from->copy()->addDay()->toIso8601String(),
                'description'     => 'PIX recebido (mock)',
                'amount'          => 250.00,
                'currencyCode'    => 'BRL',
                'type'            => 'CREDIT',
                'category'        => 'Transfer',
                'balance'         => 1500.50,
            ],
            [
                'id'              => 'tx-mock-' . md5($accountId . '-2'),
                'accountId'       => $accountId,
                'date'            => $from->copy()->addDays(2)->toIso8601String(),
                'description'     => 'Boleto pago (mock)',
                'amount'          => -120.00,
                'currencyCode'    => 'BRL',
                'type'            => 'DEBIT',
                'category'        => 'Bill Payment',
                'balance'         => 1380.50,
            ],
        ];
    }
}
