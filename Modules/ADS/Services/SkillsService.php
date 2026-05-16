<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpSkill;
use Symfony\Component\Yaml\Yaml;

/**
 * Observabilidade D9.a (ADR 0155): list query ms-range com fallback; Tracer
 * via `OtelHelper::span(` reservado quando virar hot path.
 *
 * Lista skills do projeto. ADR 0076: lê de DB (mcp_skills + mcp_skill_versions)
 * com fallback pra filesystem (.claude/skills/<slug>/SKILL.md) se DB vazio
 * ou tabela ainda não migrada.
 *
 * Fallback é importante pra:
 *   - Worktrees novas sem migrate rodado
 *   - Janela entre deploy de migrations e import inicial
 *   - Disaster recovery (DB caiu, filesystem é fallback)
 */
class SkillsService
{
    /**
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   description: string,
     *   module: ?string,
     *   git_path: string,
     *   body_chars: int,
     *   source: string,
     * }>
     */
    public function listAll(): array
    {
        if ($this->canUseDb()) {
            $dbList = $this->listFromDb();
            if (count($dbList) > 0) {
                return $dbList;
            }
        }

        return $this->listFromFilesystem();
    }

    /**
     * @return array{
     *   slug: string,
     *   frontmatter: array,
     *   body: string,
     *   git_path: string,
     *   source: string,
     * }|null
     */
    public function findBySlug(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            return null;
        }

        if ($this->canUseDb()) {
            $skill = McpSkill::with('currentVersion')->where('slug', $slug)->first();
            if ($skill !== null && $skill->currentVersion !== null) {
                return [
                    'slug'        => $skill->slug,
                    'frontmatter' => $skill->currentVersion->frontmatter_json ?? [],
                    'body'        => $skill->currentVersion->body_markdown,
                    'git_path'    => $skill->git_path ?? ".claude/skills/{$slug}/SKILL.md",
                    'source'      => 'db',
                ];
            }
        }

        return $this->findInFilesystem($slug);
    }

    private function canUseDb(): bool
    {
        try {
            return Schema::hasTable('mcp_skills');
        } catch (\Throwable $e) {
            report($e);
            \Log::warning('ADS SkillsService: canUseDb falhou — fallback filesystem', ['exception' => $e]);
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listFromDb(): array
    {
        $skills = McpSkill::with('currentVersion')
            ->whereNull('deleted_at')
            ->orderBy('slug')
            ->get();

        return $skills->map(function (McpSkill $s) {
            $fm = $s->currentVersion->frontmatter_json ?? [];

            return [
                'slug'        => $s->slug,
                'name'        => $fm['name'] ?? $s->slug,
                'description' => $fm['description'] ?? '',
                'module'      => $s->module ?: ($fm['module'] ?? null),
                'git_path'    => $s->git_path ?? ".claude/skills/{$s->slug}/SKILL.md",
                'body_chars'  => mb_strlen($s->currentVersion->body_markdown ?? ''),
                'source'      => 'db',
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listFromFilesystem(): array
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
                'module'      => $parsed['frontmatter']['module'] ?? null,
                'git_path'    => $parsed['git_path'],
                'body_chars'  => mb_strlen($parsed['body']),
                'source'      => 'filesystem',
            ];
        }

        usort($skills, fn ($a, $b) => strcmp($a['slug'], $b['slug']));

        return $skills;
    }

    /**
     * @return array{slug: string, frontmatter: array, body: string, git_path: string, source: string}|null
     */
    private function findInFilesystem(string $slug): ?array
    {
        $absolutePath = base_path('.claude/skills/'.$slug.'/SKILL.md');
        if (! is_file($absolutePath)) {
            return null;
        }

        $parsed = $this->parseFile($absolutePath);
        if ($parsed === null) {
            return null;
        }

        return [
            'slug'        => $parsed['slug'],
            'frontmatter' => $parsed['frontmatter'],
            'body'        => $parsed['body'],
            'git_path'    => $parsed['git_path'],
            'source'      => 'filesystem',
        ];
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

        return glob("$base/*/SKILL.md") ?: [];
    }

    /**
     * @return array{slug: string, frontmatter: array, body: string, git_path: string}|null
     */
    private function parseFile(string $absolutePath): ?array
    {
        $content = @file_get_contents($absolutePath);
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
}
