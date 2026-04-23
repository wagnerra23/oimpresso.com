<?php

namespace Modules\DocVault\Services;

use Illuminate\Support\Facades\File;

/**
 * Lê as 3 fontes de memória/conhecimento do projeto:
 *
 * 1. Primer files      — CLAUDE.md / AGENTS.md na raiz do repo
 * 2. Project memory    — memory/ versionado no git (handoff, ADRs, sessions, etc.)
 * 3. Claude memory     — ~/.claude/projects/{projeto}/memory/ (persistência cross-session)
 *
 * Expõe:
 *   - listRoots()    → estrutura de árvore das 3 fontes
 *   - readFile($key) → conteúdo de um arquivo específico (key = "root::caminho/relativo.md")
 *   - stats()        → contagens por root
 */
class MemoryReader
{
    const ROOT_PRIMER  = 'primer';
    const ROOT_PROJECT = 'project';
    const ROOT_CLAUDE  = 'claude';

    const EXT_ALLOWED = ['md', 'txt', 'json', 'yaml', 'yml'];

    public function listRoots(): array
    {
        return [
            self::ROOT_PRIMER  => $this->readPrimer(),
            self::ROOT_PROJECT => $this->readTree(config('docvault.memory.project_dir'), self::ROOT_PROJECT),
            self::ROOT_CLAUDE  => $this->readTree(config('docvault.memory.claude_dir'), self::ROOT_CLAUDE, true),
        ];
    }

    public function stats(): array
    {
        $roots = $this->listRoots();
        return [
            'primer'  => $this->countLeaves($roots[self::ROOT_PRIMER]),
            'project' => $this->countLeaves($roots[self::ROOT_PROJECT]),
            'claude'  => $this->countLeaves($roots[self::ROOT_CLAUDE]),
        ];
    }

    public function readFile(string $key): ?array
    {
        [$root, $relative] = explode('::', $key, 2) + [null, null];
        if (! $root || ! $relative) return null;

        $baseDir = match ($root) {
            self::ROOT_PROJECT => config('docvault.memory.project_dir'),
            self::ROOT_CLAUDE  => config('docvault.memory.claude_dir'),
            self::ROOT_PRIMER  => base_path(),
            default            => null,
        };
        if (! $baseDir) return null;

        // Segurança: rejeita path traversal
        if (str_contains($relative, '..')) return null;

        $path = $baseDir . DIRECTORY_SEPARATOR . $relative;
        $real = realpath($path);
        $baseReal = realpath($baseDir);
        if (! $real || ! $baseReal || ! str_starts_with($real, $baseReal)) return null;
        if (! is_file($real)) return null;

        return [
            'root'     => $root,
            'relative' => str_replace('\\', '/', $relative),
            'absolute' => $real,
            'content'  => File::get($real),
            'size'     => filesize($real),
            'mtime'    => date('Y-m-d H:i', filemtime($real)),
            'meta'     => $this->parseFrontmatter(File::get($real)),
        ];
    }

    // ------------------------------------------------------------------------

    protected function readPrimer(): array
    {
        $files = (array) config('docvault.memory.primer_files', []);
        $children = [];
        foreach ($files as $path) {
            if (! is_file($path)) continue;
            $name = basename($path);
            $children[] = [
                'type'     => 'file',
                'name'     => $name,
                'key'      => self::ROOT_PRIMER . '::' . $name,
                'size'     => filesize($path),
                'mtime'    => date('Y-m-d H:i', filemtime($path)),
                'preview'  => $this->previewOf($path),
            ];
        }
        return [
            'type'     => 'dir',
            'name'     => 'Primer',
            'path'     => base_path(),
            'exists'   => ! empty($children),
            'children' => $children,
        ];
    }

    protected function readTree(string $baseDir, string $rootKey, bool $flat = false): array
    {
        if (! is_dir($baseDir)) {
            return [
                'type'     => 'dir',
                'name'     => basename($baseDir),
                'path'     => $baseDir,
                'exists'   => false,
                'children' => [],
            ];
        }

        return $this->walkDir($baseDir, $baseDir, $rootKey, $flat);
    }

    protected function walkDir(string $dir, string $base, string $rootKey, bool $flat, int $depth = 0): array
    {
        $children = [];

        if ($flat && $depth > 0) {
            // claude_dir é "flat" — normalmente não tem subpastas que importam; limita profundidade.
            return [
                'type'     => 'dir',
                'name'     => basename($dir),
                'path'     => $dir,
                'exists'   => true,
                'children' => [],
            ];
        }

        $entries = @scandir($dir) ?: [];
        sort($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($full)) {
                $sub = $this->walkDir($full, $base, $rootKey, $flat, $depth + 1);
                if (! empty($sub['children'])) {
                    $children[] = $sub;
                }
            } else {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (! in_array($ext, self::EXT_ALLOWED, true)) continue;

                $relative = ltrim(str_replace($base, '', $full), DIRECTORY_SEPARATOR);
                $children[] = [
                    'type'     => 'file',
                    'name'     => $entry,
                    'key'      => $rootKey . '::' . str_replace('\\', '/', $relative),
                    'size'     => filesize($full),
                    'mtime'    => date('Y-m-d H:i', filemtime($full)),
                    'preview'  => $this->previewOf($full),
                    'meta'     => $this->parseFrontmatter(@file_get_contents($full, false, null, 0, 2048) ?: ''),
                ];
            }
        }

        return [
            'type'     => 'dir',
            'name'     => basename($dir) ?: 'root',
            'path'     => $dir,
            'exists'   => true,
            'children' => $children,
        ];
    }

    protected function previewOf(string $path): string
    {
        $chunk = @file_get_contents($path, false, null, 0, 512);
        if (! $chunk) return '';
        $chunk = $this->stripFrontmatter($chunk);
        $chunk = trim(preg_replace('/^#+\s*/m', '', $chunk));
        return mb_substr($chunk, 0, 140);
    }

    protected function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) return [];
        $out = [];
        foreach (preg_split('/\R/', $m[1]) as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $mm)) {
                $out[$mm[1]] = trim($mm[2]);
            }
        }
        return $out;
    }

    protected function stripFrontmatter(string $content): string
    {
        return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content, 1);
    }

    protected function countLeaves(array $node): int
    {
        if (($node['type'] ?? '') === 'file') return 1;
        $n = 0;
        foreach (($node['children'] ?? []) as $c) {
            $n += $this->countLeaves($c);
        }
        return $n;
    }
}
