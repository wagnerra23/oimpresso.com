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
     * Lista todos os módulos. Considera tanto arquivos `{Nome}.md` (formato plano antigo)
     * quanto pastas `{Nome}/` contendo SPEC.md + README.md + ARCHITECTURE.md + CHANGELOG.md
     * (formato novo). Pasta tem prioridade: se `{Nome}/` existir, ignora `{Nome}.md`.
     */
    public function listModules(): array
    {
        $dir = $this->dir();
        if (! File::isDirectory($dir)) return [];

        $seen = [];
        $out = [];

        // Formato novo: pastas com SPEC.md
        foreach (File::directories($dir) as $d) {
            $name = basename($d);
            if (in_array($name, ['INDEX', 'RECOMENDACOES'], true)) continue;
            $spec = $d . DIRECTORY_SEPARATOR . 'SPEC.md';
            if (! File::exists($spec)) continue;

            $readme = $d . DIRECTORY_SEPARATOR . 'README.md';
            $fmSource = File::exists($readme) ? $readme : $spec;
            $meta = $this->readMeta($spec, $fmSource);
            $out[] = array_merge(['name' => $name, 'format' => 'folder'], $meta);
            $seen[$name] = true;
        }

        // Formato antigo: arquivos {Nome}.md (só quem não tem pasta)
        foreach (File::files($dir) as $f) {
            if ($f->getExtension() !== 'md') continue;
            $name = $f->getFilenameWithoutExtension();
            if (in_array($name, ['INDEX', 'RECOMENDACOES'], true)) continue;
            if (isset($seen[$name])) continue;

            $meta = $this->readMeta($f->getPathname());
            $out[] = array_merge(['name' => $name, 'format' => 'flat'], $meta);
        }

        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $out;
    }

    /**
     * Lê um módulo específico e devolve shape completo pra UI. Tenta pasta primeiro
     * (formato novo: SPEC/README/ARCHITECTURE/CHANGELOG.md), fallback pro arquivo plano.
     */
    public function readModule(string $name): ?array
    {
        $dir = $this->dir();
        $folder = $dir . DIRECTORY_SEPARATOR . $name;

        if (File::isDirectory($folder) && File::exists($folder . DIRECTORY_SEPARATOR . 'SPEC.md')) {
            return $this->readModuleFolder($name, $folder);
        }

        $flatPath = $dir . DIRECTORY_SEPARATOR . $name . '.md';
        if (! File::exists($flatPath)) return null;

        $content = File::get($flatPath);
        $meta = $this->parseFrontmatter($content);
        $body = $this->stripFrontmatter($content);

        return [
            'name'         => $name,
            'format'       => 'flat',
            'path'         => $flatPath,
            'frontmatter'  => $meta,
            'stories'      => $this->extractStories($body),
            'rules'        => $this->extractRules($body),
            'raw'          => $body,
            'readme'       => null,
            'architecture' => null,
            'changelog'    => null,
            'size_bytes'   => strlen($content),
            'mtime'        => File::lastModified($flatPath),
        ];
    }

    protected function readModuleFolder(string $name, string $folder): array
    {
        $spec    = File::get($folder . DIRECTORY_SEPARATOR . 'SPEC.md');
        $readme  = File::exists($folder . DIRECTORY_SEPARATOR . 'README.md')
            ? File::get($folder . DIRECTORY_SEPARATOR . 'README.md') : null;
        $arch    = File::exists($folder . DIRECTORY_SEPARATOR . 'ARCHITECTURE.md')
            ? File::get($folder . DIRECTORY_SEPARATOR . 'ARCHITECTURE.md') : null;
        $change  = File::exists($folder . DIRECTORY_SEPARATOR . 'CHANGELOG.md')
            ? File::get($folder . DIRECTORY_SEPARATOR . 'CHANGELOG.md') : null;
        $adrs    = $this->readAdrs($folder . DIRECTORY_SEPARATOR . 'adr');

        // Frontmatter vem do README se existir (fonte oficial do módulo); senão do SPEC.
        $fmContent = $readme ?? $spec;
        $fm = $this->parseFrontmatter($fmContent);

        $specBody = $this->stripFrontmatter($spec);
        $totalBytes = strlen($spec)
            + ($readme ? strlen($readme) : 0)
            + ($arch ? strlen($arch) : 0)
            + ($change ? strlen($change) : 0)
            + array_sum(array_map(fn ($a) => strlen($a['raw']), $adrs));

        return [
            'name'         => $name,
            'format'       => 'folder',
            'path'         => $folder,
            'frontmatter'  => $fm,
            'stories'      => $this->extractStories($specBody),
            'rules'        => $this->extractRules($specBody),
            'raw'          => $specBody,
            'readme'       => $readme ? $this->stripFrontmatter($readme) : null,
            'architecture' => $arch,
            'changelog'    => $change,
            'adrs'         => $adrs,
            'size_bytes'   => $totalBytes,
            'mtime'        => File::lastModified($folder . DIRECTORY_SEPARATOR . 'SPEC.md'),
        ];
    }

    /**
     * Lê ADRs em `adr/NNNN-slug.md`. Extrai título (primeiro H1) e status do bloco de lista.
     */
    protected function readAdrs(string $adrDir): array
    {
        if (! File::isDirectory($adrDir)) return [];

        $out = [];
        foreach (File::files($adrDir) as $f) {
            if ($f->getExtension() !== 'md') continue;
            $content = File::get($f->getPathname());

            $title = '';
            if (preg_match('/^#\s+(.+?)$/m', $content, $m)) {
                $title = trim($m[1]);
            }

            $status = 'unknown';
            if (preg_match('/\*\*Status\*\*:\s*([a-z][a-z\-]*)/i', $content, $m)) {
                $status = strtolower(trim($m[1]));
            }

            $date = null;
            if (preg_match('/\*\*Data\*\*:\s*(\d{4}-\d{2}-\d{2})/', $content, $m)) {
                $date = $m[1];
            }

            // Número vem do nome do arquivo: 0001-slug.md → 0001
            $filename = $f->getFilenameWithoutExtension();
            $number = preg_match('/^(\d{4})/', $filename, $nm) ? $nm[1] : '0000';

            $out[] = [
                'number'  => $number,
                'slug'    => $filename,
                'title'   => $title,
                'status'  => $status,
                'date'    => $date,
                'raw'     => $content,
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['number'], $b['number']));
        return $out;
    }

    // ------------------------------------------------------------------------

    protected function readMeta(string $path, ?string $frontmatterSource = null): array
    {
        $content = File::get($path);
        $fmContent = $frontmatterSource && File::exists($frontmatterSource) ? File::get($frontmatterSource) : $content;
        $meta = $this->parseFrontmatter($fmContent);
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
        if (! preg_match_all('/###\s+(US-[A-Z]+-\d+)\s+·\s+(.+?)\n(.*?)(?=\n###\s+(?:US|R)-|\n##\s+|\z)/s', $body, $matches, PREG_SET_ORDER)) {
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
        if (! preg_match_all('/###\s+(R-[A-Z]+-\d+)\s+·\s+(.+?)\n(.*?)(?=\n###\s+(?:US|R)-|\n##\s+|\z)/s', $body, $matches, PREG_SET_ORDER)) {
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
