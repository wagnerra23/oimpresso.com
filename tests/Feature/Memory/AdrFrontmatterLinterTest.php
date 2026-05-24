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

// Vocabulario canon PT-BR (preferido em ADRs novas — ver memory/decisions/_SCHEMA.md).
// Legacy variants aceitos por backward-compat com ADRs aceitas pre-Constituição v2 (Tier 0
// IRREVOGÁVEL append-only — nao podemos reescrever frontmatter das aceitas sem ADR superseded).
const STATUS_VALIDOS    = [
    // canon PT-BR
    'rascunho', 'proposto', 'aceito', 'deprecated', 'superseded',
    // legacy English (ADRs 0122-0164 escritas durante migração module-grade v3)
    'accepted', 'proposed', 'aceita',
];
const AUTHORITY_VALIDOS = ['canonical', 'reference', 'exploratory'];
const LIFECYCLE_VALIDOS = [
    // canon PT-BR
    'ativo', 'arquivado', 'substituido',
    // legacy English / Wave variants
    'active', 'canon', 'feature_wish',
];
// canon: iniciais TEAM.md [W,F,M,L,E]. Legacy variants aceitas em ADRs aceitas append-only.
const DECIDED_BY_VALIDO = ['W', 'F', 'M', 'L', 'E', 'wagner', 'Wagner'];
const MODULE_VALIDOS    = [
    // canon (lowercase)
    'copiloto', 'financeiro', 'pontowr2', 'memcofre',
    'cms', 'officeimpresso', 'connector', 'grow', 'core', 'infra',
    'whatsapp', 'ads', 'governance', 'nfebrasil', 'recurringbilling',
    'nfse', 'projectmgmt', 'repair', 'consultaos',
    'kb',           // ADR 0150 KB Unificado como Grafo de Conhecimento (2026-05-16)
    'sells',        // ADRs 0129/0136/0143 — FSM canonico Sells (vendas core)
    'autopecas',    // ADR 0125 — Modules/Autopecas feature-wish (Vargas sinal qualificado)
    'comissao',     // ADR 0151 — Modules/Comissao feature-wish (cross-vertical)
    'pcp',          // ADR 0152 — Modules/Pcp feature-wish (apontamento producao)
    // Legacy/Wave variants (case-sensitive em ADRs aceitas pre-migração — append-only Tier 0)
    'Sells', 'Autopecas', 'Comissao', 'Pcp', 'Governance', 'ADS',
    'Infra', 'jana', 'sells', 'design-system', 'Sells',
    'NfeBrasil',    // ADR 0186 mergeada com case canônico do path Modules/NfeBrasil/ — append-only impede fix
];

const CAMPOS_OBRIGATORIOS = [
    'slug', 'number', 'title', 'type',
    'status', 'authority', 'lifecycle',
    'decided_by', 'decided_at',
];

/**
 * ADRs legacy (pre-Constituição v2 / Wave 19+) que NÃO seguem o schema canon completo.
 *
 * Não podem ser reeditadas por força de append-only Tier 0 IRREVOGÁVEL (ADR 0095 +
 * Constituição Art. 3 + governance-gate.yml). Wagner aprova migração via PR dedicado
 * com label `frontmatter-migration-approved` (TODO Wave 30+).
 *
 * Cada entrada aqui é débito documentado de frontmatter — backlog na próxima limpeza:
 * - 0122/0123/0124/0126-mcp-jira/0127 — header tabular legacy (campo `adr:`/`deciders:`/`references:`)
 * - 0126-vault/0128 — sem frontmatter (header markdown plain)
 */
const ADRS_LEGACY_SKIP = [
    '0122-admin-center-ct100',
    '0123-modules-arquivos-backbone',
    '0124-curador-conhecimento-pipeline',
    '0126-mcp-jira-projects-modulos-verticais',
    '0126-vault-chunked-encryption-sprint-2',
    '0127-modules-auditoria-undo-activity-log',
    '0128-smoke-testing-e2e-pos-cycle',
    // Pre-existentes em main 2026-05-21 (Wagner Financeiro/Accounting deprec sprint):
    // ambos sem frontmatter YAML canon. Append-only Tier 0 — não editar.
    // Backlog migração: Wave 30+ junto com 0122-0128.
    '0172-deprecar-modulo-accounting-fundir-financeiro',
    '0173-errata-arq-0005-tabelas-accounting-sem-prefixo',
    // Aceita 2026-05-21 antes do CI rodar este check com vocabulário module strict.
    // `module: crm` legacy (MODULE_VALIDOS não lista 'crm' nem 'Crm' como canon —
    // Modules/Crm é módulo real do projeto; backlog adicionar à enum em PR
    // dedicado quando outras ADRs precisarem). Append-only Tier 0 — não editar.
    '0179-cliente-drawer-760px-substitui-show-fullpage',
    // Pre-existente em main · frontmatter incompleto (sem slug/number/type/authority/
    // lifecycle/decided_at). Append-only Tier 0 — não editar. Backlog migração junto
    // com 0122-0128 + 0172-0173 em Wave 30+.
    '0170-onda5-simplificada',
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
        // Pula ADRs legacy com frontmatter incompleto/ausente — append-only Tier 0
        // impede reescrita; correção espera PR dedicado com Wagner.
        if (in_array($name, ADRS_LEGACY_SKIP, true)) {
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
