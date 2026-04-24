<?php

namespace App\Console\Commands;

use App\Services\ModuleManagerService;
use App\Services\ModuleRequirementsGenerator;
use App\Services\ModuleSpecGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Gera arquivos de requisitos funcionais por módulo em `memory/requisitos/`.
 *
 * Complementa o `module:specs` (técnico) com foco funcional/negócio:
 * user stories, regras Gherkin, DoD, rastreabilidade.
 *
 * Uso:
 *   php artisan module:requirements              # todos os módulos
 *   php artisan module:requirements Essentials   # 1 módulo
 *   php artisan module:requirements --force      # sobrescreve arquivos existentes
 *   php artisan module:requirements --stdout     # imprime sem salvar
 */
class GenerateModuleRequirementsCommand extends Command
{
    protected $signature = 'module:requirements
                            {module? : Nome do módulo (default: todos)}
                            {--force : Sobrescreve arquivos existentes (cuidado: perde edições manuais)}
                            {--stdout : Imprime no stdout em vez de salvar}';

    protected $description = 'Gera memory/requisitos/<Modulo>.md com user stories + regras Gherkin + DoD.';

    public function handle(
        ModuleSpecGenerator $specGen,
        ModuleRequirementsGenerator $reqGen,
        ModuleManagerService $mgr
    ): int {
        $single = $this->argument('module');
        $force = (bool) $this->option('force');
        $toStdout = (bool) $this->option('stdout');

        $targets = $single
            ? [$single]
            : $this->discoverAllModules($mgr);

        if (empty($targets)) {
            $this->warn('Nenhum módulo encontrado.');
            return self::SUCCESS;
        }

        $this->info(count($targets) . ' módulo(s) a processar.');

        $outDir = base_path('memory/requisitos');
        if (! $toStdout && ! File::isDirectory($outDir)) {
            File::makeDirectory($outDir, 0755, true);
            $this->line("→ Criei diretório {$outDir}");
        }

        $summary = [];

        foreach ($targets as $name) {
            $spec = $specGen->inspect($name);
            if (isset($spec['error'])) {
                $this->error("  ✗ {$name}: " . $spec['error']);
                continue;
            }

            $md = $reqGen->render($spec);

            if ($toStdout) {
                $this->line("# ============ {$name} ============");
                $this->line($md);
                continue;
            }

            $file = $outDir . DIRECTORY_SEPARATOR . $name . '.md';
            $existed = File::exists($file);

            if ($existed && ! $force) {
                $this->line("  <fg=yellow>·</> {$name} — já existe (use --force para sobrescrever)");
                $summary[] = ['name' => $name, 'action' => 'skipped', 'active' => $spec['signals']['active'] ?? false];
                continue;
            }

            File::put($file, $md);
            $this->line("  <fg=green>✓</> {$name}");
            $summary[] = [
                'name'   => $name,
                'action' => $existed ? 'overwritten' : 'created',
                'active' => $spec['signals']['active'] ?? false,
                'exists_in_current' => $spec['exists_in_current'] ?? false,
            ];
        }

        if (! $toStdout && ! $single) {
            $this->writeIndex($outDir, $summary);
            $this->info('Índice: memory/requisitos/INDEX.md');
        }

        return self::SUCCESS;
    }

    protected function discoverAllModules(ModuleManagerService $mgr): array
    {
        // Inclui tanto os ativos em Modules/ quanto os "perdidos" em branches antigas.
        // Reusa a descoberta do module:specs: lê memory/modulos/*.md.
        $modulesDir = base_path('Modules');
        $current = [];
        if (File::isDirectory($modulesDir)) {
            foreach (File::directories($modulesDir) as $dir) {
                $current[] = basename($dir);
            }
        }

        $specs = [];
        $specsDir = base_path('memory/modulos');
        if (File::isDirectory($specsDir)) {
            foreach (File::files($specsDir) as $f) {
                $fname = $f->getFilenameWithoutExtension();
                if (in_array($fname, ['INDEX', 'RECOMENDACOES'], true)) continue;
                $specs[] = $fname;
            }
        }

        $all = array_unique(array_merge($current, $specs));
        sort($all);
        return $all;
    }

    protected function writeIndex(string $dir, array $summary): void
    {
        $active = array_filter($summary, fn ($s) => $s['active'] ?? false);
        $inactive = array_filter($summary, fn ($s) => ! ($s['active'] ?? true) && ($s['exists_in_current'] ?? false));
        $legacy = array_filter($summary, fn ($s) => ! ($s['exists_in_current'] ?? true));

        $md = "# Índice — Requisitos funcionais por módulo\n\n";
        $md .= "> Documentação viva, complementa `memory/modulos/` (spec técnica)\n";
        $md .= "> com foco no **valor de negócio** — user stories, regras Gherkin, DoD.\n";
        $md .= ">\n";
        $md .= "> **Atualizado em " . now()->format('Y-m-d H:i') . "**\n\n";

        $md .= "## Resumo\n\n";
        $md .= "| Categoria | Módulos | % |\n";
        $md .= "|---|---:|---:|\n";
        $total = count($summary);
        if ($total > 0) {
            $md .= "| 🟢 Ativos | " . count($active) . " | " . round(count($active) / $total * 100) . "% |\n";
            $md .= "| ⚪ Inativos (presentes) | " . count($inactive) . " | " . round(count($inactive) / $total * 100) . "% |\n";
            $md .= "| ⚠️ Legados (ausentes) | " . count($legacy) . " | " . round(count($legacy) / $total * 100) . "% |\n";
            $md .= "| **Total** | **{$total}** | 100% |\n\n";
        }

        $md .= "## Módulos ativos\n\n";
        $md .= "Clique para ver requisitos funcionais.\n\n";
        foreach ($active as $m) {
            $md .= "- [{$m['name']}]({$m['name']}.md)\n";
        }
        $md .= "\n";

        if (! empty($inactive)) {
            $md .= "## Módulos inativos (presentes no branch atual)\n\n";
            foreach ($inactive as $m) {
                $md .= "- [{$m['name']}]({$m['name']}.md)\n";
            }
            $md .= "\n";
        }

        if (! empty($legacy)) {
            $md .= "## Módulos legados (ausentes — decidir ressuscitar/deprecar)\n\n";
            foreach ($legacy as $m) {
                $md .= "- [{$m['name']}]({$m['name']}.md) ⚠️\n";
            }
            $md .= "\n";
        }

        $md .= "## Como trabalhar com estes arquivos\n\n";
        $md .= "1. **Formato estruturado** — cada arquivo tem frontmatter YAML + user stories (`US-XXX-NNN`)\n";
        $md .= "   + regras Gherkin (`R-XXX-NNN`) + DoD rastreável com a tela React.\n";
        $md .= "2. **Fonte única da verdade funcional** — quando o código muda, atualizar o requisito.\n";
        $md .= "3. **Regerar** — `php artisan module:requirements` gera arquivos faltantes\n";
        $md .= "   sem sobrescrever edições manuais. Use `--force` com cuidado.\n";
        $md .= "4. **Módulo MemCofre** (`/docs`) consome esses arquivos e linka com evidências\n";
        $md .= "   (screenshots de bug, chat logs, erros reportados).\n\n";

        $md .= "---\n";
        $md .= "_Regerar índice: `php artisan module:requirements`_\n";

        File::put($dir . DIRECTORY_SEPARATOR . 'INDEX.md', $md);
    }
}
