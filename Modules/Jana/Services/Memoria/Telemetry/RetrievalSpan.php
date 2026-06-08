<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria\Telemetry;

use Illuminate\Support\Str;

/**
 * RetrievalSpan — POPO leve representando um span do pipeline retrieval Jana
 * (D8 gap #3 — OTel GenAI retrieval spans — 2026-05-15).
 *
 * Não depende de `open-telemetry/sdk` (não instalado — config/otel.php §1-9
 * deixa explícito que estamos em "lightweight bridge" enquanto Hostinger não
 * suporta extensão PECL). Quando o SDK full subir no CT 100 (env
 * `OTEL_FULL_SDK=true`), este POPO continua válido porque os atributos seguem
 * a nomenclatura canônica OTel GenAI semantic conventions 2026
 * (https://opentelemetry.io/docs/specs/semconv/gen-ai/).
 *
 * Por hora os spans são consumidos por:
 *   - Langfuse self-host CT 100 (via LangfuseClient::recordSpan)
 *   - mcp_audit_log (linha por query — ADR 0053)
 *   - Log channel `copiloto-ai` (debug local)
 *
 * Multi-tenant Tier 0 ([ADR 0093]): business_id SEMPRE preenchido. Custom attr.
 * PII Tier 0: query NUNCA em raw — sempre `sha256(query)` quando
 *   config('copiloto.telemetry.redact_query') = true (default true).
 */
final class RetrievalSpan
{
    public readonly string $spanId;
    public readonly float $startTime;
    public ?float $endTime = null;
    public string $status = 'unset';
    public ?string $statusMessage = null;

    /** @var array<string,mixed> Atributos canônicos OTel GenAI + customs business_id */
    public array $attributes = [];

    /** @var array<int,array{name:string, time:float, attributes:array<string,mixed>}> */
    public array $events = [];

    /** @param array<string,mixed> $attributes */
    public function __construct(
        public readonly string $name,
        public readonly ?string $parentSpanId,
        array $attributes = [],
    ) {
        $this->spanId = (string) Str::uuid();
        $this->startTime = microtime(true);
        $this->attributes = $attributes;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /** @param array<string,mixed> $attrs */
    public function addEvent(string $name, array $attrs = []): self
    {
        $this->events[] = [
            'name' => $name,
            'time' => microtime(true),
            'attributes' => $attrs,
        ];

        return $this;
    }

    public function setStatus(string $status, ?string $message = null): self
    {
        $this->status = $status;
        $this->statusMessage = $message;

        return $this;
    }

    public function end(): self
    {
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
        }

        return $this;
    }

    public function durationMs(): float
    {
        $end = $this->endTime ?? microtime(true);

        return ($end - $this->startTime) * 1000.0;
    }
}
