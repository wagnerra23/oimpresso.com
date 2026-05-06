<?php

namespace Modules\Brief\Services;

/**
 * Resultado da validação do brief gerado pelo Brain B.
 * Imutável — fail() ou ok() na construção, sem setters.
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
        return new self(true, '', $tokenCount);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason, 0);
    }

    public function isOk(): bool
    {
        return $this->isOk;
    }
}
