<?php

namespace Modules\ADS\Tools;

use Modules\ADS\Contracts\Tool;

/**
 * Tool: lê últimas N linhas do laravel.log com filtro por nível.
 * Read-only. Usada por Brain A/B pra investigar erros.
 */
class LogReaderTool implements Tool
{
    public function name(): string { return 'log_reader'; }
    public function category(): string { return 'leitura'; }
    public function isReadOnly(): bool { return true; }

    public function description(): string
    {
        return 'Lê últimas N linhas do laravel.log com filtro por nível (ERROR/WARNING/INFO). '
             . 'Use para investigar erros recentes, debugar exceções, encontrar padrões em logs.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lines'      => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 50],
                'min_level'  => ['type' => 'string', 'enum' => ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'], 'default' => 'WARNING'],
                'pattern'    => ['type' => 'string', 'description' => 'Regex opcional pra filtrar linhas'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $input): array
    {
        $lines = min(500, max(1, (int) ($input['lines'] ?? 50)));
        $minLevel = strtoupper($input['min_level'] ?? 'WARNING');
        $pattern = $input['pattern'] ?? null;

        $logPath = storage_path('logs/laravel.log');
        if (! is_file($logPath)) {
            return ['ok' => false, 'output' => null, 'error' => 'log_file_not_found'];
        }

        $size = filesize($logPath);
        // Lê apenas últimos ~256KB pra performance
        $maxRead = min($size, 262144);
        $fp = fopen($logPath, 'rb');
        fseek($fp, max(0, $size - $maxRead));
        $chunk = fread($fp, $maxRead);
        fclose($fp);

        $allLines = preg_split('/\r?\n/', $chunk);
        $levelOrder = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
        $minOrder = $levelOrder[$minLevel] ?? 2;

        $filtered = [];
        foreach (array_reverse($allLines) as $line) {
            if (empty($line)) continue;
            if (preg_match('/\.(DEBUG|INFO|WARNING|ERROR|CRITICAL):/i', $line, $m)) {
                $lvlOrder = $levelOrder[strtoupper($m[1])] ?? 0;
                if ($lvlOrder < $minOrder) continue;
                if ($pattern && ! preg_match("/{$pattern}/i", $line)) continue;
                $filtered[] = mb_strimwidth($line, 0, 800, '…');
                if (count($filtered) >= $lines) break;
            }
        }

        return [
            'ok'     => true,
            'output' => [
                'lines_returned' => count($filtered),
                'min_level'      => $minLevel,
                'entries'        => array_reverse($filtered),
            ],
            'error'  => null,
        ];
    }
}
