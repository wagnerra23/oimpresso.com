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
            'page_id' => $this->resolvePageId($module, $tela, $sources['charter']['content'] ?? null),
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
        // tela aninhada (ex: "CaixaUnificada/Index") → slug sem `/` pra não virar
        // subpasta em RUNBOOK-/decisoes/visual-comparison. charter+casos usam o path real.
        $telaSlug = str_replace('/', '-', mb_strtolower($tela));
        $rel = [
            'charter' => "resources/js/Pages/{$module}/{$tela}.charter.md",
            'casos' => "resources/js/Pages/{$module}/{$tela}.casos.md",
            'runbook' => "memory/requisitos/{$module}/RUNBOOK-{$telaSlug}.md",
            'briefing' => "memory/requisitos/{$module}/BRIEFING.md",
        ];
        $sources = [];
        foreach ($rel as $key => $path) {
            $sources[$key] = ['path' => $path, 'content' => $this->read($path)];
        }
        // decisoes do protótipo: a pasta vem do cowork-map (Sells → vendas), não do nome
        // da tela (Index ≠ vendas). Fallback ao slug + ao módulo se o map não casar.
        $decisoes = [];
        foreach ($this->prototypeDirsForModule($module) as $dir) {
            $decisoes[] = "prototipo-ui/prototipos/{$dir}/decisoes.md";
        }
        $decisoes[] = "prototipo-ui/prototipos/{$telaSlug}/decisoes.md";
        $decisoes[] = 'prototipo-ui/prototipos/' . mb_strtolower($module) . '/decisoes.md';
        $sources['decisoes'] = $this->firstGlob($decisoes);

        $sources['visual_comparison'] = $this->firstGlob([
            "memory/requisitos/{$module}/*{$telaSlug}*visual-comparison.md", // prioriza a tela
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

    /**
     * page_id na ordem de verdade: `page_id:` do charter → `page:` do charter (slug) →
     * page_id do cowork-map (entrada do módulo) → slug normalizado SEM `/` (último caso).
     */
    private function resolvePageId(string $module, string $tela, ?string $charter): string
    {
        return $this->pageIdFromCharter($charter)
            ?? $this->pageFromCharter($charter)
            ?? $this->pageIdFromMap($module)
            ?? str_replace('/', '-', mb_strtolower("{$module}-{$tela}"));
    }

    /** Lê o `page_id:` real do frontmatter do charter (não fabrica). */
    private function pageIdFromCharter(?string $charter): ?string
    {
        if ($charter === null) {
            return null;
        }

        return preg_match('/^page_id:\s*(.+?)\s*$/m', $charter, $m) ? trim($m[1]) : null;
    }

    /** Deriva page_id do `page:` do charter (ex: `/atendimento/caixa-unificada` → `atendimento-caixa-unificada`). */
    private function pageFromCharter(?string $charter): ?string
    {
        if ($charter === null || ! preg_match('/^page:\s*(.+?)\s*$/m', $charter, $m)) {
            return null;
        }
        $slug = trim(str_replace('/', '-', mb_strtolower(trim($m[1]))), '-');

        return $slug !== '' ? $slug : null;
    }

    /** page_id da 1ª entrada do cowork-map cujo `module` casa (best-effort quando 1 tela/módulo). */
    private function pageIdFromMap(string $module): ?string
    {
        foreach ($this->coworkScreens() as $screen) {
            if (mb_strtolower((string) ($screen['module'] ?? '')) === mb_strtolower($module)) {
                $pid = trim((string) ($screen['page_id'] ?? ''));
                if ($pid !== '') {
                    return $pid;
                }
            }
        }

        return null;
    }

    /**
     * Chaves do cowork-map cujo `module` casa = pasta(s) de protótipo do módulo (Sells → vendas).
     *
     * @return list<string>
     */
    private function prototypeDirsForModule(string $module): array
    {
        $dirs = [];
        foreach ($this->coworkScreens() as $key => $screen) {
            if (mb_strtolower((string) ($screen['module'] ?? '')) === mb_strtolower($module)) {
                $dirs[] = (string) $key;
            }
        }

        return $dirs;
    }

    /** @return array<string, array<string, mixed>> screens do cowork-map (keyed por tela). */
    private function coworkScreens(): array
    {
        $path = $this->root() . '/prototipo-ui/cowork-map.json';
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? (array) ($decoded['screens'] ?? []) : [];
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
