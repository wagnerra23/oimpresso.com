<?php

namespace App\Console\Commands;

use App\Services\ModuleManagerService;
use App\Services\ModuleSpecGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateModuleSpecsCommand extends Command
{
    protected $signature = 'module:specs
                            {module? : Nome do módulo (default: todos)}
                            {--stdout : Imprimir no stdout em vez de salvar em memory/modulos/}
                            {--index-only : Regenerar somente o índice atual, sem tocar specs individuais}';

    protected $description = 'Inspeciona módulos atuais; mantém snapshots técnicos legados e o índice de autoridade.';

    public function handle(ModuleSpecGenerator $gen, ModuleManagerService $mgr): int
    {
        $single = $this->argument('module');
        $toStdout = (bool) $this->option('stdout');
        $indexOnly = (bool) $this->option('index-only');

        if ($single && $indexOnly) {
            $this->error('--index-only não aceita módulo individual.');
            return self::INVALID;
        }

        $targets = $single
            ? [$single]
            : $this->discoverAllModules($mgr);

        if (empty($targets)) {
            $this->warn('Nenhum módulo encontrado.');
            return self::SUCCESS;
        }

        $this->info(count($targets) . ' módulos registrados no checkout atual.');

        $outDir = base_path('memory/modulos');
        if (!$toStdout && !File::isDirectory($outDir)) {
            File::makeDirectory($outDir, 0755, true);
        }

        $index = [];

        foreach ($targets as $name) {
            $this->info("→ Inspecionando {$name}…");
            $spec = $gen->inspect($name);
            if (isset($spec['error'])) {
                $this->error($spec['error']);
                continue;
            }
            if (! $indexOnly) {
                $md = $gen->renderMarkdown($spec);
                if ($toStdout) {
                    $this->line($md);
                } else {
                    $file = $outDir . DIRECTORY_SEPARATOR . $name . '.md';
                    File::put($file, $md);
                    $this->line("   <fg=green>✓</> " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));
                }
            }

            $index[] = [
                'name'        => $name,
                'priority'    => $spec['signals']['migration_priority'],
                'risk'        => $spec['signals']['risk'],
                'active'      => $spec['signals']['active'],
                'in_current'  => $spec['exists_in_current'] ?? false,
                'branches'    => $spec['branch_presence'] ?? [],
                'routes'      => count($spec['routes']['all']),
                'views'       => $spec['views']['count'],
                'migrations'  => count($spec['migrations']),
                'upos_hooks'  => count($spec['upos_hooks'] ?? []),
                'permissions' => count(($spec['permissions']['registered']) ?? []),
            ];
        }

        if (!$toStdout && !$single) {
            $this->writeIndex($outDir, $index);
            $this->info('Índice consolidado: memory/modulos/INDEX.md');
        }

        return self::SUCCESS;
    }

    /** Lista somente módulos registrados no checkout atual. Histórico continua no Git. */
    protected function discoverAllModules(ModuleManagerService $mgr): array
    {
        $names = array_column($mgr->list(), 'name');
        sort($names);
        return $names;
    }

    protected function writeIndex(string $dir, array $index): void
    {
        usort($index, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $md  = "# Índice técnico dos módulos\n\n";
        $md .= "> Projeção determinística dos diretórios atuais que possuem `Modules/<M>/module.json`.\n";
        $md .= "> Regenerar com `php artisan module:specs --index-only`.\n\n";
        $md .= "**Autoridade:** `module.json` declara a existência; `SCOPE.md` declara a fronteira;\n";
        $md .= "`memory/requisitos/<M>/SPEC.md` declara comportamento; `SUPERFICIE.md` é inventário\n";
        $md .= "gerado. Os arquivos históricos `memory/modulos/<M>.md` permanecem como snapshots e não\n";
        $md .= "definem quais módulos existem.\n\n";
        $md .= "**Total atual:** " . count($index) . " módulos.\n\n";
        $md .= "| Módulo | Manifesto | Fronteira | Superfície | Requisitos |\n";
        $md .= "|---|---|---|---|---|\n";
        foreach ($index as $row) {
            $name = $row['name'];
            $md .= "| {$name} | [module.json](../../Modules/{$name}/module.json) | [SCOPE](../../Modules/{$name}/SCOPE.md) | [SUPERFICIE](../requisitos/{$name}/SUPERFICIE.md) | [SPEC](../requisitos/{$name}/SPEC.md) |\n";
        }

        File::put($dir . DIRECTORY_SEPARATOR . 'INDEX.md', $md);
    }
}
