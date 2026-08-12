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
                            {--index-only : Regenerar somente o índice atual, sem tocar specs individuais}
                            {--aceito-perda-de-branch : Regravar mesmo quando uma branch histórica sumiu e o registro dela for insubstituível}';

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

        // Só corre risco de perda quem de fato reescreve os specs individuais.
        // `--stdout` não grava; `--index-only` só toca INDEX.md, que não carrega
        // coluna de presença-em-branch (ver writeIndex) — nenhum dos dois apaga registro.
        if (! $toStdout && ! $indexOnly && ! $this->guardaPerdaDeBranch($gen, $outDir)) {
            return self::FAILURE;
        }

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

    /**
     * Impede que regenerar apague, em silêncio, o único registro de uma branch que sumiu.
     *
     * `memory/modulos/` é a ÚNICA memória de módulos que não existem mais em lugar nenhum:
     * `main-wip-2026-04-22` (backup) sumiu do repo e do remoto, e 6 módulos só constam
     * dela — Accounting, AiAssistance, Grow, IProduction, Officeimpresso1, Writebot,
     * todos ausentes também de `origin/3.7-com-nfe` (medido 2026-07-30). Como o git não
     * sabe mais responder por essa branch, regravar as specs trocaria o `✅` deles por
     * `n/d` e o fato se perderia — sem erro, sem aviso, sem volta.
     *
     * Então: se uma branch histórica sumiu E existe arquivo que a registra, aborta e
     * mostra quais. `--aceito-perda-de-branch` segue adiante, agora como escolha.
     *
     * Histórico: nasceu no #5085 (2026-07-30) e foi removido pelo #5327 (2026-08-06),
     * que trouxe este arquivo de uma branch anterior ao guard — revert não-declarado.
     * O bite-test que o cobre é `ModuleSpecBranchPresenceTest`, e ele ficou vermelho no
     * mesmo dia; a lane `PHP / Pest (Ponto · MySQL)` já estava vermelha por outra causa,
     * então ninguém viu. Restaurado aqui sobre a versão atual do arquivo (o `--index-only`
     * e o `discoverAllModules` enxuto do #5327 seguem intactos).
     */
    protected function guardaPerdaDeBranch(ModuleSpecGenerator $gen, string $outDir): bool
    {
        $ausentes = $gen->branchesAusentes();
        if ($ausentes === [] || ! File::isDirectory($outDir)) {
            return true;
        }

        $emRisco = [];
        foreach (File::files($outDir) as $arquivo) {
            if ($arquivo->getExtension() !== 'md') {
                continue;
            }
            $conteudo = (string) File::get($arquivo->getPathname());
            foreach ($ausentes as $branch) {
                if (preg_match('/^\|.*' . preg_quote($branch, '/') . '.*✅.*\|$/mu', $conteudo)) {
                    $emRisco[$branch][] = $arquivo->getFilenameWithoutExtension();
                }
            }
        }

        if ($emRisco === []) {
            foreach ($ausentes as $branch) {
                $this->warn("Branch histórica `{$branch}` não existe mais — a presença nela sai como `n/d`.");
            }

            return true;
        }

        if ($this->option('aceito-perda-de-branch')) {
            foreach ($emRisco as $branch => $modulos) {
                $this->warn("Regravando mesmo assim: `{$branch}` (" . count($modulos) . ' módulo(s) perdem o registro).');
            }

            return true;
        }

        $this->error('ABORTADO — regravar apagaria o único registro de uma branch que não existe mais.');
        foreach ($emRisco as $branch => $modulos) {
            sort($modulos);
            $this->line("  <fg=yellow>{$branch}</> (sumiu do repo) é registrada por: " . implode(', ', $modulos));
        }
        $this->newLine();
        $this->line('  Esses arquivos são a última prova de que esses módulos existiram.');
        $this->line('  Confira antes: <fg=cyan>git show HEAD:memory/modulos/<Modulo>.md</>');
        $this->line('  Ciente e quer prosseguir: <fg=cyan>php artisan module:specs --aceito-perda-de-branch</>');

        return false;
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
            $md .= "| {$name} | [module.json](../../Modules/{$name}/module.json) | [SCOPE](../requisitos/{$name}/SCOPE.md) | [SUPERFICIE](../requisitos/{$name}/SUPERFICIE.md) | [SPEC](../requisitos/{$name}/SPEC.md) |\n";
        }

        File::put($dir . DIRECTORY_SEPARATOR . 'INDEX.md', $md);
    }
}
