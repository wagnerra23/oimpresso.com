<?php

namespace Modules\ADS\Services;

/**
 * Observabilidade D9.a (ADR 0155): value-object — spans Tracer ficam no
 * PolicyEngine que produz este resultado, via `OtelHelper::span(`.
 */
final class PolicyResult
{
    public const ACTION_BLOCK          = 'block';
    public const ACTION_REQUIRE_HUMAN  = 'require_human';
    public const ACTION_REQUIRE_BRAIN_B = 'require_brain_b';
    public const ACTION_ALLOW_BRAIN_A  = 'allow_brain_a';

    private function __construct(
        public readonly string $action,
        public readonly string $eventType,
        public readonly string $rule,
    ) {}

    public static function block(string $eventType, string $rule): self
    {
        return new self(self::ACTION_BLOCK, $eventType, $rule);
    }

    public static function requireHuman(string $eventType, string $rule): self
    {
        return new self(self::ACTION_REQUIRE_HUMAN, $eventType, $rule);
    }

    public static function requireBrainB(string $eventType, string $rule): self
    {
        return new self(self::ACTION_REQUIRE_BRAIN_B, $eventType, $rule);
    }

    public static function allowBrainA(string $eventType, string $rule): self
    {
        return new self(self::ACTION_ALLOW_BRAIN_A, $eventType, $rule);
    }

    public function isBlocked(): bool
    {
        return $this->action === self::ACTION_BLOCK;
    }

    public function requiresHuman(): bool
    {
        return in_array($this->action, [self::ACTION_BLOCK, self::ACTION_REQUIRE_HUMAN], true);
    }

    public function allowsBrainA(): bool
    {
        return $this->action === self::ACTION_ALLOW_BRAIN_A;
    }
}
