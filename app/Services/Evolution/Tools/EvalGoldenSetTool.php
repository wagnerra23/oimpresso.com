<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use App\Services\Evolution\Eval\GoldenSetRunner;

class EvalGoldenSetTool implements Tool
{
    public function __construct(private readonly ?GoldenSetRunner $runner = null) {}

    public function name(): string
    {
        return 'EvalGoldenSet';
    }

    public function description(): string
    {
        return 'Roda golden set + LLM-as-judge e devolve score 0-100 por caso e média.';
    }

    public function __invoke(array $args = [])
    {
        $runner = $this->runner ?? app(GoldenSetRunner::class);

        return $runner->run();
    }
}
