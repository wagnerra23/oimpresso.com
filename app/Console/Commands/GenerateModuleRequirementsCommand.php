<?php

namespace App\Console\Commands;

use App\Services\ModuleManagerService;
use App\Services\ModuleRequirementsGenerator;
use App\Services\ModuleSpecGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Gera arquivos de requisitos funcionais em `memory/requisitos/<Modulo>/SPEC.md`.
 *
 * Mantém o contrato funcional canônico ao lado de BRIEFING e SUPERFICIE:
 * user stories, regras Gherkin, DoD e rastreabilidade.
 *
 * Uso:
 *   php artisan module:requirements              # cria somente SPECs ausentes
 *   php artisan module:requirements Essentials   # 1 módulo
 *   php artisan module:requirements --force      # sobrescreve contratos (destrutivo)
 *   php artisan module:requirements --stdout     # imprime sem salvar
 *   php artisan module:requirements --index-only # regenera somente o índice
 */
class GenerateModuleRequirementsCommand extends Command
{
    protected $signature = 'module:requirements
                            {module? : Nome do módulo (default: todos)}
                            {--force : Sobrescreve arquivos existentes (cuidado: perde edições manuais)}
                            {--stdout : Imprime no stdout em vez de salvar}
                            {--index-only : Regenerar somente o índice atual, sem tocar SPECs}';

    protected $description = 'Gera memory/requisitos/<Modulo>/SPEC.md com user stories + regras Gherkin + DoD.';

    public function handle(
        ModuleSpecGenerator $specGen,
        ModuleRequirementsGenerator $reqGen,
        ModuleManagerService $mgr
    ): int {
        $single = $this->argument('module');
        $force = (bool) $this->option('force');
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

            if ($indexOnly) {
                $summary[] = [
                    'name' => $name,
                    'active' => $spec['signals']['active'] ?? false,
                    'exists_in_current' => true,
                ];
                continue;
            }

            $md = $reqGen->render($spec);

            if ($toStdout) {
                $this->line("# ============ {$name} ============");
                $this->line($md);
                continue;
            }

            $moduleOutDir = $outDir . DIRECTORY_SEPARATOR . $name;
            if (! File::isDirectory($moduleOutDir)) {
                File::makeDirectory($moduleOutDir, 0755, true);
            }
            $file = $moduleOutDir . DIRECTORY_SEPARATOR . 'SPEC.md';
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
        $current = array_column($mgr->list(), 'name');
        sort($current);
        return $current;
    }

    protected function writeIndex(string $dir, array $summary): void
    {
        $md = "# Índice — Requisitos funcionais por módulo\n\n";
        $md .= "> ⚙️ Gerado por `php artisan module:requirements --index-only` a partir dos módulos registrados no checkout atual. Histórico fica no Git; este índice não mistura branches antigas.\n\n";
        $md .= "**Total atual:** " . count($summary) . " módulos registrados.\n\n";
        $md .= "| Módulo | BRIEFING | SPEC | SUPERFÍCIE |\n";
        $md .= "|---|:---:|:---:|:---:|\n";
        foreach ($summary as $m) {
            $name = $m['name'];
            $brief = File::exists(base_path("memory/requisitos/{$name}/BRIEFING.md"))
                ? "[abrir]({$name}/BRIEFING.md)" : "❌ ausente";
            $spec = File::exists(base_path("memory/requisitos/{$name}/SPEC.md"))
                ? "[abrir]({$name}/SPEC.md)" : "❌ ausente";
            $surface = File::exists(base_path("memory/requisitos/{$name}/SUPERFICIE.md"))
                ? "[abrir]({$name}/SUPERFICIE.md)" : "❌ ausente";
            $md .= "| **{$name}** | {$brief} | {$spec} | {$surface} |\n";
        }
        $md .= "\n";

        $md .= "## Como trabalhar com estes arquivos\n\n";
        $md .= "1. **Formato estruturado** — cada arquivo tem frontmatter YAML + user stories (`US-XXX-NNN`)\n";
        $md .= "   + regras Gherkin (`R-XXX-NNN`) + DoD rastreável com a tela React.\n";
        $md .= "2. **Fonte única da verdade funcional** — `memory/requisitos/<Módulo>/SPEC.md`.\n";
        $md .= "3. **Regerar** — `php artisan module:requirements` gera SPECs faltantes\n";
        $md .= "   sem sobrescrever edições manuais. Use `--force` com cuidado.\n";
        // O item 4 dizia que "Modules/SRS consome esses arquivos e linka com evidências".
        // O módulo foi REMOVIDO em 2026-07-29 (ADR 0357) — manter a frase faria este
        // comando GERAR prosa falsa em cada run. Sucessor do acervo: Modules/KB.
        $md .= "4. **Acervo/busca** — `Modules/KB` (`kb_nodes` + `mcp_memory_documents`).\n\n";

        $md .= "---\n";
        $md .= "_Regerar índice: `php artisan module:requirements --index-only`_\n";

        File::put($dir . DIRECTORY_SEPARATOR . 'INDEX.md', $md);
    }
}
