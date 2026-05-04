<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Camada 3.5 — status rápido de drift entre auto-mem e memory/claude/.
 *
 * Wrapper amigável sobre `memcofre:sync-memories --dry`. Útil pra:
 *   - CI gate (exit 1 se drift > 0)
 *   - quick-check antes de commit ("posso pushar?")
 *
 * Ver: ADR 0061 (zero auto-mem privada).
 */
class MemSyncStatusCommand extends Command
{
    protected $signature = 'mem:sync-status';

    protected $description = 'Mostra drift entre auto-mem e memory/claude/ (exit 1 se drift > 0)';

    public function handle(): int
    {
        Artisan::call('memcofre:sync-memories', ['--dry' => true]);
        $output = Artisan::output();

        $adds = $changes = $removes = 0;
        foreach (preg_split('/\r\n|\n|\r/', $output) as $line) {
            if (preg_match('/^\s*\+\s+\S/', $line)) $adds++;
            elseif (preg_match('/^\s*~\s+\S/', $line)) $changes++;
            elseif (preg_match('/^\s*-\s+\S/', $line)) $removes++;
        }

        $drift = $adds + $changes;

        // Última sync — tenta filemtime de memory/claude/
        $claudeDir = base_path('memory/claude');
        $ultimaSync = is_dir($claudeDir) ? $this->formatarTempo(filemtime($claudeDir)) : 'desconhecido';

        if ($drift === 0) {
            $this->info('✅ 0 drift, mirror sincronizado');
            $this->line("Última sync: $ultimaSync");
            if ($removes > 0) {
                $this->line("(detectadas $removes deletions já propagadas)");
            }
            return 0;
        }

        $this->warn("⚠️ $drift arquivos pendentes ($adds adds, $changes modify)");
        $this->line('Rode: <comment>php artisan memcofre:sync-memories</comment>');
        $this->line("Última sync: $ultimaSync");
        return 1;
    }

    private function formatarTempo(int $ts): string
    {
        $diff = time() - $ts;
        if ($diff < 60) return "há {$diff}s";
        if ($diff < 3600) return 'há ' . round($diff / 60) . 'min';
        if ($diff < 86400) return 'há ' . round($diff / 3600) . 'h';
        return 'há ' . round($diff / 86400) . 'd';
    }
}
