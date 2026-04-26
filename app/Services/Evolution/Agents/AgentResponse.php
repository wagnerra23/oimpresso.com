<?php

declare(strict_types=1);

namespace App\Services\Evolution\Agents;

class AgentResponse
{
    public function __construct(
        public readonly string $text,
        public readonly array $traces = [],
        public readonly int $tokensIn = 0,
        public readonly int $tokensOut = 0,
        public readonly int $latencyMs = 0,
    ) {}
}
