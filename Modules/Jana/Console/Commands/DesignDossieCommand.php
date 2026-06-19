<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\Memoria\DesignDossieAssembler;

/**
 * PR-1 da estação de ingestão de design ([plano] vectorized-badger · ADR 0270 D-2).
 *
 * `design:dossie --module=Sells --tela=Index` → MONTA o dossiê da tela lendo as fontes
 * curadas que JÁ existem (charter+casos+decisoes+RUNBOOK+visual-comparison+briefing+
 * persona+feedback) e imprime/escreve a READ-VIEW. É o contexto que o handoff padrão
 * não carrega e a IA deve ler ANTES de aplicar um zip de design.
 *
 * Read-only sobre a memória: NÃO escreve canon, NÃO chama LLM, NÃO aplica nada.
 * Determinístico (o corpo do dossiê não carrega timestamp). Raiz configurável
 * (`jana.dossie_root`) pra testar com fixtures sem tocar o repo real.
 */
class DesignDossieCommand extends Command
{
    protected $signature = 'design:dossie
                            {--module= : Módulo (ex: Sells)}
                            {--tela= : Tela/arquivo (ex: Index)}
                            {--out= : Caminho de saída (default: stdout)}';

    protected $description = 'Monta o dossiê de decisões de uma tela (read-view do curado existente) — contexto pra IA antes de aplicar design';

    public function handle(): int
    {
        $module = (string) $this->option('module');
        $tela = (string) $this->option('tela');
        if ($module === '' || $tela === '') {
            $this->error('Use --module=<Mod> --tela=<Tela>.');

            return self::FAILURE;
        }

        $sources = $this->resolveSources($module, $tela);
        $dossie = DesignDossieAssembler::assemble([
            'tela' => $tela,
            'module' => $module,
            // page_id vem do charter (verdade); fallback ao slug derivado se ausente
            'page_id' => $this->pageIdFromCharter($sources['charter']['content'] ?? null)
                ?? mb_strtolower("{$module}-{$tela}"),
            'sources' => $sources,
            'personas' => $this->resolvePersonas($module),
            'feedback' => $this->resolveFeedback($module),
            'padroes' => [],
        ]);

        $out = (string) $this->option('out');
        if ($out !== '' && $out !== '-') {
            @mkdir(dirname($out), 0o775, true);
            file_put_contents($out, $dossie);
            $this->info("Dossiê escrito em {$out} (read-view efêmera, não-canon).");
        } else {
            $this->line($dossie);
        }

        return self::SUCCESS;
    }

    private function root(): string
    {
        return rtrim((string) config('jana.dossie_root', base_path()), '/\\');
    }

    /** @return array<string, array{path:string, content:?string}> */
    private function resolveSources(string $module, string $tela): array
    {
        $telaLower = mb_strtolower($tela);
        $rel = [
            'charter' => "resources/js/Pages/{$module}/{$tela}.charter.md",
            'casos' => "resources/js/Pages/{$module}/{$tela}.casos.md",
            'runbook' => "memory/requisitos/{$module}/RUNBOOK-{$telaLower}.md",
            'briefing' => "memory/requisitos/{$module}/BRIEFING.md",
        ];
        $sources = [];
        foreach ($rel as $key => $path) {
            $sources[$key] = ['path' => $path, 'content' => $this->read($path)];
        }
        // best-effort por glob (nomes variam): decisoes do protótipo + visual-comparison
        $sources['decisoes'] = $this->firstGlob([
            "prototipo-ui/prototipos/{$telaLower}/decisoes.md",
            "prototipo-ui/prototipos/" . mb_strtolower($module) . "/decisoes.md",
        ]);
        $sources['visual_comparison'] = $this->firstGlob([
            "memory/requisitos/{$module}/*{$telaLower}*visual-comparison.md", // prioriza a tela
            "memory/requisitos/{$module}/*visual-comparison.md",
        ]);

        return $sources;
    }

    /** @return array{primary:?string, secondary:array<int,string>} */
    private function resolvePersonas(string $module): array
    {
        $path = $this->root() . '/memory/requisitos/_DesignSystem/personas-por-modulo.yml';
        if (! is_file($path)) {
            return ['primary' => null, 'secondary' => []];
        }
        try {
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($path);
        } catch (\Throwable) {
            return ['primary' => null, 'secondary' => []];
        }
        $node = $parsed[mb_strtolower($module)] ?? null;
        if (! is_array($node)) {
            return ['primary' => null, 'secondary' => []];
        }

        return [
            'primary' => is_string($node['primary'] ?? null) ? $node['primary'] : null,
            'secondary' => array_values(array_filter((array) ($node['secondary'] ?? []), 'is_string')),
        ];
    }

    /** @return array<int, array{path:string, content:?string}> */
    private function resolveFeedback(string $module, int $max = 5): array
    {
        $needle = mb_strtolower($module);
        $found = [];
        foreach (glob($this->root() . '/memory/reference/feedback-*.md') ?: [] as $abs) {
            $name = basename($abs);
            $content = (string) @file_get_contents($abs);
            if (str_contains(mb_strtolower($name), $needle) || str_contains(mb_strtolower($content), "modules/{$needle}")) {
                $found[] = ['path' => 'memory/reference/' . $name, 'content' => mb_substr($content, 0, 600)];
            }
            if (count($found) >= $max) {
                break;
            }
        }

        return $found;
    }

    /** Lê o `page_id:` real do frontmatter do charter (não fabrica). */
    private function pageIdFromCharter(?string $charter): ?string
    {
        if ($charter === null) {
            return null;
        }

        return preg_match('/^page_id:\s*(.+?)\s*$/m', $charter, $m) ? trim($m[1]) : null;
    }

    private function read(string $rel): ?string
    {
        $abs = $this->root() . '/' . $rel;

        return is_file($abs) ? (string) file_get_contents($abs) : null;
    }

    /** @param array<int,string> $rels @return array{path:string, content:?string} */
    private function firstGlob(array $rels): array
    {
        foreach ($rels as $rel) {
            foreach (glob($this->root() . '/' . $rel) ?: [] as $abs) {
                return ['path' => ltrim(str_replace($this->root(), '', $abs), '/\\'), 'content' => (string) file_get_contents($abs)];
            }
        }

        return ['path' => $rels[0] ?? '?', 'content' => null];
    }
}
