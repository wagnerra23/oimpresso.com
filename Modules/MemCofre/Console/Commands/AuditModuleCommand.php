<?php

namespace Modules\MemCofre\Console\Commands;

use Illuminate\Console\Command;
use Modules\MemCofre\Services\ModuleAuditor;
use Modules\MemCofre\Services\RequirementsFileReader;

/**
 * Executa auditoria de qualidade da documentação de um módulo (ADR 0007).
 *
 * Uso:
 *   php artisan memcofre:audit-module MemCofre
 *   php artisan memcofre:audit-module PontoWr2 --save
 *   php artisan memcofre:audit-module --all
 */
class AuditModuleCommand extends Command
{
    protected $signature = 'memcofre:audit-module {module?} {--all} {--save}';

    protected $description = 'Auditoria de qualidade da documentação de um módulo (15 checks)';

    public function handle(ModuleAuditor $auditor, RequirementsFileReader $reader): int
    {
        $modules = [];
        if ($this->option('all')) {
            foreach ($reader->listModules() as $m) {
                if (($m['format'] ?? 'flat') === 'folder') {
                    $modules[] = $m['name'];
                }
            }
        } elseif ($name = $this->argument('module')) {
            $modules = [$name];
        } else {
            $this->error('Especifique um módulo ou use --all.');
            return 1;
        }

        $results = [];
        foreach ($modules as $m) {
            $result = $auditor->audit($m);
            $results[] = $result;
            $this->renderSingle($result);

            if ($this->option('save')) {
                $path = $auditor->saveReport($result);
                if ($path) $this->line("  → salvo em " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path));
            }
            $this->line('');
        }

        // Resumo quando --all
        if (count($modules) > 1) {
            $this->line('');
            $this->info('Resumo geral:');
            $rows = array_map(fn ($r) => [
                $r['module'],
                "{$r['score']}/100",
                $r['critical'],
                $r['warning'],
                $r['info'],
            ], $results);
            $this->table(['Módulo', 'Score', 'Critical', 'Warning', 'Info'], $rows);
        }

        $hasCritical = collect($results)->contains(fn ($r) => $r['critical'] > 0);
        return $hasCritical ? 1 : 0;
    }

    protected function renderSingle(array $r): void
    {
        $scoreColor = $r['score'] >= 80 ? 'info' : ($r['score'] >= 50 ? 'comment' : 'error');
        $this->line("═══ {$r['module']} ═══");
        $this->{$scoreColor}("Score: {$r['score']}/100");
        $this->line("  critical: {$r['critical']}  warning: {$r['warning']}  info: {$r['info']}");

        if (empty($r['findings'])) {
            $this->info('  ✓ Sem issues');
            return;
        }

        foreach ($r['findings'] as $f) {
            $tag = strtoupper($f['level']);
            $this->line("  [{$f['code']}] {$tag}: {$f['message']}");
        }
    }
}
