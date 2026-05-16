<?php

namespace Modules\Auditoria\Services;

use App\Util\OtelHelper;

/**
 * Resultado da verificacao "essa Activity pode ser revertida?".
 *
 * Per ADR 0127 §princípio 4 (whitelist UNREVERTIBLE).
 *
 * D9.a OTel: factory methods envolvidos em span pra rastrear taxa de
 * allow/deny (signal de UX — Wagner monitora se whitelist UNREVERTIBLE
 * está bloqueando muito). Zero-cost quando otel.enabled=false.
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
        return OtelHelper::spanBiz('auditoria.revert.check.allow', function () {
            return new self(allowed: true);
        }, ['module' => 'Auditoria', 'decision' => 'allow']);
    }

    public static function deny(string $reason, ?string $modelClass = null): self
    {
        return OtelHelper::spanBiz('auditoria.revert.check.deny', function () use ($reason, $modelClass) {
            return new self(allowed: false, reason: $reason, modelClass: $modelClass);
        }, [
            'module'      => 'Auditoria',
            'decision'    => 'deny',
            'model_class' => $modelClass ?? 'unknown',
            'has_reason'  => $reason !== '',
        ]);
    }
}
