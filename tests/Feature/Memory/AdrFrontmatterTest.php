<?php

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (frontmatter dos ADRs em memory/decisions) contra fonte-da-verdade móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Camada 1 — Eval Suite (Opção C).
 *
 * Tests de frontmatter complementares ao AdrFrontmatterLinterTest existente,
 * focados nos requisitos da Opção C aprovada por Wagner:
 *   - canary fact tests (ADR 0066 cobre fatos críticos)
 *   - cobertura de status válidos (proposto/aceito/deprecated/superseded)
 *   - validação de superseded_by referenciando ADR existente
 *
 * Ver: ADR 0066 (format_date), ADR 0064 (modularização), ADR 0065 (Permission Registry).
 */

use Symfony\Component\Yaml\Yaml;

// Tests/TestCase já é aplicado pelo tests/Pest.php pra todo Feature/

const SPEC_ADR_DIR             = 'memory/decisions';
const SPEC_STATUS_VALIDOS      = ['proposto', 'aceito', 'deprecated', 'superseded', 'rascunho'];
const SPEC_CAMPOS_OBRIGATORIOS = ['slug', 'number', 'title', 'type', 'status', 'decided_by', 'decided_at'];

/**
 * @return array<array{path:string, slug:string, content:string}>
 */
function specAdrs(): array
{
    $base = base_path(SPEC_ADR_DIR);
    $arquivos = glob("$base/*.md") ?: [];
    $adrs = [];
    foreach ($arquivos as $path) {
        $name = basename($path, '.md');
        if (str_starts_with($name, '_') || $name === 'README') continue;
        $adrs[] = [
            'path'    => $path,
            'slug'    => $name,
            'content' => file_get_contents($path),
        ];
    }
    return $adrs;
}

function specFrontmatter(string $conteudo): ?array
{
    if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $conteudo, $m)) return null;
    try {
        $fm = Yaml::parse($m[1], Yaml::PARSE_DATETIME);
        return is_array($fm) ? $fm : null;
    } catch (\Throwable $e) {
        return null;
    }
}

it('Test 1: ADRs com frontmatter têm os campos mínimos válidos', function () {
    // Migração das ADRs legadas (0001-0046) é tracked pelo AdrFrontmatterLinterTest
    // existente. Aqui validamos só ADRs que JÁ tem frontmatter — schema novo.
    $erros = [];
    foreach (specAdrs() as $adr) {
        $fm = specFrontmatter($adr['content']);
        if (! $fm) continue; // skip — debt rastreada no linter existente
        $faltando = array_diff(SPEC_CAMPOS_OBRIGATORIOS, array_keys($fm));
        if ($faltando) {
            $erros[] = "{$adr['slug']}: faltando [" . implode(', ', $faltando) . "]";
        }
        if (isset($fm['type']) && $fm['type'] !== 'adr') {
            $erros[] = "{$adr['slug']}: type=`{$fm['type']}` (esperado `adr`)";
        }
        if (isset($fm['status']) && ! in_array($fm['status'], SPEC_STATUS_VALIDOS, true)) {
            $erros[] = "{$adr['slug']}: status inválido `{$fm['status']}`";
        }
        if (isset($fm['decided_at'])) {
            $da = $fm['decided_at'];
            $valid = $da instanceof \DateTimeInterface
                || (is_string($da) && preg_match('/^\d{4}-\d{2}-\d{2}/', $da));
            if (! $valid) {
                $erros[] = "{$adr['slug']}: decided_at sem formato YYYY-MM-DD";
            }
        }
    }
    expect($erros)->toBeEmpty(
        "ADRs com frontmatter incompleto:\n  - " . implode("\n  - ", $erros)
    );
});

it('Test 2: slug do frontmatter casa com filename', function () {
    $erros = [];
    foreach (specAdrs() as $adr) {
        $fm = specFrontmatter($adr['content']);
        if (! $fm || ! isset($fm['slug'])) continue;
        if ($fm['slug'] !== $adr['slug']) {
            $erros[] = "{$adr['slug']}: slug=`{$fm['slug']}` divergente";
        }
    }
    expect($erros)->toBeEmpty("Slug divergente:\n  - " . implode("\n  - ", $erros));
});

it('Test 3: number é inteiro e bate com prefixo do filename', function () {
    $erros = [];
    foreach (specAdrs() as $adr) {
        $fm = specFrontmatter($adr['content']);
        if (! $fm || ! isset($fm['number'])) continue;

        // YAML parsa "0064" como string por causa do leading zero — aceita ambos
        // desde que casa com o prefixo numérico do filename.
        $num = is_int($fm['number']) ? $fm['number'] : (ctype_digit((string) $fm['number']) ? (int) $fm['number'] : null);
        if ($num === null) {
            $erros[] = "{$adr['slug']}: number não é inteiro nem string numérica (`{$fm['number']}`)";
            continue;
        }
        if (! preg_match('/^(\d{4})-/', $adr['slug'], $m)) continue;
        if ($num !== (int) $m[1]) {
            $erros[] = "{$adr['slug']}: number=`{$fm['number']}` divergente do prefixo `{$m[1]}`";
        }
    }
    expect($erros)->toBeEmpty("Number divergente:\n  - " . implode("\n  - ", $erros));
});

it('Test 4: superseded_by referencia ADR existente', function () {
    // Primeiro indexa números existentes
    $existentes = [];
    foreach (specAdrs() as $adr) {
        if (preg_match('/^(\d{4})-/', $adr['slug'], $m)) {
            $existentes[(int) $m[1]] = true;
        }
    }

    $erros = [];
    foreach (specAdrs() as $adr) {
        $fm = specFrontmatter($adr['content']);
        if (! $fm) continue;
        $supBy = $fm['superseded_by'] ?? null;
        if (! $supBy) continue;
        $refs = is_array($supBy) ? $supBy : [$supBy];
        foreach ($refs as $ref) {
            // Aceita "0048" ou "[0048]" ou int 48
            $num = is_int($ref) ? $ref : (int) preg_replace('/\D/', '', (string) $ref);
            if ($num <= 0) continue;
            if (! isset($existentes[$num])) {
                $erros[] = "{$adr['slug']}: superseded_by referencia ADR " . sprintf('%04d', $num) . " inexistente";
            }
        }
    }
    expect($erros)->toBeEmpty("References quebradas:\n  - " . implode("\n  - ", $erros));
});

it('Test 5: ADR 0066 (canary) cobre fatos críticos do format_date', function () {
    $path = base_path('memory/decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md');
    expect(file_exists($path))->toBeTrue('ADR 0066 não encontrada');

    $conteudo = file_get_contents($path);
    $faltando = [];
    foreach (['Larissa', '10634ad2', 'e5c8c90d', 'createFromTimestamp', 'UPDATE transactions'] as $termo) {
        if (! str_contains($conteudo, $termo)) {
            $faltando[] = $termo;
        }
    }
    expect($faltando)->toBeEmpty(
        "ADR 0066 perdeu fatos canary: [" . implode(', ', $faltando) . "].\n" .
        "Esses termos são sentinelas de promoção auto-mem→ADR (ver ADR 0066)."
    );
});
