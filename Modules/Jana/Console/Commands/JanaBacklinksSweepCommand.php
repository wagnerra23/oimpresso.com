<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Jana\Services\Backlinks\AdrGraphBuilder;

/**
 * jana:backlinks:sweep — Varredura de backlinks ADR↔ADR↔SPEC.
 *
 * Gap G5 (P1) AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md:
 * Obsidian Smart Connections / Roam têm bidirectional links automáticos;
 * oimpresso só tem `related_adrs:` manual unidirecional.
 *
 * Esta varredura detecta 4 sinais de drift do link graph:
 *   1. Orfãs        — ADR aceita sem inbound link (perdida no tempo)
 *   2. Broken       — ref a ADR inexistente (ex: related_adrs:[9999])
 *   3. Assimétricas — supersedes/superseded_by não fecha o par
 *   4. SPEC↔ADR     — SPEC menciona ADR mas ADR não cita o SPEC
 *
 * Outputs:
 *   - memory/decisions/_BACKLINKS-REPORT-YYYY-MM-DD.md  (humano)
 *   - storage/app/jana/backlinks-graph.json             (machine)
 *
 * Exit code:
 *   0 — tudo limpo
 *   1 — broken links detectados (gate CI futuro)
 *
 * Uso:
 *   php artisan jana:backlinks:sweep
 *   php artisan jana:backlinks:sweep --json
 *   php artisan jana:backlinks:sweep --no-report  (só JSON, sem MD)
 *   php artisan jana:backlinks:sweep --fix        (lista o que fixar — não auto-aplica)
 */
class JanaBacklinksSweepCommand extends Command
{
    protected $signature = 'jana:backlinks:sweep
                            {--json : Output JSON em stdout}
                            {--no-report : Não escreve _BACKLINKS-REPORT-*.md}
                            {--fix : Lista ações de correção (não auto-aplica)}';

    protected $description = 'Varredura de backlinks ADR↔SPEC — detecta orfãos/broken/assimétricos (gap G5 auditoria 2026-05-13)';

    public function handle(AdrGraphBuilder $builder): int
    {
        $this->info('Construindo grafo de ADRs...');
        $builder->build();

        $nodes = $builder->nodes();
        $orphans = $builder->findOrphans();
        $broken = $builder->findBrokenLinks();
        $asymmetric = $builder->findAsymmetric();
        $crossRefs = $builder->findSpecCrossRefs();
        $top = $builder->topCentral(5);

        $stats = [
            'total_adrs' => count($nodes),
            'orphans' => count($orphans),
            'broken' => count($broken),
            'asymmetric' => count($asymmetric),
            'spec_cross_refs' => count($crossRefs),
        ];

        // ─── JSON sempre escrito em storage/app/jana ─────────────────────
        $jsonDir = storage_path('app/jana');
        if (! is_dir($jsonDir)) {
            File::makeDirectory($jsonDir, 0755, true);
        }
        $jsonPath = $jsonDir . '/backlinks-graph.json';
        File::put($jsonPath, json_encode($builder->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line("JSON gravado: <fg=cyan>{$jsonPath}</>");

        // ─── Relatório markdown ─────────────────────────────────────────
        if (! $this->option('no-report')) {
            $reportPath = base_path('memory/decisions/_BACKLINKS-REPORT-' . now()->format('Y-m-d') . '.md');
            File::put($reportPath, $this->renderMarkdown($stats, $orphans, $broken, $asymmetric, $crossRefs, $top, $builder));
            $this->line("Relatório: <fg=cyan>{$reportPath}</>");
        }

        // ─── Stdout summary ─────────────────────────────────────────────
        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => count($broken) === 0,
                'stats' => $stats,
                'orphans' => array_values($orphans),
                'broken' => $broken,
                'asymmetric' => $asymmetric,
                'top_central' => $top,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderSummary($stats, $top);
            if ($this->option('fix')) {
                $this->renderFixHints($orphans, $broken, $asymmetric);
            }
        }

        // Exit code: 1 só se broken (sinal forte). Orfãs/assimétricas são warning.
        return count($broken) === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function renderSummary(array $stats, array $top): void
    {
        $this->newLine();
        $this->info('━━━ Resumo varredura backlinks ━━━');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de ADRs',          $stats['total_adrs']],
                ['Orfãs (sem inbound)',     $stats['orphans']],
                ['Broken links',            $stats['broken']],
                ['Assimétricas',            $stats['asymmetric']],
                ['SPEC↔ADR não-fechados',   $stats['spec_cross_refs']],
            ]
        );

        if ($top !== []) {
            $this->newLine();
            $this->info('Top 5 ADRs mais centrais (inbound):');
            $this->table(
                ['#', 'Título', 'Inbound'],
                array_map(fn ($r) => [$r['number'], substr($r['title'], 0, 60), $r['inbound_count']], $top)
            );
        }
    }

    protected function renderFixHints(array $orphans, array $broken, array $asymmetric): void
    {
        $this->newLine();
        $this->warn('━━━ Ações sugeridas (--fix) ━━━');

        if ($broken !== []) {
            $this->warn('Broken links — refs a ADRs inexistentes:');
            foreach (array_slice($broken, 0, 10) as $b) {
                $this->line("  ADR {$b['from']} → ADR {$b['to']} ({$b['type']}) — remover ou corrigir número");
            }
        }

        if ($asymmetric !== []) {
            $this->warn('Assimétricas — fechar par:');
            foreach (array_slice($asymmetric, 0, 10) as $a) {
                $this->line("  ADR {$a['from']} diz {$a['type']}:[{$a['to']}] — adicionar {$a['expected_reverse']}:[{$a['from']}] em ADR {$a['to']}");
            }
        }

        if ($orphans !== []) {
            $orphanList = array_slice(array_keys($orphans), 0, 10);
            $this->warn('Orfãs (top 10) — considerar adicionar inbound link de ADR mãe relacionada:');
            foreach ($orphanList as $num) {
                $this->line("  ADR {$num}: " . substr($orphans[$num]['title'], 0, 70));
            }
        }
    }

