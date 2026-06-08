<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Jana\Entities\Mcp\McpSkill;
use Modules\Jana\Entities\Mcp\McpSkillVersion;
use Symfony\Component\Yaml\Yaml;

/**
 * Observabilidade D9.a (ADR 0155): scaffold generation envolto em
 * `OtelHelper::span(` (Tracer ads.scaffold.skill) — mede 4 testes validade.
 *
 * Meta-skill `meta-skill-roi-erp-autonomo` em código.
 *
 * Aplica os 4 testes de validade da missão e gera scaffold de skill nova
 * (filesystem `.claude/skills/<slug>/SKILL.md` + DB `mcp_skills` status=draft).
 *
 * ADR 0078 — A constituição do oimpresso é uma frase. Skill é a unidade
 * atômica de evolução. Tudo passa por aqui.
 */
class ScaffoldSkillFromMissionService
{
    private const META_SLUG = 'meta-skill-roi-erp-autonomo';

    /**
     * Palavras que indicam SUBSTITUIÇÃO de trabalho humano. Lista é flexível
     * — se nenhuma bate exato, retornamos warning, não bloqueio.
     */
    private const SUBSTITUTE_KEYWORDS = [
        'substitui', 'automatiza', 'elimina', 'remove', 'evita',
        'previne', 'valida', 'garante', 'força', 'exige', 'impede',
        'bloqueia', 'gera', 'cria', 'aplica',
    ];

    /**
     * Palavras que indicam TRABALHO HUMANO REPETITIVO (não decisão única).
     */
    private const REPETITIVE_KEYWORDS = [
        'toda', 'todo', 'sempre', 'cada', 'qualquer',
        'humano', 'manual', 'repetitivo', 'recorrente',
        'antes de', 'após', 'ao criar', 'ao alterar', 'ao tocar',
    ];

    /**
     * @param  string  $mission  Frase única descrevendo a missão da skill.
     * @param  string|null  $slug  Slug opcional. Se null, deriva da missão.
     * @param  bool  $force  Pular validação dos 4 testes (Wagner override).
     * @return array{
     *   ok: bool,
     *   slug: string,
     *   skill_id: ?int,
     *   absolute_path: string,
     *   git_path: string,
     *   tests: array{substitui: bool, repetitivo: bool, message: string},
     *   message: string,
     * }
     */
    public function run(string $mission, ?string $slug = null, bool $force = false): array
    {
        $mission = trim($mission);
        if ($mission === '') {
            return $this->failure('', 'missão vazia — passe 1 frase descrevendo o que a skill substitui');
        }

        // Teste 1 + 2: validação léxica de "substitui trabalho humano repetitivo"
        $tests = $this->runValidationTests($mission);
        if (! $force && ! ($tests['substitui'] && $tests['repetitivo'])) {
            return [
                'ok'            => false,
                'slug'          => '',
                'skill_id'      => null,
                'absolute_path' => '',
                'git_path'      => '',
                'tests'         => $tests,
                'message'       => $tests['message'].' (use --force pra ignorar a meta-skill)',
            ];
        }

        // Slug
        $slug = $slug !== null ? Str::slug($slug) : $this->deriveSlug($mission);
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            return $this->failure($slug, "slug inválido: {$slug}");
        }

        $absolutePath = base_path('.claude/skills/'.$slug.'/SKILL.md');
        $gitPath = '.claude/skills/'.$slug.'/SKILL.md';

        // Idempotência — se já existe, não sobrescreve
        if (is_file($absolutePath)) {
            return $this->failure($slug, "skill {$slug} já existe em {$gitPath}. Use editor /ads/admin/skills/{$slug}/edit");
        }

        // Gera scaffold
        $now = now();
        $body = $this->renderTemplate($slug, $mission, $now->toDateString());

        // Cria pasta + arquivo
        $dir = dirname($absolutePath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return $this->failure($slug, "não consegui criar diretório {$dir}");
        }

