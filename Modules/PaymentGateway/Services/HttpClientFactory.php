<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fábrica centralizada de PendingRequest pros drivers PaymentGateway.
 *
 * Onda 4e — Auditoria 2026-05-23 catalogou que os 5 drivers (Asaas, BcbPix,
 * C6, Inter, Pagar.me) usam Http::baseUrl(...)->timeout(30) puro, sem `retry()`
 * nem handler de rate-limit 429. Resultado: 502 transitório de banco quebra
 * cobrança no relógio, sem nenhuma chance de recuperação automática.
 *
 * Esta fábrica resolve isso aplicando:
 *
 *  1. **Retry com 3 tentativas + sleep base 200ms** — implementado via
 *     `Http::retry()` Laravel 13 com callback custom que sabe distinguir
 *     ConnectionException + RequestException retryável (status 502/503/504/429).
 *
 *  2. **`throw: false`** no `retry()` — drivers continuam fazendo
 *     `$response->failed()` check próprio + joguem GatewayUnavailableException
 *     com mensagem específica. Preserva contrato dos drivers existentes.
 *
 *  3. **`throwIf(callable)`** com condição retryable status — força Laravel
 *     a jogar RequestException SÓ pra status que valem retry, fazendo o
 *     callback de retry ser invocado. Para 4xx não-retryável (400/401/422),
 *     callable retorna false → Response retorna sem throw → drivers checam
 *     `->failed()` normalmente.
 *
 *  4. **Sleep manual via `usleep`/`sleep`** no callback de retry quando
 *     status = 429 — respeita header `Retry-After` (RFC 9110 §10.2.3,
 *     formato segundos OU HTTP-date). Default 1s se ausente. Cap em 30s
 *     pra não travar worker queue (RecurringBilling job timeout 300s).
 *
 *  5. **Wrapper `send(Closure)`** — depois de esgotar retries em status
 *     retryável, Laravel ainda throw RequestException (engatilhado pelo
 *     throwIf na última tentativa). O método estático `send()` captura
 *     essa exception e devolve `$e->response`, preservando DX dos drivers
 *     que esperam sempre receber Response (nunca tratar exception
 *     RequestException direta).
 *
 * **Decisões catalogadas:**
 *
 *  - **Cap 30s no Retry-After**: alguns bancos podem mandar Retry-After: 3600
 *    (1h) em rate-limit duro. Não queremos travar worker queue por isso.
 *    Pior caso a cobrança vai pra retry job via JobsRunner (RecurringBilling).
 *
 *  - **3 tentativas não 5**: 3 × 30s pior caso = 90s. APIs bancárias 5xx
 *    transitórios raramente resolvem além da 3ª tentativa.
 *
 *  - **Sleep linear 200ms (não exponencial)**: simplificado. O sleep manual
 *    em 429 vence (delegamos pro Retry-After do banco).
 *
 *  - **Healthchecks NÃO usam retry** (`withRetry: false`): healthcheck
 *    significa "está vivo agora?". 1 fail = down. Retry mascararia downtime
 *    real do banco do dashboard de operação.
 *
 *  - **NÃO retry em 500 puro** — 500 pode ser bug de payload da request
 *    (idempotência quebrada, validação semântica). Só 502/503/504 são
 *    sintomas de infra transitória.
 *
 * Custo IA: ZERO (driver HTTP-only). Latência adicional pior caso por
 * request quando 5xx + 429 todos hitando: ~30s × 3 = 90s. Worker queue
 * RecurringBilling tem timeout 300s — folga confortável.
 *
 * @see ADR 0170 Onda 4 — PaymentGateway Tier L3
 * @see Audit 2026-05-23 — gap #1 (sem retry) + gap #2 (sem 429 handler)
 */
final class HttpClientFactory
{
    /**
     * Cap absoluto pra espera de Retry-After (segundos). Bancos podem mandar
     * valores muito altos em rate-limit duro; preferimos delegar pro retry
     * job de queue (RecurringBilling) do que travar worker.
     */
    public const RETRY_AFTER_CAP_SECONDS = 30;

    /**
     * Status HTTP que vale a pena fazer retry (transitórios de infra).
     * NÃO incluindo 500 — esse pode ser bug de payload da request.
     */
    public const RETRYABLE_5XX = [502, 503, 504];

    /**
     * Sleep entre tentativas em ms (base do Laravel Http::retry).
     */
    public const SLEEP_MS_BASE = 200;

    /**
     * Quantas tentativas no máximo (1 original + N-1 retries).
     */
    public const RETRY_TIMES = 3;

