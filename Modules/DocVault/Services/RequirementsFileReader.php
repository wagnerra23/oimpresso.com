<?php

namespace Modules\DocVault\Services;

use Illuminate\Support\Facades\File;

/**
 * Lê arquivos markdown em `memory/requisitos/` e extrai:
 *   - frontmatter YAML
 *   - user stories (IDs e DoD completude)
 *   - regras Gherkin (IDs)
 *
 * Usado pelo DocVault pra mostrar overview por módulo sem precisar
 * parsear MD via regex em cada request — pode ser cacheado / sincronizado
 * com a tabela docs_requirements (Fase 3).
 */
class RequirementsFileReader
{
    public function dir(): string
    {
        return (string) config('docvault.requirements_dir', base_path('memory/requisitos'));
    }

    /**
     * Lista todos os módulos com arquivo .md.
     * Retorna array de metadata (do frontmatter + stats).
     */
    public function listModules(): array
    {
        $dir = $this->dir();
        if (! File::isDirectory($dir)) return [];

        $out = [];
        foreach (File::files($dir) as $f) {
            $name = $f->getFilenameWithoutExtension();
            if (in_array($name, ['INDEX', 'RECOMENDACOES'], true)) continue;

            $meta = $this->readMeta($f->getPathname());
            $out[] = array_merge(['name' => $name], $meta);
        }

        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $out;
    }

    /**
     * Lê um módulo específico e devolve shape completo pra UI.
     */
    public function readModule(string $name): ?array
    {
        $path = $this->dir() . DIRECTORY_SEPARATOR . $name . '.md';
        if (! File::exists($path)) return null;

        $content = File::get($path);
        $meta = $this->parseFrontmatter($content);
        $body = $this->stripFrontmatter($content);

        return [
            'name'       => $name,
            'path'       => $path,
            'frontmatter' => $meta,
            'stories'    => $this->extractStories($body),
            'rules'      => $this->extractRules($body),
            'raw'        => $body,
            'size_bytes' => strlen($content),
            'mtime'      => File::lastModified($path),
        ];
    }

    // ------------------------------------------------------------------------

    protected function readMeta(string $path): array
    {
        $content = File::get($path);
        $meta = $this->parseFrontmatter($content);
        $body = $this->stripFrontmatter($content);

        $stories = $this->extractStories($body);
        $rules = $this->extractRules($body);

        $dodTotal = 0;
        $dodDone = 0;
        foreach ($stories as $s) {
            $dodTotal += $s['dod_total'];
            $dodDone += $s['dod_done'];
        }

        return [
            'frontmatter' => $meta,
            'stories_count' => count($stories),
            'rules_count'   => count($rules),
            'dod_total'     => $dodTotal,
            'dod_done'      => $dodDone,
            'dod_pct'       => $dodTotal > 0 ? round($dodDone / $dodTotal * 100) : 0,
            'size_bytes'    => strlen($content),
            'mtime'         => File::lastModified($path),
        ];
    }

    protected function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) {
            return [];
        }
        $yaml = $m[1];
        $out = [];
        foreach (preg_split('/\R/', $yaml) as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $mm)) {
                $key = $mm[1];
                $val = trim($mm[2]);
                // Strip list brackets
                if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                    $items = array_map('trim', explode(',', trim($val, '[]')));
                    $out[$key] = array_values(array_filter($items));
                } else {
                    $out[$key] = $val;
                }
            }
        }
        return $out;
    }

    protected function stripFrontmatter(string $content): string
    {
        return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content, 1);
    }

    /**
     * Encontra headings tipo "### US-XXXX-NNN · Título" + conta DoD.
     */
    protected function extractStories(string $body): array
    {
        $stories = [];
        // Divide por seção de user story
        if (! preg_match_all('/###\s+(US-[A-Z]+-\d+)\s+·\s+(.+?)\n(.*?)(?=\n###\s+[UR]-|\n##\s+|\z)/s', $body, $matches, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($matches as $m) {
            $id = $m[1];
            $title = trim($m[2]);
            $section = $m[3];

            // Conta DoD: linhas [ ] vs [x]
            $total = 0;
            $done = 0;
            foreach (preg_split('/\R/', $section) as $line) {
                if (preg_match('/^-\s*\[([ xX])\]/', trim($line), $mm)) {
                    $total++;
                    if (in_array($mm[1], ['x', 'X'], true)) $done++;
                }
            }

            // Implementado em?
            $implementado = null;
            if (preg_match('/\*\*Implementado em:\*\*\s*(?:`([^`]+)`|\[TODO[^\]]*\]|_([^_]+)_)/', $section, $impl)) {
                $implementado = $impl[1] ?? $impl[2] ?? null;
                if ($implementado && str_contains($implementado, 'TODO')) $implementado = null;
            }

            $stories[] = [
                'id'            => $id,
                'title'         => $title,
                'dod_total'     => $total,
                'dod_done'      => $done,
                'implementado_em' => $implementado,
            ];
        }
        return $stories;
    }

    /**
     * Encontra headings tipo "### R-XXXX-NNN · Título".
     */
    protected function extractRules(string $body): array
    {
        $rules = [];
        if (! preg_match_all('/###\s+(R-[A-Z]+-\d+)\s+·\s+(.+?)\n(.*?)(?=\n###\s+[UR]-|\n##\s+|\z)/s', $body, $matches, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($matches as $m) {
            $id = $m[1];
            $title = trim($m[2]);
            $section = $m[3];

            $testado = null;
            if (preg_match('/\*\*Testado em:\*\*\s*(?:`([^`]+)`|\[TODO[^\]]*\]|_([^_]+)_)/', $section, $t)) {
                $testado = $t[1] ?? $t[2] ?? null;
                if ($testado && str_contains($testado, 'TODO')) $testado = null;
            }

            $rules[] = [
                'id'         => $id,
                'title'      => $title,
                'testado_em' => $testado,
            ];
        }
        return $rules;
    }
}
