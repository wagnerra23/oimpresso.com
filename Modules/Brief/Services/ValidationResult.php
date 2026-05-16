<?php

namespace Modules\Brief\Services;

use App\Util\OtelHelper;

/**
 * Resultado da validação do brief gerado pelo Brain B.
 * Imutável — fail() ou ok() na construção, sem setters.
 *
 * D9.a OTel (Wave 17): factory methods envolvidos em span pra rastrear
 * taxa de fail/ok do brief gerado pelo Brain B (signal pra Wagner monitorar
 * regressão de qualidade do LLM). Zero-cost quando otel.enabled=false.
 */
final class ValidationResult
{
    private function __construct(
        public readonly bool $isOk,
        public readonly string $reason,
        public readonly int $tokenCount,
    ) {}

    public static function ok(int $tokenCount): self
    {
        return OtelHelper::spanBiz('brief.validation.ok', function () use ($tokenCount) {
            return new self(true, '', $tokenCount);
        }, ['module' => 'Brief', 'token_count' => $tokenCount]);
    }

    public static function fail(string $reason): self
    {
        return OtelHelper::spanBiz('brief.validation.fail', function () use ($reason) {
            return new self(false, $reason, 0);
        }, [
            'module'      => 'Brief',
            // reason é code interno (missing_end_sentinel, pii_leaked etc) — NUNCA contém PII.
            'reason_code' => $reason,
        ]);
    }

    public function isOk(): bool
    {
        return $this->isOk;
    }
}