    protected function renderMarkdown(
        array $stats,
        array $orphans,
        array $broken,
        array $asymmetric,
        array $crossRefs,
        array $top,
        AdrGraphBuilder $builder,
    ): string {
        $today = now()->format('Y-m-d');
        $md = "# Relatório de backlinks ADR — {$today}\n\n";
        $md .= "> Gerado por `php artisan jana:backlinks:sweep` (Gap G5 P1 — auditoria 2026-05-13).\n";
        $md .= "> Frequência sugerida: daily 06:30 BRT via cron (após `jana:health-check`).\n\n";

        $md .= "## Sumário\n\n";
        $md .= "| Métrica | Valor |\n|---|---|\n";
        $md .= "| Total de ADRs | {$stats['total_adrs']} |\n";
        $md .= "| Orfãs (sem inbound link) | {$stats['orphans']} |\n";
        $md .= "| Broken links | {$stats['broken']} |\n";
        $md .= "| Assimétricas | {$stats['asymmetric']} |\n";
        $md .= "| SPEC↔ADR cross-refs não-fechados | {$stats['spec_cross_refs']} |\n\n";

        // Top central
        $md .= "## Top 5 ADRs mais centrais (inbound)\n\n";
        $md .= "| # | Título | Inbound |\n|---|---|---|\n";
        foreach ($top as $r) {
            $title = str_replace('|', '\\|', $r['title']);
            $md .= "| {$r['number']} | {$title} | {$r['inbound_count']} |\n";
        }
        $md .= "\n";

        // Broken
        $md .= "## Broken links (" . count($broken) . ")\n\n";
        if ($broken === []) {
            $md .= "_Nenhum broken link detectado._\n\n";
        } else {
            $md .= "| De | Para | Tipo | Título origem |\n|---|---|---|---|\n";
            foreach ($broken as $b) {
                $title = str_replace('|', '\\|', $b['from_title']);
                $md .= "| {$b['from']} | {$b['to']} | {$b['type']} | {$title} |\n";
            }
            $md .= "\n";
        }

        // Assimétricas
        $md .= "## Assimétricas (" . count($asymmetric) . ")\n\n";
        if ($asymmetric === []) {
            $md .= "_Todos os pares supersedes/superseded_by estão fechados._\n\n";
        } else {
            $md .= "| De | Para | Tipo | Falta inverso em |\n|---|---|---|---|\n";
            foreach (array_slice($asymmetric, 0, 50) as $a) {
                $md .= "| {$a['from']} | {$a['to']} | {$a['type']} | ADR {$a['missing_in']} (faltando `{$a['expected_reverse']}:[{$a['from']}]`) |\n";
            }
            if (count($asymmetric) > 50) {
                $md .= "\n_Mostrando 50 de " . count($asymmetric) . "._\n";
            }
            $md .= "\n";
        }

        // Orfãs
        $md .= "## Orfãs aceitas sem inbound (" . count($orphans) . ")\n\n";
        if ($orphans === []) {
            $md .= "_Nenhuma ADR aceita orfã._\n\n";
        } else {
            $md .= "| # | Slug | Título |\n|---|---|---|\n";
            foreach ($orphans as $num => $node) {
                $title = str_replace('|', '\\|', $node['title']);
                $slug = str_replace('|', '\\|', $node['slug']);
                $md .= "| {$num} | {$slug} | {$title} |\n";
            }
            $md .= "\n";
        }

        // SPEC cross-refs
        $md .= "## SPEC↔ADR cross-refs não-fechados (" . count($crossRefs) . ")\n\n";
        if ($crossRefs === []) {
            $md .= "_Todos SPECs que mencionam ADR são também citados pela respectiva ADR._\n\n";
        } else {
            $md .= "_SPEC menciona ADR mas ADR não cita o SPEC (heurística leve — revisão humana):_\n\n";
            $md .= "| SPEC | ADR | Título ADR |\n|---|---|---|\n";
            foreach (array_slice($crossRefs, 0, 100) as $r) {
                $title = str_replace('|', '\\|', $r['adr_title']);
                $md .= "| {$r['spec']} | {$r['adr']} | {$title} |\n";
            }
            if (count($crossRefs) > 100) {
                $md .= "\n_Mostrando 100 de " . count($crossRefs) . "._\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";
        $md .= "**Próximos passos:**\n\n";
        $md .= "1. Corrigir broken links (CI gate futuro) — remover refs ou criar ADR faltante\n";
        $md .= "2. Fechar pares assimétricos — adicionar `superseded_by:` reverso\n";
        $md .= "3. Revisar orfãs — adicionar link de ADR mãe relacionada se relevante\n";
        $md .= "4. Decidir cross-refs SPEC↔ADR caso a caso\n\n";
        $md .= "_Append-only — esta varredura NÃO modifica ADRs. Auto-fix recusado por design (`--fix` apenas lista)._\n";

        return $md;
    }
}
