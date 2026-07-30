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
                            {--aceito-perda-de-branch : Regravar mesmo quando uma branch histórica sumiu e o registro dela for insubstituível}';

    protected $description = 'Inspeciona módulo(s) e gera spec markdown em memory/modulos/';

    public function handle(ModuleSpecGenerator $gen, ModuleManagerService $mgr): int
    {
        $single = $this->argument('module');
        $toStdout = (bool) $this->option('stdout');

        $targets = $single
            ? [$single]
            : $this->discoverAllModules($mgr);

        if (empty($targets)) {
            $this->warn('Nenhum módulo encontrado.');
            return self::SUCCESS;
        }

        $this->info(count($targets) . ' módulos descobertos (atuais + perdidos em branches antigas)');

        $outDir = base_path('memory/modulos');

        if (! $toStdout && ! $this->guardaPerdaDeBranch($gen, $outDir)) {
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
            $md = $gen->renderMarkdown($spec);

            if ($toStdout) {
                $this->line($md);
                continue;
            }

            $file = $outDir . DIRECTORY_SEPARATOR . $name . '.md';
            File::put($file, $md);
            $this->line("   <fg=green>✓</> " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));

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
     * Lista TODOS os módulos únicos encontrados em qualquer branch conhecida:
     *  - atual (working dir / main)
     *  - main-wip-2026-04-22 (backup Wagner)
     *  - origin/3.7-com-nfe (versão antiga)
     */
    /**
     * Impede que regenerar apague, em silêncio, o único registro de uma branch que sumiu.
     *
     * `memory/modulos/` é a ÚNICA memória de módulos que não existem mais em lugar nenhum:
     * `main-wip-2026-04-22` (backup) sumiu do repo e do remoto, e 6 módulos só constam
     * dela — Accounting, AiAssistance, Grow, IProduction, Officeimpresso1, Writebot,
     * todos ausentes de `origin/3.7-com-nfe` (medido 2026-07-30). Como o git não sabe
     * mais responder por essa branch, regravar as specs trocaria o `✅` deles por `n/d`
     * e o fato se perderia — sem erro, sem aviso, sem volta.
     *
     * Então: se uma branch histórica sumiu E existe arquivo que a registra, aborta e
     * mostra quais. `--aceito-perda-de-branch` segue adiante, agora como escolha.
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

    protected function discoverAllModules(ModuleManagerService $mgr): array
    {
        $names = array_column($mgr->list(), 'name');

        $branches = ['main-wip-2026-04-22', 'origin/3.7-com-nfe'];
        // Redirecionamento compatível com Windows (NUL) e Unix (/dev/null)
        $nullDev = stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null';
        foreach ($branches as $br) {
            $cmd = 'git -C ' . escapeshellarg(base_path())
                 . ' ls-tree --name-only ' . escapeshellarg($br) . ' Modules/ 2>' . $nullDev;
            $out = shell_exec($cmd);
            if (!$out) continue;
            foreach (array_filter(explode("\n", trim($out))) as $line) {
                $name = basename(trim($line));
                if ($name !== '' && !in_array($name, $names)) {
                    $names[] = $name;
                }
            }
        }
        sort($names);
        return $names;
    }

    protected function writeIndex(string $dir, array $index): void
    {
        usort($index, function ($a, $b) {
            // ativos primeiro
            if ($a['active'] !== $b['active']) return $a['active'] ? -1 : 1;
            // menor complexidade primeiro (alta prioridade = mais fácil)
            $ca = $a['routes'] + $a['views'];
            $cb = $b['routes'] + $b['views'];
            return $ca <=> $cb;
        });

        $md  = "# Índice de Specs dos Módulos\n\n";
        $md .= "Gerado por `php artisan module:specs` em " . now()->format('Y-m-d H:i') . ".\n\n";
        $md .= "**Total:** " . count($index) . " módulos únicos encontrados em todas as branches conhecidas (atual, `main-wip-2026-04-22`, `origin/3.7-com-nfe`).\n\n";

        // Separar em 3 grupos: ativos / inativos locais / perdidos (não existem no atual)
        $active = array_values(array_filter($index, fn($r) => $r['active']));
        $inactiveLocal = array_values(array_filter($index, fn($r) => !$r['active'] && $r['in_current']));
        $lost = array_values(array_filter($index, fn($r) => !$r['in_current']));

        $md .= "## 🟢 Ativos (" . count($active) . ")\n\n";
        $md .= $this->renderIndexTable($active);

        if (!empty($inactiveLocal)) {
            $md .= "\n## ⚪ Inativos no branch atual (" . count($inactiveLocal) . ")\n\n";
            $md .= "_Existem em `Modules/` mas com flag `false` em `modules_statuses.json`._\n\n";
            $md .= $this->renderIndexTable($inactiveLocal);
        }

        if (!empty($lost)) {
            $md .= "\n## ❌ Perdidos na migração 3.7 → 6.7 (" . count($lost) . ")\n\n";
            $md .= "_**Existem em branches antigas** (`main-wip-2026-04-22` ou `origin/3.7-com-nfe`) **mas não na branch atual 6.7-react.**_\n";
            $md .= "_Potenciais funcionalidades que ficaram para trás. Decidir se trazer de volta ou abandonar._\n\n";
            $md .= "| Módulo | main-wip | 3.7 | Ação sugerida |\n";
            $md .= "|---|:-:|:-:|---|\n";
            foreach ($lost as $row) {
                $mw = ($row['branches']['main-wip-2026-04-22'] ?? false) ? '✅' : '—';
                $v37 = ($row['branches']['origin/3.7-com-nfe'] ?? false) ? '✅' : '—';
                $md .= "| [{$row['name']}]({$row['name']}.md) | {$mw} | {$v37} | (definir) |\n";
            }
        }

        $md .= "\n## Como usar\n\n";
        $md .= "1. Abra o spec de um módulo (coluna 'Módulo' é link).\n";
        $md .= "2. Na seção **'Gaps & próximos passos'**, preencha customizações suas conhecidas.\n";
        $md .= "3. Compare com o código original do UltimatePOS 6.7 para identificar o diff (seção automática).\n";
        $md .= "4. Use 'Prioridade' e 'Risco' para definir ordem de migração.\n\n";
        $md .= "## Regenerar\n\n";
        $md .= "```bash\n";
        $md .= "php artisan module:specs              # todos\n";
        $md .= "php artisan module:specs Ponto        # um só\n";
        $md .= "php artisan module:specs --stdout     # ver sem salvar\n";
        $md .= "```\n";

        File::put($dir . DIRECTORY_SEPARATOR . 'INDEX.md', $md);
    }

    protected function renderIndexTable(array $rows): string
    {
        $md  = "| # | Módulo | Prioridade | Risco | Rotas | Views | Migrations | Permissões | Hooks |\n";
        $md .= "|--:|---|---|---|--:|--:|--:|--:|--:|\n";
        foreach ($rows as $i => $row) {
            $md .= sprintf(
                "| %d | [%s](%s.md) | %s | %s | %d | %d | %d | %d | %d |\n",
                $i + 1,
                $row['name'],
                $row['name'],
                $row['priority'],
                $row['risk'],
                $row['routes'],
                $row['views'],
                $row['migrations'],
                $row['permissions'],
                $row['upos_hooks']
            );
        }
        return $md;
    }
}
