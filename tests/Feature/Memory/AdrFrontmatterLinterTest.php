<?php

/**
 * MEM-KB-3 / F1 — Linter de frontmatter YAML em memory/decisions/*.md.
 *
 * Garante que toda ADR aceita pelo CI atende ao schema canônico documentado
 * em memory/decisions/_SCHEMA.md, validado contra memory/decisions/_schema.json.
 *
 * Cobertura:
 *   1. Toda ADR tem frontmatter YAML válido entre `---`.
 *   2. Frontmatter parsa sem erro (symfony/yaml).
 *   3. 8 campos obrigatórios presentes (slug, number, title, type, status,
 *      authority, lifecycle, decided_by, decided_at).
 *   4. Vocabulário controlado (status/authority/lifecycle/decided_by) bate.
 *   5. `slug` casa com nome do arquivo.
 *   6. `number` casa com prefixo numérico do filename.
 *   7. `superseded` exige `superseded_by` não-vazio + lifecycle=substituido.
 *
 * Quando falha: o erro lista a ADR específica + campo problemático.
 * Migração das 60 ADRs antigas roda na task seguinte (MEM-KB-3 step 6).
 */

use Symfony\Component\Yaml\Yaml;

const ADR_DIR_REL = 'memory/decisions';

const STATUS_VALIDOS    = ['rascunho', 'proposto', 'aceito', 'deprecated', 'superseded'];
const AUTHORITY_VALIDOS = ['canonical', 'reference', 'exploratory'];
const LIFECYCLE_VALIDOS = ['ativo', 'arquivado', 'substituido'];
const DECIDED_BY_VALIDO = ['W', 'F', 'M', 'L', 'E'];
const MODULE_VALIDOS    = [
    'copiloto', 'financeiro', 'pontowr2', 'memcofre',
    'cms', 'officeimpresso', 'connector', 'grow', 'core', 'infra',
];

const CAMPOS_OBRIGATORIOS = [
    'slug', 'number', 'title', 'type',
    'status', 'authority', 'lifecycle',
    'decided_by', 'decided_at',
];

/**
 * @return array<array{path:string, slug:string, content:string}>
 */
function adrsParaValidar(): array
{
    $base = base_path(ADR_DIR_REL);
    $arquivos = glob("$base/*.md") ?: [];

    $adrs = [];
    foreach ($arquivos as $path) {
        $name = basename($path, '.md');
        // Pula README, _SCHEMA, _TEMPLATE, _INDEX
        if (str_starts_with($name, '_') || $name === 'README') {
            continue;
        }
        $adrs[] = [
            'path'    => $path,
            'slug'    => $name,
            'content' => file_get_contents($path),
        ];
    }
    return $adrs;
}

/**
 * @return array{frontmatter:?array, body:string, raw:?string}
 */
function extrairFrontmatter(string $conteudo): array
{
    if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $conteudo, $m)) {
        return ['frontmatter' => null, 'body' => $conteudo, 'raw' => null];
    }
    try {
        $fm = Yaml::parse($m[1], Yaml::PARSE_DATETIME);
    } catch (\Throwable $e) {
        return ['frontmatter' => null, 'body' => $m[2], 'raw' => $m[1]];
    }
    return ['frontmatter' => is_array($fm) ? $fm : null, 'body' => $m[2], 'raw' => $m[1]];
}

it('todas as ADRs têm frontmatter YAML válido', function () {
    $erros = [];
    foreach (adrsParaValidar() as $adr) {
        $parsed = extrairFrontmatter($adr['content']);
        if ($parsed['frontmatter'] === null) {
            $erros[] = sprintf('%s: %s', $adr['slug'],
                $parsed['raw'] === null
                    ? 'sem bloco --- de frontmatter'
                    : 'YAML inválido: ' . substr($parsed['raw'], 0, 100));
        }
    }
    expect($erros)->toBeEmpty(
        "ADRs sem frontmatter válido (rode `php artisan mcp:adr:migrar-frontmatter`):\n  - " .
        implode("\n  - ", $erros)
    );
});

it('todas as ADRs têm os 8 campos obrigatórios', function () {
    $erros = [];
    foreach (adrsParaValidar() as $adr) {
        $parsed = extrairFrontmatter($adr['content']);
        if (! $parsed['frontmatter']) {
            continue; // já coberto pelo teste anterior
        }
        $faltando = array_diff(CAMPOS_OBRIGATORIOS, array_keys($parsed['frontmatter']));
        if (! empty($faltando)) {
            $erros[] = sprintf('%s: faltando [%s]', $adr['slug'], implode(', ', $faltando));
        }
    }
    expect($erros)->toBeEmpty(
        "ADRs com campos obrigatórios faltando:\n  - " . implode("\n  - ", $erros)
    );
});

