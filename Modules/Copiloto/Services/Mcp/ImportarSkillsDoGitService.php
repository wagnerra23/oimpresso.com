<?php

namespace Modules\Copiloto\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Entities\Mcp\McpSkill;
use Modules\Copiloto\Entities\Mcp\McpSkillLabel;
use Modules\Copiloto\Entities\Mcp\McpSkillVersion;
use Symfony\Component\Yaml\Yaml;

/**
 * ADR 0076 (Fase 1) — import inicial das 16 skills atuais em .claude/skills/ pra DB.
 *
 * Roda 1× via command `mcp:skills:import-from-git`. Idempotente: skills já
 * importadas não geram nova version (compara git_sha do HEAD).
 *
 * Default git_sync_mode=manual (Wagner aprova drift caso a caso) — ele pode
 * mudar pra auto/pinned via UI depois.
 */
class ImportarSkillsDoGitService
{
    /**
     * @return array{created: int, updated: int, unchanged: int, skipped: int, errors: array}
     */
    public function run(): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
        $errors = [];

        $base = base_path('.claude/skills');
        if (! is_dir($base)) {
            return compact('created', 'updated', 'unchanged', 'skipped', 'errors');
        }

        $files = glob("$base/*/SKILL.md") ?: [];
        $headSha = $this->resolveHeadSha();

        foreach ($files as $file) {
            try {
                $slug = basename(dirname($file));
                $parsed = $this->parseFile($file);
                if ($parsed === null) {
                    $skipped++;

                    continue;
                }

                $relativePath = str_replace('\\', '/', str_replace(base_path(), '', $file));
                $relativePath = ltrim($relativePath, '/');

                $skill = McpSkill::where('slug', $slug)->first();

                if ($skill === null) {
                    DB::transaction(function () use ($slug, $parsed, $relativePath, $headSha, &$created) {
                        $skill = McpSkill::create([
                            'slug'                => $slug,
                            'business_id'         => null, // global por default
                            'source'              => 'claude-code',
                            'status'              => 'published',
                            'module'              => $parsed['module'] ?? null,
                            'origin'              => 'imported',
                            'git_sync_mode'       => 'manual',
                            'auto_publish_to_git' => false,
                            'git_path'            => $relativePath,
                        ]);

                        $version = McpSkillVersion::create([
                            'skill_id'         => $skill->id,
                            'version'          => 1,
                            'body_markdown'    => $parsed['body'],
                            'frontmatter_json' => $parsed['frontmatter'],
                            'origin'           => 'git_seed',
                            'status'           => 'published',
                            'git_sha'          => $headSha,
                            'created_by'       => null,
                        ]);

                        $skill->current_version_id = $version->id;
                        $skill->save();

                        McpSkillLabel::create([
                            'skill_id'   => $skill->id,
                            'label'      => 'production',
                            'version_id' => $version->id,
                            'moved_at'   => now(),
                            'reason'     => 'Import inicial (mcp:skills:import-from-git)',
                        ]);

                        $created++;
                    });

                    continue;
                }

                // Já existe — checar se body/frontmatter mudou desde última version
                $latestVersion = $skill->versions()->orderByDesc('version')->first();
                $bodyChanged = $latestVersion === null
                    || $latestVersion->body_markdown !== $parsed['body']
                    || $latestVersion->frontmatter_json != $parsed['frontmatter'];

                if (! $bodyChanged) {
                    $unchanged++;

                    continue;
                }

                // Body mudou — cria nova version origin=git_seed (re-import detectou drift)
                DB::transaction(function () use ($skill, $latestVersion, $parsed, $headSha, &$updated) {
                    $newVersion = McpSkillVersion::create([
                        'skill_id'         => $skill->id,
                        'version'          => ($latestVersion->version ?? 0) + 1,
                        'body_markdown'    => $parsed['body'],
                        'frontmatter_json' => $parsed['frontmatter'],
                        'origin'           => 'git_seed',
                        'status'           => 'published',
                        'git_sha'          => $headSha,
                        'created_by'       => null,
                    ]);

                    $skill->current_version_id = $newVersion->id;
                    $skill->save();

                    // Move label production pra nova version
                    $existingLabel = McpSkillLabel::where('skill_id', $skill->id)
                        ->where('label', 'production')
                        ->first();
                    if ($existingLabel) {
                        $existingLabel->update([
                            'version_id'          => $newVersion->id,
                            'previous_version_id' => $existingLabel->version_id,
                            'moved_at'            => now(),
                            'reason'              => 'Re-import detectou body novo via mcp:skills:import-from-git',
                        ]);
                    }

                    $updated++;
                });
            } catch (\Throwable $e) {
                $errors[] = "$slug: ".$e->getMessage();
            }
        }

        return compact('created', 'updated', 'unchanged', 'skipped', 'errors');
    }

    /**
     * @return array{frontmatter: array, body: string, module: ?string}|null
     */
    private function parseFile(string $absolutePath): ?array
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }

        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $m)) {
            return [
                'frontmatter' => [],
                'body'        => $content,
                'module'      => null,
            ];
        }

        try {
            $frontmatter = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            $frontmatter = [];
        }

        if (! is_array($frontmatter)) {
            $frontmatter = [];
        }

        return [
            'frontmatter' => $frontmatter,
            'body'        => $m[2],
            'module'      => $frontmatter['module'] ?? null,
        ];
    }

    private function resolveHeadSha(): ?string
    {
        $sha = @exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD 2>&1');

        return is_string($sha) && preg_match('/^[a-f0-9]{40}$/', trim($sha)) ? trim($sha) : null;
    }
}
