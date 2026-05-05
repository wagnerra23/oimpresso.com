<?php

namespace Modules\ADS\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * MVP read-only — lê .claude/skills/<slug>/SKILL.md direto do filesystem.
 *
 * Não usa DB. Quando ADR 0076 entregar mcp_skills via Sprint A do CYCLE-02,
 * este service vira fallback ou é substituído por leitura DB.
 *
 * @see memory/decisions/0076-skills-db-primary-git-destino-drift-alert.md
 */
class SkillsService
{
    /**
     * Lista todas as skills disponíveis em .claude/skills/.
     *
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   description: string,
     *   module: ?string,
     *   git_path: string,
     *   body_chars: int,
     * }>
     */
    public function listAll(): array
    {
        $skills = [];
        foreach ($this->skillFiles() as $file) {
            $parsed = $this->parseFile($file);
            if ($parsed === null) {
                continue;
            }
            $skills[] = [
                'slug'        => $parsed['slug'],
                'name'        => $parsed['frontmatter']['name'] ?? $parsed['slug'],
                'description' => $parsed['frontmatter']['description'] ?? '',
                'module'      => $this->extractModule($parsed['frontmatter']),
                'git_path'    => $parsed['git_path'],
                'body_chars'  => mb_strlen($parsed['body']),
            ];
        }

        usort($skills, fn ($a, $b) => strcmp($a['slug'], $b['slug']));

        return $skills;
    }

    /**
     * Busca uma skill específica por slug. Retorna null se não existir.
     *
     * @return array{
     *   slug: string,
     *   frontmatter: array,
     *   body: string,
     *   git_path: string,
     * }|null
     */
    public function findBySlug(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            return null;
        }

        $base = base_path('.claude/skills/'.$slug.'/SKILL.md');
        if (! is_file($base)) {
            return null;
        }

        return $this->parseFile($base);
    }

    /**
     * @return iterable<string>
     */
    private function skillFiles(): iterable
    {
        $base = base_path('.claude/skills');
        if (! is_dir($base)) {
            return [];
        }

        $matches = glob("$base/*/SKILL.md") ?: [];

        return $matches;
    }

    /**
     * @return array{
     *   slug: string,
     *   frontmatter: array,
     *   body: string,
     *   git_path: string,
     * }|null
     */
    private function parseFile(string $absolutePath): ?array
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }

        $slug = basename(dirname($absolutePath));
        $relativePath = str_replace('\\', '/', str_replace(base_path(), '', $absolutePath));
        $relativePath = ltrim($relativePath, '/');

        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $m)) {
            return [
                'slug'        => $slug,
                'frontmatter' => [],
                'body'        => $content,
                'git_path'    => $relativePath,
            ];
        }

        try {
            $frontmatter = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            $frontmatter = [];
        }

        return [
            'slug'        => $slug,
            'frontmatter' => is_array($frontmatter) ? $frontmatter : [],
            'body'        => $m[2],
            'git_path'    => $relativePath,
        ];
    }

    /**
     * Tenta inferir módulo do frontmatter (campo `module`, ou tags, ou null).
     */
    private function extractModule(array $frontmatter): ?string
    {
        if (! empty($frontmatter['module'])) {
            return (string) $frontmatter['module'];
        }

        return null;
    }
}
