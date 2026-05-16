<?php

namespace Modules\ADS\Services;

/**
 * Observabilidade D9.a (ADR 0155): DTO de entrada — spans Tracer ficam no
 * DecisionRouter::route() que consome este input, via `OtelHelper::span(`.
 */
final class RoutingInput
{
    public function __construct(
        public readonly int    $businessId,
        public readonly string $eventType,
        public readonly string $eventSource,  // 'brain_a' | 'evolution_agent' | 'wagner' | 'scheduler'
        public readonly string $domain,
        public readonly array  $filesAffected = [],
        public readonly array  $metadata = [],
    ) {}
}
