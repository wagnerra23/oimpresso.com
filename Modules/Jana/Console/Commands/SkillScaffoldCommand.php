<?php

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\ScaffoldSkillFromMissionService;

/**
 * Cria scaffold de skill nova a partir de 1 frase (missão).
 *
 * Aplica a meta-skill `meta-skill-roi-erp-autonomo` antes de criar:
 * — substitui trabalho humano repetitivo?
 * — com ROI mensurável?
 * — rumo ao ERP autônomo R$ [redacted Tier 0]M / 24m?
 *
 * Saída: arquivo `.claude/skills/<slug>/SKILL.md` + entry em `mcp_skills`
 * status=draft. O body se edita no PRÓPRIO arquivo em git — a UI
 * `/ads/admin/skills/<slug>/edit` foi removida na parte 6 (ADR 0363) junto
 * com o Modules/ADS. O `SkillsService` continua vivo, aqui na Jana (#5129).
 *
 * ADR 0078 — A constituição do oimpresso é uma frase.
 */
class SkillScaffoldCommand extends Command
{
    protected $signature = 'skill:scaffold
                            {mission : Frase única descrevendo a missão da skill}
                            {--slug= : Slug opcional (auto-derivado da missão se omitido)}
                            {--force : Pular validação dos 4 testes da meta-skill}';

    protected $description = 'Cria scaffold de skill nova a partir de uma frase (validada pela meta-skill ROI ERP autônomo)';

    public function handle(ScaffoldSkillFromMissionService $service): int
    {
        $mission = (string) $this->argument('mission');
        $slug = $this->option('slug');
        $force = (bool) $this->option('force');

        $this->newLine();
        $this->line("<fg=cyan>Meta-skill</> aplicando 4 testes na missão:");
        $this->line("  <fg=white>\"{$mission}\"</>");
        $this->newLine();

        $result = $service->run($mission, $slug, $force);

        // Imprime resultado dos testes
        $tests = $result['tests'];
        $this->line('  Substitui trabalho humano? '.($tests['substitui'] ? '<fg=green>✓</>' : '<fg=red>✗</>'));
        $this->line('  Trabalho repetitivo?       '.($tests['repetitivo'] ? '<fg=green>✓</>' : '<fg=red>✗</>'));
        $this->line('  ROI mensurável?            <fg=yellow>?</> (declare no SKILL.md gerado)');
        $this->line('  Acelera R$ [redacted Tier 0]M / 24m?      <fg=yellow>?</> (declare no SKILL.md gerado)');
        $this->newLine();

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info('✓ '.$result['message']);
        $this->newLine();
        $this->line('  <fg=gray>Filesystem:</> '.$result['git_path']);
        if ($result['skill_id'] !== null) {
            $this->line('  <fg=gray>DB:</> mcp_skills id='.$result['skill_id'].' status=draft');
        } else {
            $this->line('  <fg=yellow>DB:</> não persistido (schema mcp_skills indisponível)');
        }
        $this->newLine();
        $this->line('Próximo passo:');
        $this->line('  1. Editar body em <fg=cyan>'.($result['git_path'] ?? '.claude/skills/'.$result['slug'].'/SKILL.md').'</>');
        $this->line('  2. Preencher 4 TODOs (substitui/repetitivo/ROI/R$ [redacted Tier 0]M)');
        $this->line('  3. Commitar — a revisão é o PR');
        $this->newLine();

        return self::SUCCESS;
    }
}
