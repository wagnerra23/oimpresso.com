<?php

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\Mcp\ImportarSkillsDoGitService;

/**
 * ADR 0076 (Fase 1) — import inicial das skills filesystem → DB.
 *
 * Idempotente. Pode rodar quantas vezes quiser.
 *
 * Uso: php artisan mcp:skills:import-from-git
 */
class McpSkillsImportFromGitCommand extends Command
{
    protected $signature = 'mcp:skills:import-from-git';

    protected $description = 'Importa .claude/skills/<slug>/SKILL.md pra mcp_skills/mcp_skill_versions (idempotente).';

    public function handle(ImportarSkillsDoGitService $service): int
    {
        $this->info('→ Import iniciado.');
        $r = $service->run();

        $this->line("  Criadas:    {$r['created']}");
        $this->line("  Atualizadas: {$r['updated']}");
        $this->line("  Inalteradas: {$r['unchanged']}");
        $this->line("  Skipped:    {$r['skipped']}");

        if (! empty($r['errors'])) {
            $this->error('Erros:');
            foreach ($r['errors'] as $e) {
                $this->line("  • $e");
            }

            return self::FAILURE;
        }

        $this->info('✓ Import concluído.');

        return self::SUCCESS;
    }
}
