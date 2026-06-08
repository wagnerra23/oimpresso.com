<?php

namespace Modules\ADS\Services;

/**
 * Observabilidade D9.a (ADR 0155): value-object — spans Tracer ficam no
 * DecisionRouter que produz este resultado, via `OtelHelper::span(`.
 */
final class RoutingDecision
{
    public function __construct(
        public readonly int    $decisionId,
        public readonly string $destination,    // 'brain_a'|'brain_b'|'pending_wagner'|'blocked'|'queued'
        public readonly float  $riskScore,
        public readonly float  $confidenceScore,
        public readonly string $policyApplied,
        public readonly int    $hitlLevel,
    ) {}

    public function isBlocked(): bool    { return $this->destination === 'blocked'; }
    public function isQueued(): bool     { return $this->destination === 'queued'; }
    public function goesToBrainA(): bool { return $this->destination === 'brain_a'; }
    public function goesToBrainB(): bool { return $this->destination === 'brain_b'; }
    public function isAutonomous(): bool { return $this->destination === 'brain_a' && $this->hitlLevel === 0; }
    public function needsWagner(): bool  { return in_array($this->destination, ['pending_wagner', 'blocked'], true); }
}
