<?php

namespace Modules\ADS\Services;

/**
 * Observabilidade D9.a (ADR 0155): value-object — spans Tracer ficam no
 * RiskEngine que produz este resultado, via `OtelHelper::span(`.
 */
final class RiskResult
{
    public function __construct(
        public readonly float  $score,
        public readonly string $eventType,
        public readonly float  $impact,
        public readonly float  $uncertainty,
        public readonly float  $reversibility,
        public readonly float  $criticality,
        public readonly bool   $usedPrior,
    ) {}

    public function isGreen(): bool  { return $this->score < 0.20; }
    public function isYellow(): bool { return $this->score >= 0.20 && $this->score < 0.40; }
    public function isOrange(): bool { return $this->score >= 0.40 && $this->score < 0.70; }
    public function isRed(): bool    { return $this->score >= 0.70; }

    public function zone(): string
    {
        return match (true) {
            $this->isGreen()  => 'green',
            $this->isYellow() => 'yellow',
            $this->isOrange() => 'orange',
            default           => 'red',
        };
    }
}