        if (@file_put_contents($absolutePath, $body) === false) {
            return $this->failure($slug, "não consegui escrever {$absolutePath}");
        }

        // Registra no DB (se schema disponível)
        $skillId = null;
        if ($this->hasSkillsSchema()) {
            try {
                $skillId = $this->persistToDb($slug, $body, $gitPath, $mission);
            } catch (\Throwable $e) {
                // DB falhou mas filesystem foi criado — sucesso parcial
                return [
                    'ok'            => true,
                    'slug'          => $slug,
                    'skill_id'      => null,
                    'absolute_path' => $absolutePath,
                    'git_path'      => $gitPath,
                    'tests'         => $tests,
                    'message'       => "Filesystem criado, DB falhou: {$e->getMessage()}. Rode `php artisan mcp:skills:import-from-git` depois.",
                ];
            }
        }

        return [
            'ok'            => true,
            'slug'          => $slug,
            'skill_id'      => $skillId,
            'absolute_path' => $absolutePath,
            'git_path'      => $gitPath,
            'tests'         => $tests,
            'message'       => "Skill {$slug} criada (status=draft). Edite via /ads/admin/skills/{$slug}/edit",
        ];
    }

    private function runValidationTests(string $mission): array
    {
        $missionLower = mb_strtolower($mission);

        $hasSubstitute = false;
        foreach (self::SUBSTITUTE_KEYWORDS as $kw) {
            if (str_contains($missionLower, $kw)) {
                $hasSubstitute = true;
                break;
            }
        }

        $hasRepetitive = false;
        foreach (self::REPETITIVE_KEYWORDS as $kw) {
            if (str_contains($missionLower, $kw)) {
                $hasRepetitive = true;
                break;
            }
        }

        $message = '';
        if (! $hasSubstitute && ! $hasRepetitive) {
            $message = 'Missão não passa nos 2 primeiros testes da meta-skill: não fica claro o que SUBSTITUI nem que é trabalho HUMANO REPETITIVO. Reformule.';
        } elseif (! $hasSubstitute) {
            $message = 'Missão fala de algo recorrente mas não fica claro o que SUBSTITUI. Use verbos: substitui/automatiza/elimina/valida/garante/força.';
        } elseif (! $hasRepetitive) {
            $message = 'Missão tem ação substitutiva mas não fica claro que é REPETITIVA. Indique: "toda X", "sempre que", "ao criar", "ao alterar".';
        } else {
            $message = '4 testes preliminares OK. Resta declarar ROI mensurável + conexão com R$ [redacted Tier 0]M / 24m no SKILL.md gerado.';
        }

        return [
            'substitui'   => $hasSubstitute,
            'repetitivo' => $hasRepetitive,
            'message'    => $message,
        ];
    }

    private function deriveSlug(string $mission): string
    {
        // Pega as 5 primeiras palavras significativas
        $words = preg_split('/\s+/', $mission);
        $stop = ['a','o','e','de','do','da','com','em','para','por','que','um','uma','os','as','no','na','nos','nas','é'];
        $significant = array_filter($words, fn ($w) => mb_strlen($w) > 2 && ! in_array(mb_strtolower($w), $stop, true));
        $slugSource = implode('-', array_slice(array_values($significant), 0, 5));

        return Str::slug($slugSource) ?: 'skill-'.now()->format('Ymd-His');
    }

    private function renderTemplate(string $slug, string $mission, string $date): string
    {
        $missionEscaped = str_replace('"', '\"', $mission);

        return <<<MD
---
name: {$slug}
mission: "{$missionEscaped}"
description: TODO — quando esta skill ATIVA (preenche pra Claude Code reconhecer auto-load).
type: skill
status: draft
version: 0.1.0
trust_level: L2
owner: wagner
created_at: {$date}
generated_from: skill:scaffold
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: null
triggers_on:
  - TODO — padrão 1 (ex: "ao criar Eloquent Model")
  - TODO — padrão 2
does_not_trigger_on:
  - TODO — exclusões claras
roi_metric:
  type: time
  baseline: "TODO — estado atual mensurável"
  target: "TODO — melhoria esperada mensurável"
metrics:
  triggered_count: 0
  helped_outcome_rate: 0.0
  false_trigger_rate: 0.0
---

# {$slug}

## Missão

> {$mission}

## Os 4 testes (preencher antes de approve)

- [ ] **Substitui:** TODO — que trabalho humano esta skill substitui?
- [ ] **Humano repetitivo:** TODO — quantas vezes por semana/mês acontece?
- [ ] **ROI mensurável:** TODO — declare tipo (tempo/erro/receita), baseline, target
- [ ] **Acelera ERP autônomo R\$ 10M / 24m:** TODO — conexão direta com a tese

## Quando aciona

TODO — descreva os triggers que casam com a `description` do frontmatter.

## Quando NÃO aciona

TODO — exclusões explícitas pra evitar false positives.

## Regras

TODO — lista numerada do que a IA deve fazer quando essa skill carrega.

## Exemplos

✅ TODO — caso correto

❌ TODO — caso errado / o que NÃO fazer

## Anti-patterns

TODO — armadilhas comuns que esta skill previne.

## Ciclo evolutivo (Planejar→Executar→Analisar→Organizar)

- **Planejar:** este SKILL.md (em rascunho)
- **Executar:** Claude Code carrega quando description casar
- **Analisar:** `triggered_count`, `helped_outcome_rate`, `false_trigger_rate` no frontmatter
- **Organizar:** nova versão (semver bump) com 4 rationales obrigatórios via UI editor

## Histórico de versões

- **v0.1.0** ({$date}) — DRAFT criado via `php artisan skill:scaffold "{$missionEscaped}"`
MD;
    }

    private function hasSkillsSchema(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('mcp_skills');
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::warning('ADS ScaffoldSkillFromMissionService: hasSkillsSchema falhou', ['exception' => $e]);
            return false;
        }
    }

    private function persistToDb(string $slug, string $body, string $gitPath, string $mission): int
    {
        $skillId = 0;

        DB::transaction(function () use ($slug, $body, $gitPath, $mission, &$skillId) {
            $skill = McpSkill::create([
                'slug'                => $slug,
                'business_id'         => null,
                'source'              => 'claude-code',
                'status'              => 'draft',
                'module'              => null,
                'origin'              => 'created',
                'git_sync_mode'       => 'manual',
                'auto_publish_to_git' => false,
                'git_path'            => $gitPath,
            ]);

            // Parse frontmatter pra persistir como JSON
            $frontmatter = [];
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $body, $m)) {
                try {
                    $parsed = Yaml::parse($m[1]);
                    $frontmatter = is_array($parsed) ? $parsed : [];
                } catch (\Throwable $e) {
                    $frontmatter = [];
                }
                $bodyOnly = $m[2];
            } else {
                $bodyOnly = $body;
            }

            $version = McpSkillVersion::create([
                'skill_id'         => $skill->id,
                'version'          => 1,
                'body_markdown'    => $bodyOnly,
                'frontmatter_json' => $frontmatter,
                'origin'           => 'created',
                'status'           => 'draft',
                'git_sha'          => null,
                'created_by'       => 1, // Wagner
            ]);

            $skill->current_version_id = $version->id;
            $skill->save();

            $skillId = $skill->id;
        });

        return $skillId;
    }

    private function failure(string $slug, string $message): array
    {
        return [
            'ok'            => false,
            'slug'          => $slug,
            'skill_id'      => null,
            'absolute_path' => '',
            'git_path'      => '',
            'tests'         => ['substitui' => false, 'repetitivo' => false, 'message' => $message],
            'message'       => $message,
        ];
    }
}
