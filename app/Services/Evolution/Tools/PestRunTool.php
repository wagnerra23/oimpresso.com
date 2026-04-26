<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use Symfony\Component\Process\Process;

class PestRunTool implements Tool
{
    public function name(): string
    {
        return 'PestRun';
    }

    public function description(): string
    {
        return 'Executa Pest com filtro opcional e retorna resumo (passed/failed/output).';
    }

    public function __invoke(array $args = [])
    {
        $filter = isset($args['filter']) ? (string) $args['filter'] : null;

        $cmd = [base_path('vendor/bin/pest'), '--no-coverage'];

        if ($filter !== null && $filter !== '') {
            $cmd[] = '--filter';
            $cmd[] = $filter;
        }

        $proc = new Process($cmd, base_path());
        $proc->setTimeout(180);
        $proc->run();

        $output = $proc->getOutput().$proc->getErrorOutput();

        return [
            'exit_code' => $proc->getExitCode(),
            'passed' => str_contains($output, 'Tests:') && ! str_contains($output, 'Failed'),
            'output_tail' => mb_substr($output, max(0, mb_strlen($output) - 2000)),
        ];
    }
}
