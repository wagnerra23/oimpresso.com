<?php

namespace Modules\ADS\Tools;

use Symfony\Component\Process\Process;
use Modules\ADS\Contracts\Tool;

/**
 * Tool: inspeção git (log, show, diff). Read-only.
 * Não permite write (commit/push/reset/checkout). Esses ficam em GitWriteTool futuro
 * que SEMPRE exige Wagner aprovar.
 */
class GitInspectTool implements Tool
{
    public function name(): string { return 'git_inspect'; }
    public function category(): string { return 'leitura'; }
    public function isReadOnly(): bool { return true; }

    public function description(): string
    {
        return 'Inspeção git read-only: log, show <sha>, diff <sha>, blame <file>. '
             . 'Use para entender histórico, ver mudanças num commit específico.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => ['type' => 'string', 'enum' => ['log', 'show', 'diff', 'blame'], 'default' => 'log'],
                'sha'     => ['type' => 'string', 'description' => 'SHA do commit (show/diff)'],
                'file'    => ['type' => 'string', 'description' => 'Path do arquivo (blame)'],
                'limit'   => ['type' => 'integer', 'default' => 10],
            ],
            'required' => ['command'],
        ];
    }

    public function execute(array $input): array
    {
        $cmd = $input['command'] ?? 'log';
        $sha = $input['sha'] ?? null;
        $file = $input['file'] ?? null;
        $limit = (int) ($input['limit'] ?? 10);

        // Whitelist rígida — sem opções perigosas
        $args = match ($cmd) {
            'log'   => ['log', "--max-count={$limit}", '--pretty=format:%H|%s|%an|%cI'],
            'show'  => $sha ? ['show', '--stat', $sha] : null,
            'diff'  => $sha ? ['diff', "{$sha}^..{$sha}", '--stat'] : null,
            'blame' => $file ? ['blame', '--', $file] : null,
            default => null,
        };

        if (! $args) {
            return ['ok' => false, 'output' => null, 'error' => 'invalid_command_or_missing_args'];
        }

        try {
            $process = new Process(array_merge(['git'], $args), base_path());
            $process->setTimeout(15);
            $process->mustRun();

            $output = trim($process->getOutput());
            // Limita a 16KB pra evitar payload gigante
            if (strlen($output) > 16384) {
                $output = substr($output, 0, 16384) . "\n…(truncated)";
            }

            return [
                'ok'     => true,
                'output' => ['command' => $cmd, 'result' => $output],
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => null, 'error' => $e->getMessage()];
        }
    }
}