it('vocabulário controlado de status/authority/lifecycle/decided_by é respeitado', function () {
    $erros = [];
    foreach (adrsParaValidar() as $adr) {
        $fm = extrairFrontmatter($adr['content'])['frontmatter'] ?? [];
        if (! $fm) continue;

        if (isset($fm['status']) && ! in_array($fm['status'], STATUS_VALIDOS, true)) {
            $erros[] = "{$adr['slug']}: status inválido `{$fm['status']}`";
        }
        if (isset($fm['authority']) && ! in_array($fm['authority'], AUTHORITY_VALIDOS, true)) {
            $erros[] = "{$adr['slug']}: authority inválido `{$fm['authority']}`";
        }
        if (isset($fm['lifecycle']) && ! in_array($fm['lifecycle'], LIFECYCLE_VALIDOS, true)) {
            $erros[] = "{$adr['slug']}: lifecycle inválido `{$fm['lifecycle']}`";
        }
        if (isset($fm['decided_by']) && is_array($fm['decided_by'])) {
            foreach ($fm['decided_by'] as $iniciais) {
                if (! in_array($iniciais, DECIDED_BY_VALIDO, true)) {
                    $erros[] = "{$adr['slug']}: decided_by inválido `{$iniciais}` (use W/F/M/L/E)";
                }
            }
        }
        if (isset($fm['module']) && $fm['module'] !== null && ! in_array($fm['module'], MODULE_VALIDOS, true)) {
            $erros[] = "{$adr['slug']}: module inválido `{$fm['module']}`";
        }
    }
    expect($erros)->toBeEmpty(
        "Vocabulário controlado violado:\n  - " . implode("\n  - ", $erros)
    );
});

it('slug do frontmatter casa com nome do arquivo', function () {
    $erros = [];
    foreach (adrsParaValidar() as $adr) {
        $fm = extrairFrontmatter($adr['content'])['frontmatter'] ?? [];
        if (! $fm || ! isset($fm['slug'])) continue;

        if ($fm['slug'] !== $adr['slug']) {
            $erros[] = "{$adr['slug']}: frontmatter slug=`{$fm['slug']}` divergente do filename";
        }
    }
    expect($erros)->toBeEmpty("Slug divergente:\n  - " . implode("\n  - ", $erros));
});

it('number do frontmatter casa com prefixo do filename', function () {
    $erros = [];
    foreach (adrsParaValidar() as $adr) {
        $fm = extrairFrontmatter($adr['content'])['frontmatter'] ?? [];
        if (! $fm || ! isset($fm['number'])) continue;

        if (! preg_match('/^(\d{4})-/', $adr['slug'], $m)) continue;
        $esperado = (int) $m[1];

        if ((int) $fm['number'] !== $esperado) {
            $erros[] = "{$adr['slug']}: number=`{$fm['number']}` divergente do prefixo `{$esperado}`";
        }
    }
    expect($erros)->toBeEmpty("Number divergente:\n  - " . implode("\n  - ", $erros));
});

it('ADRs com status=superseded têm superseded_by + lifecycle=substituido', function () {
    $erros = [];
    foreach (adrsParaValidar() as $adr) {
        $fm = extrairFrontmatter($adr['content'])['frontmatter'] ?? [];
        if (! $fm || ($fm['status'] ?? null) !== 'superseded') continue;

        $supBy = $fm['superseded_by'] ?? [];
        if (empty($supBy)) {
            $erros[] = "{$adr['slug']}: status=superseded mas superseded_by vazio";
        }
        if (($fm['lifecycle'] ?? null) !== 'substituido') {
            $erros[] = "{$adr['slug']}: status=superseded mas lifecycle != substituido";
        }
    }
    expect($erros)->toBeEmpty("Coerência superseded violada:\n  - " . implode("\n  - ", $erros));
});

it('schema canônico _SCHEMA.md e _schema.json existem', function () {
    expect(file_exists(base_path('memory/decisions/_SCHEMA.md')))->toBeTrue();
    expect(file_exists(base_path('memory/decisions/_schema.json')))->toBeTrue();
    expect(file_exists(base_path('memory/decisions/_TEMPLATE.md')))->toBeTrue();

    $schema = json_decode(file_get_contents(base_path('memory/decisions/_schema.json')), true);
    expect($schema)->toBeArray()->toHaveKey('required');
    expect($schema['required'])->toContain('slug', 'number', 'title', 'type', 'status', 'authority', 'lifecycle', 'decided_by', 'decided_at');
});
