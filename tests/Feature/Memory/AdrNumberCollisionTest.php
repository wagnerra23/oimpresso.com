<?php

/**
 * Governança de docs canônicos — guarda contra COLISÃO de número de ADR.
 *
 * Invariante (ADR 0028 — numeração monotônica): cada número de 4 dígitos
 * `NNNN-*.md` em memory/decisions/ deve identificar UMA única ADR. Quando 2+
 * arquivos compartilham o mesmo número, isso é uma COLISÃO.
 *
 * Por que este teste existe: já houve 5 colisões históricas (0101/0102/0119/
 * 0195/0235) e NENHUMA foi pega por teste — entraram em main via PRs paralelos
 * que numeraram igual sem coordenação cross-branch. A 5ª (0235: DS v4 roxo +
 * staging CT 100, ambos mergeados 2026-05-29) motivou esta camada de enforcement
 * (pedido Wagner: "testes que garantem que ninguém bagunça").
 *
 * Como uma colisão é tolerada: append-only Tier 0 IRREVOGÁVEL (Constituição Art. 3
 * + ADR 0095) PROÍBE renumerar uma ADR já aceita. Então uma colisão pré-existente
 * só é aceita se estiver REGISTRADA no frontmatter `numbering_collisions: [...]`
 * de memory/decisions/_INDEX-LIFECYCLE.md (single source of truth de lifecycle).
 * Registrar = decisão consciente de carregar o débito; não-registrar = bug.
 *
 * O que este teste GARANTE:
 *   1. Toda colisão presente no disco está registrada em `numbering_collisions`
 *      — colisão NÃO registrada FALHA o teste (lista número + arquivos).
 *   2. Todo número listado em `numbering_collisions` realmente colide no disco
 *      — entrada órfã/stale (número que não tem mais 2+ arquivos) FALHA o teste.
 *
 * Teste PURO de arquivo: lê do disco + assert. Sem banco, sem rede, determinístico.
 * Mecânica espelha AdrFrontmatterLinterTest/AdrFrontmatterTest: base_path() +
 * glob() + Symfony\Component\Yaml\Yaml.
 */

use Symfony\Component\Yaml\Yaml;

const COLLISION_ADR_DIR        = 'memory/decisions';
const COLLISION_LIFECYCLE_FILE = 'memory/decisions/_INDEX-LIFECYCLE.md';

/**
 * Mapeia número de 4 dígitos -> lista de filenames (sem extensão) que o usam.
 *
 * Varre SÓ o nível raiz de memory/decisions/ (glob não é recursivo, então o
 * subdir proposals/ fica naturalmente de fora — propostas não são ADRs aceitas
 * e não disputam numeração canon). Casa apenas `^(\d{4})-.*\.md$`; ignora
 * `_INDEX`/`_SCHEMA`/`_TEMPLATE`/README e qualquer arquivo sem prefixo numérico.
 *
 * @return array<string, list<string>>  ex: ['0235' => ['0235-ds-v4-...', '0235-staging-...']]
 */
function adrNumerosParaArquivos(): array
{
    $base = base_path(COLLISION_ADR_DIR);
    $arquivos = glob("$base/*.md") ?: [];

    $porNumero = [];
    foreach ($arquivos as $path) {
        $nome = basename($path, '.md');
        // Padrão canon: 4 dígitos + hífen + slug. `_INDEX`, `_SCHEMA`, `_TEMPLATE`,
        // `README` e afins não casam e são descartados aqui.
        if (! preg_match('/^(\d{4})-.+$/', $nome, $m)) {
            continue;
        }
        $numero = $m[1]; // mantém zero-padding como string canônica '0235'
        $porNumero[$numero][] = $nome;
    }

    return $porNumero;
}

/**
 * Lê `numbering_collisions: [...]` do frontmatter de _INDEX-LIFECYCLE.md e
 * devolve o conjunto de números colisão REGISTRADOS, normalizados pra string
 * de 4 dígitos com zero-padding.
 *
 * Cuidado: YAML coage `[0101, 0102, ...]` pra INTEIROS (101, 102, ...) — o
 * zero-padding se perde no parse. Por isso re-formatamos com sprintf('%04d').
 * Aceita também valores já-string ('0101') por robustez.
 *
 * @return list<string>  ex: ['0101', '0102', '0119', '0195', '0235']
 */
function colisoesRegistradas(): array
{
    $path = base_path(COLLISION_LIFECYCLE_FILE);
    $conteudo = file_get_contents($path);

    if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $conteudo, $m)) {
        return [];
    }

    try {
        $fm = Yaml::parse($m[1]);
    } catch (\Throwable $e) {
        return [];
    }

    $lista = (is_array($fm) && isset($fm['numbering_collisions']) && is_array($fm['numbering_collisions']))
        ? $fm['numbering_collisions']
        : [];

    $normalizados = [];
    foreach ($lista as $valor) {
        // Extrai só os dígitos (cobre int 101, string '0101', etc.) e re-padroniza.
        $digitos = preg_replace('/\D/', '', (string) $valor);
        if ($digitos === '') {
            continue;
        }
        $normalizados[] = sprintf('%04d', (int) $digitos);
    }

    return array_values(array_unique($normalizados));
}

it('toda colisão de número de ADR no disco está registrada em _INDEX-LIFECYCLE.numbering_collisions', function () {
    $porNumero = adrNumerosParaArquivos();
    $registradas = colisoesRegistradas();

    // Colisão = número usado por 2+ arquivos no nível raiz.
    $colisoesNoDisco = array_filter($porNumero, fn (array $arquivos) => count($arquivos) >= 2);

    $naoRegistradas = [];
    foreach ($colisoesNoDisco as $numero => $arquivos) {
        if (! in_array((string) $numero, $registradas, true)) {
            sort($arquivos);
            $naoRegistradas[] = sprintf(
                'ADR %s colide em %d arquivos: %s',
                $numero,
                count($arquivos),
                implode(', ', array_map(fn ($n) => "$n.md", $arquivos))
            );
        }
    }
    sort($naoRegistradas);

    expect($naoRegistradas)->toBeEmpty(
        "Colisão de número de ADR NÃO registrada (ADR 0028 violada).\n" .
        "Append-only Tier 0 proíbe renumerar ADR aceita — então: registre o número em\n" .
        "memory/decisions/_INDEX-LIFECYCLE.md no frontmatter `numbering_collisions: [...]`\n" .
        "OU renumere o arquivo ainda-não-mergeado ANTES do merge.\n  - " .
        implode("\n  - ", $naoRegistradas)
    );
});

it('todo número listado em numbering_collisions realmente colide no disco (sem entrada órfã)', function () {
    $porNumero = adrNumerosParaArquivos();
    $registradas = colisoesRegistradas();

    $orfas = [];
    foreach ($registradas as $numero) {
        $qtd = isset($porNumero[$numero]) ? count($porNumero[$numero]) : 0;
        if ($qtd < 2) {
            $orfas[] = sprintf(
                'ADR %s está em numbering_collisions mas tem %d arquivo(s) no disco (esperado >= 2)',
                $numero,
                $qtd
            );
        }
    }
    sort($orfas);

    expect($orfas)->toBeEmpty(
        "Entrada órfã/stale em `numbering_collisions` de _INDEX-LIFECYCLE.md.\n" .
        "Um número só deve constar ali enquanto a colisão existir no disco.\n" .
        "Remova a entrada do índice (a colisão foi resolvida) OU confirme que os 2+\n" .
        "arquivos ainda existem.\n  - " .
        implode("\n  - ", $orfas)
    );
});
