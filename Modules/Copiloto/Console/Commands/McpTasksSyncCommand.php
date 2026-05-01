<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Modules\Copiloto\Services\TaskRegistry\TaskParserService;

/**
 * TaskRegistry Fase 0 — sync git→DB de US-* dos SPECs canônicos.
 *
 * Padrão idêntico ao IndexarMemoryGitParaDb (mcp_memory_documents).
 * Roda via webhook GitHub após push (futuro) ou manual.
 */
class McpTasksSyncCommand extends Command
{
    protected $signature = 'mcp:tasks:sync
                            {--module=  : Limitar a um módulo específico (default: todos)}
                            {--dry-run  : Mostra o que seria sincronizado sem persistir}';

    protected $description = 'Sincroniza US-* dos SPECs canônicos pra mcp_tasks (TaskRegistry F0)';

    public function handle(TaskParserService $service): int
    {
        $modulo = $this->option('module') ?: null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN: nenhuma alteração persistida.');
        }

        $this->info('Sincronizando TaskRegistry...' . ($modulo ? " (módulo={$modulo})" : ''));

        try {
            if ($dryRun) {
                $this->dryRunOutput($service, $modulo);
                return self::SUCCESS;
            }

            $relatorio = $service->syncAll($modulo);
        } catch (\Throwable $e) {
            $this->error("Falhou: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->line('');
        $this->info("✓ Tasks processadas: {$relatorio['tasks_processadas']}");
        $this->line("  Inseridas:    {$relatorio['inseridas']}");
        $this->line("  Atualizadas:  {$relatorio['atualizadas']}");
        $this->line("  Canceladas:   {$relatorio['canceladas']} (US sumiu do SPEC)");
        if (! empty($relatorio['modulos'])) {
            $this->line('');
            $this->line('Módulos:');
            foreach ($relatorio['modulos'] as $mod => $n) {
                $this->line("  {$mod}: {$n} tasks");
            }
        }
        return self::SUCCESS;
    }

    protected function dryRunOutput(TaskParserService $service, ?string $modulo): void
    {
        $base = base_path('memory/requisitos');
        if (! is_dir($base)) {
            $this->warn("Pasta {$base} não existe.");
            return;
        }
        foreach (new \DirectoryIterator($base) as $modDir) {
            if ($modDir->isDot() || ! $modDir->isDir()) continue;
            $mod = $modDir->getFilename();
            if ($modulo !== null && $mod !== $modulo) continue;
            $spec = $modDir->getPathname() . '/SPEC.md';
            if (! is_file($spec)) continue;
            $cand = $service->parseSpec($spec, $mod);
            if ($cand->isEmpty()) continue;
            $this->line("\n=== {$mod} ({$cand->count()} tasks) ===");
            foreach ($cand as $c) {
                $owner = $c['owner'] ?? '-';
                $status = $c['status'] ?? 'todo';
                $prio = $c['priority'] ?? 'p2';
                $this->line("  [{$status}] [{$prio}] {$c['task_id']} ({$owner}) — {$c['title']}");
            }
        }
    }
}
