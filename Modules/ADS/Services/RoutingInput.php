<?php

namespace Modules\ADS\Services;

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
