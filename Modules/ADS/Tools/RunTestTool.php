<?php

namespace Modules\ADS\Tools;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Modules\ADS\Contracts\Tool;

/**
 * Tool de EXECUÇÃO de testes Pest sandboxed.
 *
 * Defesas:
 *   - Timeout 60s hard (Process::setTimeout)
 *   - Path whitelist: só roda Pest dentro de Modules/<X>/Tests/ ou tests/
 *   - Output max 64KB (truncado)
 *   - SEM --filter aceito do agente (evita injection com flags arbitrárias)
 *   - php artisan test em vez de pest direto (usa config Laravel)
 */
class RunTestTool implements Tool
{
    public function name(): string { return 'run_test'; }
    public function category(): string { return 'execução'; }
    public function isReadOnly(): bool { return false; } // muda DB de teste

    private const ALLOWED_PREFIXES = [
        'Modules/',     // Modules/X/Tests/...
        'tests/',
    ];

    private const TIMEOUT_SECONDS = 60;
    private const MAX_OUTPUT_BYTES = 65536;

    public function description(): string
    {
        return 'Executa testes Pest dentro de path whitelist (Modules/X/Tests/ ou tests/). '
             . 'Timeout 60s hard. Retorna pass/fail + tail do output. Wagner aprova antes.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Path relativo do teste/diretório (ex: Modules/ADS/Tests/Unit ou tests/Feature/Foo.php)',
                ],
            ],
            'required' => ['path'],
        ];
    }

    public function execute(array $input): array
    {
        $path = $input['path'] ?? '';

        // Validações de segurança
        if (empty($path)) {
            return ['ok' => false, 'output' => null, 'error' => 'path_required'];
        }
        if (str_contains($path, '..')) {
            return ['ok' => false, 'output' => null, 'error' => 'path_traversal_blocked'];
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Z]:[\\\\\\/]/i', $path)) {
            return ['ok' => false, 'output' => null, 'error' => 'absolute_path_blocked'];
        }

        $path = str_replace('\\', '/', $path);

        $prefixOk = false;
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) { $prefixOk = true; break; }
        }
        if (! $prefixOk) {
            return ['ok' => false, 'output' => null, 'error' => 'path_not_in_test_whitelist'];
        }

        // Confirma que existe
        $absolutePath = base_path($path);
        if (! file_exists($absolutePath) && ! is_dir($absolutePath)) {
            return ['ok' => false, 'output' => null, 'error' => 'path_not_found'];
        }

        $startedAt = microtime(true);

        try {
            $process = new Process(
                ['php', 'artisan', 'test', $path, '--no-coverage'],
                base_path(),
            );
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            $output = $process->getOutput() . "\n" . $process->getErrorOutput();

            // Trunca output
            if (strlen($output) > self::MAX_OUTPUT_BYTES) {
                $output = '[…truncated]' . "\n" . substr($output, -self::MAX_OUTPUT_BYTES);
            }

            $passed = $process->getExitCode() === 0;
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            // Extrai contagem de testes do output Pest
            $stats = $this->extractStats($output);

            return [
                'ok'     => true,
                'output' => [
                    'passed'      => $passed,
                    'exit_code'   => $process->getExitCode(),
                    'duration_ms' => $durationMs,
                    'stats'       => $stats,
                    'output_tail' => $output,
                ],
                'error'  => null,
            ];
        } catch (ProcessTimedOutException $e) {
            return [
                'ok' => false, 'output' => null,
                'error' => 'timeout_after_' . self::TIMEOUT_SECONDS . 's',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => null, 'error' => 'process_failed: ' . $e->getMessage()];
        }
    }

    /**
     * Extrai contadores tipo "Tests: 78 passed (177 assertions)" do output Pest.
     */
    private function extractStats(string $output): array
    {
        $stats = ['passed' => null, 'failed' => null, 'assertions' => null];

        if (preg_match('/(\d+)\s+passed/i', $output, $m))     $stats['passed'] = (int) $m[1];
        if (preg_match('/(\d+)\s+failed/i', $output, $m))     $stats['failed'] = (int) $m[1];
        if (preg_match('/\((\d+)\s+assertions?\)/i', $output, $m)) $stats['assertions'] = (int) $m[1];

        return $stats;
    }
}
