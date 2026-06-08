<?php

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\Mcp\AutomationRegistrySync;

/**
 * ADR 0234 (Onda 1.1) — sync do Registry de Automações filesystem → DB.
 *
 * Espelha mcp:skills:import-from-git. Idempotente: pode rodar quantas vezes
 * quiser (rodar 2× não duplica). Varre .claude/hooks/ + Kernel.php + .claude/*.json,
 * faz upsert em mcp_automations e alerta drift em mcp_alertas_eventos.
 *
 * Uso: php artisan jana:automations:sync
 */
class AutomationsSyncCommand extends Command
{
    protected $signature = 'jana:automations:sync {--detail : Mostra slugs de drift detalhados}';

    protected $description = 'Sincroniza .claude/hooks + Kernel.php + .claude/*.json pra mcp_automations (idempotente, ADR 0234).';

    public function handle(AutomationRegistrySync $service): int
    {
        $this->info('→ Sync do Registry de Automações iniciado.');
        $r = $service->run();

        $this->line("  Criadas:     {$r['created']}");
        $this->line("  Atualizadas: {$r['updated']}");
        $this->line("  Inalteradas: {$r['unchanged']}");
        $this->line('  Órfãs (no DB, sem fonte no FS): ' . count($r['orphan_files']));
        $this->line('  Ausentes (arquivo sumiu):       ' . count($r['missing_files']));
        $this->line("  Alertas de drift criados:       {$r['alerts_created']}");

        if ($this->option('detail')) {
            if (! empty($r['orphan_files'])) {
                $this->warn('  Órfãs:');
                foreach ($r['orphan_files'] as $slug) {
                    $this->line("    • {$slug}");
                }
            }
            if (! empty($r['missing_files'])) {
                $this->warn('  Ausentes:');
                foreach ($r['missing_files'] as $arquivo) {
                    $this->line("    • {$arquivo}");
                }
            }
        }

        if (! empty($r['errors'])) {
            $this->error('Erros (não-fatais):');
            foreach ($r['errors'] as $e) {
                $this->line("  • {$e}");
            }

            return self::FAILURE;
        }

        $this->info('✓ Sync concluído.');

        return self::SUCCESS;
    }
}
