<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use Symfony\Component\Process\Process;

class GitDiffStatTool implements Tool
{
    public function name(): string
    {
        return 'GitDiffStat';
    }

    public function description(): string
    {
        return 'Retorna `git diff --stat <base>..HEAD` resumido — quantos arquivos/linhas mudaram.';
    }

    public function __invoke(array $args = [])
    {
        $base = (string) ($args['base'] ?? 'origin/6.7-bootstrap');

        $proc = new Process(['git', 'diff', '--stat', $base.'..HEAD'], base_path());
        $proc->setTimeout(30);
        $proc->run();

        return [
            'base' => $base,
            'exit_code' => $proc->getExitCode(),
            'stat' => trim($proc->getOutput()),
            'error' => trim($proc->getErrorOutput()),
        ];
    }
}
