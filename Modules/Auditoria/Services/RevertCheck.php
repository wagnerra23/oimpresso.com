<?php

namespace Modules\Auditoria\Services;

/**
 * Resultado da verificacao "essa Activity pode ser revertida?".
 *
 * Per ADR 0127 §princípio 4 (whitelist UNREVERTIBLE).
 */
class RevertCheck
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $modelClass = null,
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    public static function deny(string $reason, ?string $modelClass = null): self
    {
        return new self(allowed: false, reason: $reason, modelClass: $modelClass);
    }
}
