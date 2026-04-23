<?php

namespace Modules\Officeimpresso\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Officeimpresso\Entities\LicencaLog;

/**
 * Parseia storage/logs/laravel.log atras de erros relacionados a auth
 * do Passport / API officeimpresso e grava em licenca_log com
 * source=log_parser.
 *
 * Idempotente — dedup pelo hash da linha. Nunca modifica o log_file.
 *
 * Agendar em app/Console/Kernel.php:
 *   $schedule->command('licenca-log:parse')->everyFiveMinutes();
 */
class ParseLicencaLogCommand extends Command
{
    protected $signature = 'licenca-log:parse {--limit=5000 : max linhas por run}';

    protected $description = 'Parse Laravel log for Officeimpresso auth errors';

    /** @var string[] padroes de erro relevantes */
    private array $patterns = [
        'OAuthServerException'                => 'login_error',
        'invalid_credentials'                 => 'login_error',
        'Invalid credentials'                 => 'login_error',
        'Client authentication failed'        => 'login_error',
        'unsupported_grant_type'              => 'login_error',
        'invalid_client'                      => 'login_error',
        '/api/officeimpresso'                 => 'api_call',
    ];

    public function handle(): int
    {
        $logFile = storage_path('logs/laravel.log');
        if (! is_file($logFile)) {
            $this->warn("Log file nao encontrado: {$logFile}");
            return self::SUCCESS;
        }

        // Cursor — ultima posicao processada (grava no options table ou cache)
        $offsetKey = 'officeimpresso.log_parser.offset';
        $lastOffset = (int) (\Cache::get($offsetKey) ?? 0);
        $fileSize = filesize($logFile);

        // Log rotativo — se shrink, recomeca do zero
        if ($fileSize < $lastOffset) {
            $lastOffset = 0;
        }

        $fh = @fopen($logFile, 'rb');
        if ($fh === false) {
            $this->error('Nao conseguiu abrir log');
            return self::FAILURE;
        }
        @fseek($fh, $lastOffset);

        $limit = (int) $this->option('limit');
        $processed = 0;
        $inserted = 0;

        while (! feof($fh) && $processed < $limit) {
            $line = fgets($fh);
            if ($line === false) {
                break;
            }
            $processed++;

            $match = $this->classify($line);
            if ($match === null) {
                continue;
            }

            [$event, $errorCode] = $match;

            // Dedup por hash+timestamp — evita insert duplicado em re-runs
            $hash = hash('sha256', $line);
            $exists = LicencaLog::where('metadata->line_hash', $hash)->exists();
            if ($exists) {
                continue;
            }

            LicencaLog::create([
                'event'         => $event,
                'error_code'    => $errorCode,
                'error_message' => Str::limit(trim($line), 500, ''),
                'source'        => 'log_parser',
                'metadata'      => ['line_hash' => $hash],
                'created_at'    => $this->extractTimestamp($line) ?? now(),
            ]);
            $inserted++;
        }

        $newOffset = ftell($fh);
        fclose($fh);
        \Cache::put($offsetKey, $newOffset, now()->addDays(30));

        $this->info("processed={$processed} inserted={$inserted} offset={$newOffset}");
        return self::SUCCESS;
    }

    /** Retorna [event, error_code] ou null */
    private function classify(string $line): ?array
    {
        foreach ($this->patterns as $needle => $event) {
            if (stripos($line, $needle) !== false) {
                $code = $event === 'login_error' ? $this->extractErrorCode($line, $needle) : null;
                return [$event, $code];
            }
        }
        return null;
    }

    private function extractErrorCode(string $line, string $fallback): string
    {
        // Tenta extrair "error":"xxx" ou "code":"xxx"
        if (preg_match('/"error"\s*:\s*"([^"]+)"/i', $line, $m)) {
            return $m[1];
        }
        if (preg_match('/"code"\s*:\s*"([^"]+)"/i', $line, $m)) {
            return $m[1];
        }
        return Str::slug($fallback, '_');
    }

    private function extractTimestamp(string $line): ?\Carbon\Carbon
    {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
            try {
                return \Carbon\Carbon::parse($m[1]);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
}
