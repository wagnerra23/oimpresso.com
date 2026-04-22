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
                            {--stdout : Imprimir no stdout em vez de salvar em memory/modulos/}';

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
     *  - atual (working dir / 6.7-react)
     *  - main-wip-2026-04-22 (backup Wagner)
     *  - origin/3.7-com-nfe (versão antiga)
     *  - origin/6.7-bootstrap
     */
    protected function discoverAllModules(ModuleManagerService $mgr): array
    {
        $names = array_column($mgr->list(), 'name');

        $branches = ['main-wip-2026-04-22', 'origin/3.7-com-nfe', 'origin/6.7-bootstrap'];
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
        $md .= "**Total:** " . count($index) . " módulos únicos encontrados em todas as branches conhecidas (atual, `main-wip-2026-04-22`, `origin/3.7-com-nfe`, `origin/6.7-bootstrap`).\n\n";

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
            $md .= "| Módulo | main-wip | 3.7 | 6.7-bootstrap | Ação sugerida |\n";
            $md .= "|---|:-:|:-:|:-:|---|\n";
            foreach ($lost as $row) {
                $mw = ($row['branches']['main-wip-2026-04-22'] ?? false) ? '✅' : '—';
                $v37 = ($row['branches']['origin/3.7-com-nfe'] ?? false) ? '✅' : '—';
                $bs = ($row['branches']['origin/6.7-bootstrap'] ?? false) ? '✅' : '—';
                $md .= "| [{$row['name']}]({$row['name']}.md) | {$mw} | {$v37} | {$bs} | (definir) |\n";
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
        $md .= "php artisan module:specs PontoWr2     # um só\n";
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
