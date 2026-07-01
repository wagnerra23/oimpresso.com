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
 * só é aceita se estiver REGISTRADA em `collisions_grandfathered: [...]` de
 * governance/adr-collisions-baseline.json (fonte machine-readable única — ADR 0274 §3,
 * antes era o frontmatter `numbering_collisions` do _INDEX-LIFECYCLE.md, defasado).
 * Registrar = decisão consciente de carregar o débito; não-registrar = bug.
 *
 * O que este teste GARANTE:
 *   1. Toda colisão presente no disco está em `collisions_grandfathered`
 *      — colisão NÃO registrada FALHA o teste (lista número + arquivos).
 *   2. Todo número listado em `collisions_grandfathered` realmente colide no disco
 *      — entrada órfã/stale (número que não tem mais 2+ arquivos) FALHA o teste
 *        (ratchet "só encolhe": ao resolver a colisão, remova a entrada do baseline).
 *
 * Teste PURO de arquivo: lê do disco + assert. Sem banco, sem rede, determinístico.
 * Mecânica espelha AdrFrontmatterLinterTest/AdrFrontmatterTest: base_path() +
 * glob() + Symfony\Component\Yaml\Yaml.
 */

const COLLISION_ADR_DIR       = 'memory/decisions';
const COLLISION_BASELINE_FILE = 'governance/adr-collisions-baseline.json';

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
 * Lê `collisions_grandfathered: [...]` de governance/adr-collisions-baseline.json
 * (fonte machine-readable única — mandato ADR 0274 §3; substitui o frontmatter
 * `numbering_collisions` do _INDEX-LIFECYCLE.md, que estava defasado — total:119 vs
 * disco) e devolve o conjunto de números-colisão REGISTRADOS, 4 dígitos zero-padded.
 *
 * O baseline é o ratchet anti-bifurcação ("só encolhe"): ao resolver uma colisão,
 * remove-se a entrada daqui no MESMO PR — é o que o 2º teste (sem-órfã) enforça.
 * Aceita int (101) ou string ('0101') por robustez; re-padroniza com sprintf('%04d').
 *
 * @return list<string>  ex: ['0101', '0102', '0119', '0195', '0235']
 */
function colisoesRegistradas(): array
{
    $path = base_path(COLLISION_BASELINE_FILE);
    $conteudo = file_get_contents($path);
    if ($conteudo === false) {
        return [];
    }

    $json = json_decode($conteudo, true);

    $lista = (is_array($json) && isset($json['collisions_grandfathered']) && is_array($json['collisions_grandfathered']))
        ? $json['collisions_grandfathered']
        : [];

    $normalizados = [];
    foreach ($lista as $valor) {
        // Extrai só os dígitos (cobre int 101, string '0101', etc.) e re-padroniza.
        // is_scalar guard: $valor é mixed (vem do JSON) — evita cast inseguro de array→string.
        $bruto = is_scalar($valor) ? (string) $valor : '';
        $digitos = preg_replace('/\D/', '', $bruto) ?? '';
        if ($digitos === '') {
            continue;
        }
        $normalizados[] = sprintf('%04d', (int) $digitos);
    }

    return array_values(array_unique($normalizados));
}

it('toda colisão de número de ADR no disco está em adr-collisions-baseline.collisions_grandfathered', function () {
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
        "Colisão de número de ADR NÃO registrada (ADR 0028/0274 violada).\n" .
        "Append-only Tier 0 proíbe renumerar ADR aceita — então: registre o número em\n" .
        "governance/adr-collisions-baseline.json no array `collisions_grandfathered`\n" .
        "OU renumere o arquivo ainda-não-mergeado ANTES do merge.\n  - " .
        implode("\n  - ", $naoRegistradas)
    );
});

it('todo número em collisions_grandfathered realmente colide no disco (sem entrada órfã)', function () {
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
        "Entrada órfã/stale em `collisions_grandfathered` de adr-collisions-baseline.json.\n" .
        "Um número só deve constar ali enquanto a colisão existir no disco.\n" .
        "Remova a entrada do baseline (a colisão foi resolvida) OU confirme que os 2+\n" .
        "arquivos ainda existem.\n  - " .
        implode("\n  - ", $orfas)
    );
});
