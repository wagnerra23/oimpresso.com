<?php

/**
 * Anti-regressão de skills auto-ativáveis em .claude/skills/<nome>/SKILL.md.
 *
 * Cobertura:
 *   1. Toda skill tem frontmatter YAML válido entre `---`.
 *   2. Campos obrigatórios presentes (`name`, `description`).
 *   3. `name` do frontmatter casa com nome do diretório (chave de matching do harness).
 *   4. `description` tem comprimento mínimo (skills com descrição vaga não disparam matching).
 *   5. Não existem skills duplicadas (mesmo `name` em pastas diferentes).
 *
 * Quando falha: erro lista a skill específica + campo problemático.
 * Skills são versionadas em git (são código de configuração); regredir frontmatter
 * quebra silenciosamente o auto-matching do Claude Code.
 */

use Symfony\Component\Yaml\Yaml;

const SKILLS_DIR_REL = '.claude/skills';

const SKILL_CAMPOS_OBRIGATORIOS = ['name', 'description'];

const SKILL_DESCRIPTION_MIN_CHARS = 60;

/**
 * @return array<array{slug:string, path:string, content:string}>
 */
function skillsParaValidar(): array
{
    $base = base_path(SKILLS_DIR_REL);
    if (! is_dir($base)) {
        return [];
    }

    $skills = [];
    foreach (glob("$base/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $skillFile = "$dir/SKILL.md";
        if (! is_file($skillFile)) {
            continue;
        }
        $skills[] = [
            'slug'    => basename($dir),
            'path'    => $skillFile,
            'content' => file_get_contents($skillFile),
        ];
    }

    return $skills;
}

/**
 * @return array{frontmatter:?array, body:string}
 */
function extrairFrontmatterSkill(string $conteudo): array
{
    if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $conteudo, $m)) {
        return ['frontmatter' => null, 'body' => $conteudo];
    }
    try {
        $fm = Yaml::parse($m[1]);
    } catch (\Throwable $e) {
        return ['frontmatter' => null, 'body' => $m[2]];
    }

    return ['frontmatter' => is_array($fm) ? $fm : null, 'body' => $m[2]];
}

it('descobre pelo menos as 2 skills novas (ads-decision-flow, memoria-recall-flow)', function () {
    $slugs = array_column(skillsParaValidar(), 'slug');
    expect($slugs)->toContain('ads-decision-flow', 'memoria-recall-flow');
});

it('toda skill tem frontmatter YAML válido', function () {
    $erros = [];
    foreach (skillsParaValidar() as $skill) {
        $fm = extrairFrontmatterSkill($skill['content'])['frontmatter'];
        if ($fm === null) {
            $erros[] = $skill['slug'].' (frontmatter ausente ou YAML inválido)';
        }
    }
    expect($erros)->toBe([], "Skills com frontmatter inválido:\n - ".implode("\n - ", $erros));
});

it('toda skill tem name e description', function () {
    $erros = [];
    foreach (skillsParaValidar() as $skill) {
        $fm = extrairFrontmatterSkill($skill['content'])['frontmatter'];
        if ($fm === null) {
            continue; // teste anterior já falhou
        }
        foreach (SKILL_CAMPOS_OBRIGATORIOS as $campo) {
            if (empty($fm[$campo])) {
                $erros[] = $skill['slug']." (campo '$campo' ausente ou vazio)";
            }
        }
    }
    expect($erros)->toBe([], "Skills com campos obrigatórios faltando:\n - ".implode("\n - ", $erros));
});

it('name do frontmatter casa com nome do diretório', function () {
    $erros = [];
    foreach (skillsParaValidar() as $skill) {
        $fm = extrairFrontmatterSkill($skill['content'])['frontmatter'];
        if ($fm === null || empty($fm['name'])) {
            continue;
        }
        if ($fm['name'] !== $skill['slug']) {
            $erros[] = $skill['slug']." (frontmatter name='{$fm['name']}' ≠ pasta)";
        }
    }
    expect($erros)->toBe([], "Skills com name divergente do diretório:\n - ".implode("\n - ", $erros));
});

it('description tem ≥ '.SKILL_DESCRIPTION_MIN_CHARS.' caracteres', function () {
    $erros = [];
    foreach (skillsParaValidar() as $skill) {
        $fm = extrairFrontmatterSkill($skill['content'])['frontmatter'];
        if ($fm === null || empty($fm['description'])) {
            continue;
        }
        $len = mb_strlen($fm['description']);
        if ($len < SKILL_DESCRIPTION_MIN_CHARS) {
            $erros[] = $skill['slug']." (description tem $len chars, mínimo ".SKILL_DESCRIPTION_MIN_CHARS.')';
        }
    }
    expect($erros)->toBe([], "Skills com description muito curta (matching ruim):\n - ".implode("\n - ", $erros));
});

it('não existem skills com name duplicado', function () {
    $names = [];
    foreach (skillsParaValidar() as $skill) {
        $fm = extrairFrontmatterSkill($skill['content'])['frontmatter'];
        if ($fm === null || empty($fm['name'])) {
            continue;
        }
        $names[$fm['name']][] = $skill['slug'];
    }

    $duplicadas = array_filter($names, fn ($pastas) => count($pastas) > 1);
    expect($duplicadas)->toBe([], 'Skills com name duplicado: '.json_encode($duplicadas));
});