    /**
     * Cria PendingRequest pré-configurado com baseURL + headers + timeout
     * + (opcional) retry + handler 429.
     *
     * Quando `$withRetry=true`, o cliente:
     *   - faz até 3 tentativas
     *   - re-tenta em ConnectionException (rede caiu) e status 502/503/504/429
     *   - em 429, respeita header Retry-After (cap 30s)
     *   - em 4xx (400/401/403/422) e 500 puro, devolve Response imediato sem retry
     *   - APÓS esgotar retries em status retryável, devolve Response failed
     *     normalmente — drivers checam `$response->failed()` próprio
     *
     * @param  string  $baseUrl        URL base do gateway (ex 'https://api.asaas.com/v3')
     * @param  array<string,string>  $headers  Headers extra (ex ['access_token'=>$token])
     * @param  int  $timeoutSec        Timeout por request (default 30s)
     * @param  bool  $withRetry        true = aplica retry+429 handler; false = pure HTTP (use em healthchecks)
     * @return PendingRequest          Cliente pronto pra ->post()/->get()/etc
     */
    public static function make(
        string $baseUrl,
        array $headers = [],
        int $timeoutSec = 30,
        bool $withRetry = true,
    ): PendingRequest {
        $client = Http::baseUrl($baseUrl)
            ->withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout($timeoutSec);

        if (! $withRetry) {
            return $client;
        }

        // Mecânica:
        //  1. Http::retry com 3 tentativas. Callback `$when` decide retry:
        //     - ConnectionException → retry (rede caiu)
        //     - RequestException com status 5xx/429 → retry (com sleep 429)
        //     - Outras Throwables → false
        //  2. ->throwIf(callable) força Laravel a jogar RequestException
        //     APENAS em status retryáveis (5xx/429). Para 4xx, retorna
        //     false → Response retorna sem throw → drivers checam ->failed().
        //  3. APÓS esgotar retries em status retryável, Laravel ainda joga
        //     RequestException por causa do throwIf. O wrapper `send()`
        //     (vide método estático abaixo) captura essa RequestException e
        //     devolve $exception->response, preservando DX original dos drivers
        //     que esperam sempre receber Response.
        $isRetryableStatus = function (Response $response): bool {
            $status = $response->status();

            return $status === 429 || in_array($status, self::RETRYABLE_5XX, true);
        };

        return $client
            ->retry(
                self::RETRY_TIMES,
                self::SLEEP_MS_BASE,
                function (\Throwable $exception, PendingRequest $request): bool {
                    return self::shouldRetry($exception);
                },
                throw: false,
            )
            ->throwIf($isRetryableStatus);
    }

    /**
     * Decide se deve retry baseado na exception capturada pelo Http::retry.
     *
     * Recebe:
     *  - ConnectionException (timeout, DNS, conn refused) → retry sempre
     *  - RequestException de 5xx/429 (capturado pelo ->throwIf() acima) → retry
     *  - Outras Throwables → não retry
     */
    private static function shouldRetry(\Throwable $exception): bool
    {
        // Network/timeout errors → retry sempre
        if ($exception instanceof ConnectionException) {
            return true;
        }

        // RequestException de 5xx/429 capturado pelo ->throwIf() callback
        if ($exception instanceof RequestException) {
            $response = $exception->response;
            $status = $response->status();

            // 5xx transitório
            if (in_array($status, self::RETRYABLE_5XX, true)) {
                return true;
            }

            // 429 Too Many Requests — respeita Retry-After
            if ($status === 429) {
                self::sleepRespectingRetryAfter($response);

                return true;
            }
        }

        // Outras Throwables não-mapeadas → safe default false
        return false;
    }

    /**
     * Espera o tempo indicado pelo header `Retry-After` (RFC 9110 §10.2.3).
     *
     * Suporta:
     *  - segundos: `Retry-After: 5`
     *  - HTTP-date: `Retry-After: Wed, 21 Oct 2026 07:28:00 GMT` (raro em
     *    APIs bancárias mas ok suportar)
     *
     * Default 1s se ausente/inválido. Cap em RETRY_AFTER_CAP_SECONDS.
     *
     * NOTA: `sleep()` em queue worker é OK desde que worker tenha timeout
     * adequado. RecurringBilling jobs têm timeout 300s — sleep 30s é seguro.
     */
    private static function sleepRespectingRetryAfter(Response $response): void
    {
        $header = $response->header('Retry-After');
        $seconds = self::parseRetryAfter($header);

        // Cap pra não travar worker em rate-limit duro
        $seconds = min($seconds, self::RETRY_AFTER_CAP_SECONDS);
        $seconds = max($seconds, 1); // mínimo 1s

        sleep($seconds);
    }

    /**
     * Parse Retry-After: aceita int (segundos) ou HTTP-date (raro em APIs BR).
     *
     * @internal Público pra testabilidade direta.
     */
    public static function parseRetryAfter(?string $header): int
    {
        if ($header === null || $header === '') {
            return 1;
        }

        // Caso 1: int → segundos
        if (ctype_digit($header)) {
            return (int) $header;
        }

        // Caso 2: HTTP-date → calcula delta vs now
        $timestamp = strtotime($header);
        if ($timestamp !== false) {
            $delta = $timestamp - time();

            return $delta > 0 ? $delta : 1;
        }

        // Header malformado → default 1s
        return 1;
    }

    /**
     * Executa uma chamada HTTP via Closure e devolve a Response, capturando
     * RequestException jogada pelo throwIf retryable (após esgotar retries).
     *
     * Os drivers podem usar este wrapper pra preservar a semântica antiga
     * (sempre receber Response, nunca exception não-tratada):
     *
     * ```php
     * $response = HttpClientFactory::send(fn() =>
     *     $this->client($cred)->post('/payments', $payload)
     * );
     * if ($response->failed()) { throw GatewayUnavailableException; }
     * ```
     *
     * Sem este wrapper, drivers precisariam fazer try/catch RequestException
     * em CADA chamada — esta função centraliza isso.
     *
     * @param  \Closure(): Response  $callable Closure que faz a chamada HTTP
     * @return Response Última response (success ou failed). Em ConnectionException
     *                  pura sem response final, ESCAPA — drivers continuam tratando
     *                  via try/catch \Throwable existente.
     */
    public static function send(\Closure $callable): Response
    {
        try {
            return $callable();
        } catch (RequestException $e) {
            // Status retryável esgotou retries → response final está em $e->response.
            // Devolve normalmente; driver fará ->failed() check.
            return $e->response;
        }
    }
}
