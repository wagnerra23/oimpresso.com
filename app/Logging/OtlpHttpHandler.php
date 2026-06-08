<?php

declare(strict_types=1);

namespace App\Logging;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * US-INFRA-016 PR-1 (ADR 0132) — Monolog handler que exporta spans OTel GenAI
 * pro Langfuse self-host CT 100 via OTLP/JSON HTTP.
 *
 * Captura LogRecord do channel `otel-gen-ai` (mensagem `gen_ai.span`),
 * converte attributes em payload OTLP/JSON, POSTa em
 * `${LANGFUSE_HOST}/api/public/otel/v1/traces` com Basic auth `pk:sk`.
 *
 * Falha silenciosa — telemetria nunca quebra chat (princípio 8 ADR 0094).
 * Stack do logging.php combina este handler + daily local pra fallback.
 */
class OtlpHttpHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $publicKey,
        private readonly string $secretKey,
        private readonly string $serviceName = 'jana',
        private readonly float $timeoutSeconds = 5.0,
        private readonly ?ClientInterface $client = null,
        int|string|Level $level = Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if ($record->message !== 'gen_ai.span') {
            return;
        }

        if ($this->publicKey === '' || $this->secretKey === '') {
            return;
        }

        $payload = $this->toOtlpPayload($record);
        $client = $this->client ?? new Client(['timeout' => $this->timeoutSeconds]);

        try {
            $client->request('POST', $this->endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("{$this->publicKey}:{$this->secretKey}"),
                ],
                'json' => $payload,
                'timeout' => $this->timeoutSeconds,
            ]);
        } catch (GuzzleException $e) {
            error_log('[OtlpHttpHandler] export failed: ' . $e->getMessage());
        }
    }

    /**
     * Converte LogRecord (com $context = attributes gen_ai.*) em payload OTLP/JSON.
     * Schema: https://opentelemetry.io/docs/specs/otlp/#otlphttp-request
     */
    private function toOtlpPayload(LogRecord $record): array
    {
        $attrs = $record->context;
        $startNs = (int) ($record->datetime->format('U.u') * 1_000_000_000);
        $durationMs = (int) ($attrs['gen_ai.response.duration_ms'] ?? 0);
        $endNs = $startNs + ($durationMs * 1_000_000);

        return [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                    ],
                ],
                'scopeSpans' => [[
                    'scope' => ['name' => 'oimpresso/jana'],
                    'spans' => [[
                        'traceId' => bin2hex(random_bytes(16)),
                        'spanId' => bin2hex(random_bytes(8)),
                        'name' => (string) ($attrs['gen_ai.operation.name'] ?? 'chat'),
                        'kind' => 3,
                        'startTimeUnixNano' => (string) $startNs,
                        'endTimeUnixNano' => (string) $endNs,
                        'attributes' => $this->mapAttributes($attrs),
                    ]],
                ]],
            ]],
        ];
    }

    private function mapAttributes(array $attrs): array
    {
        $result = [];
        foreach ($attrs as $key => $value) {
            if ($value === null) {
                continue;
            }
            // OTLP spec permite só 1 value type por attribute. Pra int/float, mandamos
            // o spec-correct (intValue/doubleValue) + duplicamos como stringValue numa
            // chave irmã `<key>.str` pra Langfuse v3 renderizar (UI só lê stringValue).
            // Quando Langfuse fixar suporte intValue, remove .str fallback.
            $result[] = ['key' => (string) $key, 'value' => $this->toOtlpValue($value)];

            if (is_int($value) || is_float($value) || is_bool($value)) {
                $result[] = ['key' => (string) $key . '.str', 'value' => ['stringValue' => $this->toScalarString($value)]];
            }
        }

        return $result;
    }

    private function toOtlpValue(mixed $v): array
    {
        if (is_int($v)) {
            return ['intValue' => (string) $v];
        }

        if (is_float($v)) {
            return ['doubleValue' => $v];
        }

        if (is_bool($v)) {
            return ['boolValue' => $v];
        }

        return ['stringValue' => (string) $v];
    }

    private function toScalarString(int|float|bool $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }

        return (string) $v;
    }
}
