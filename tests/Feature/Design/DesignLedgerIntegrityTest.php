<?php

declare(strict_types=1);

/**
 * Pest test PURO de arquivo — integridade do Design Request Ledger (file-based).
 *
 * O Design Request Ledger vive em `memory/governance/design-requests/` (arquivos git,
 * NÃO MCP — o Claude Design / Cowork só lê arquivos). Estrutura:
 *   - LEDGER.md          — índice (tabela markdown REQ | data | tela | status | delta | resultado).
 *   - _TEMPLATE-REQ.md   — molde com placeholders (REQ-NNN, <...>, 2026-MM-DD).
 *   - REQ-NNN.md         — 1 por pedido (frontmatter req:/status:/... + delta + checkpoint + resultado).
 *
 * Camada de ENFORCEMENT — trava o ledger antes de virar zona quando o time MCP entrar.
 * Invariantes verificadas:
 *   (a) BIJEÇÃO  — todo REQ-*.md (exceto template) tem linha no LEDGER e vice-versa (match por ID).
 *   (b) IDENTIDADE — IDs REQ únicos; campo `req:` do frontmatter == nome do arquivo.
 *   (c) ANTI-VAZAMENTO — nenhum REQ real contém placeholder do template (copiou e esqueceu de preencher).
 *   (d) VOCABULÁRIO — `status` de cada REQ ∈ {received, processing, done}.
 *
 * Estado 2026-05-30: NÃO existe REQ-NNN.md real ainda (só _TEMPLATE-REQ.md + LEDGER vazio).
 * O teste PASSA vacuamente hoje, MAS protege o futuro. O caso vazio é tratado com elegância
 * e há asserts que PROVAM que a varredura roda mesmo com 0 REQs (template existe + NÃO conta como REQ).
 *
 * Teste determinístico, sem banco / business_id / rede. Mecanismo de frontmatter espelha
 * tests/Feature/Memory/AdrFrontmatterLinterTest.php (symfony/yaml).
 *
 * Refs: memory/governance/design-requests/LEDGER.md
 *       memory/decisions/proposals/design-request-ledger-incremental.md
 *       ADR 0236 (governança evolução doc design — "índice = fonte única")
 */

use Symfony\Component\Yaml\Yaml;

const DESIGN_LEDGER_DIR_REL = 'memory/governance/design-requests';

/** Vocabulário canon do campo `status:` (frontmatter _TEMPLATE-REQ.md linha 7). */
const REQ_STATUS_VALIDOS = ['received', 'processing', 'done'];

/**
 * Placeholders do _TEMPLATE-REQ.md que NUNCA podem sobrar num REQ real preenchido.
 * Pega o caso "copiou o template e esqueceu de trocar <...> / REQ-NNN / data".
 */
const REQ_PLACEHOLDERS_PROIBIDOS = [
    'REQ-NNN',     // id placeholder do template
    '<',           // qualquer <Mod>/<Tela>, <título curto>, <hash do commit>, ...
    'YYYY-MM-DD',  // formato de data genérico
    '2026-MM-DD',  // data placeholder do template (linha 2)
];

/**
 * Caminho absoluto da pasta do ledger. `base_path()` resolve worktrees / junctions
 * (mesmo mecanismo de CockpitPatternConformanceTest + AdrFrontmatterLinterTest).
 */
function designLedgerDir(): string
{
    return base_path(DESIGN_LEDGER_DIR_REL);
}

/**
 * Lista os arquivos REQ REAIS da pasta — `REQ-NNN.md` preenchidos.
 *
 * EXCLUI qualquer arquivo começando com `_` (ex: `_TEMPLATE-REQ.md`) — espelha o guard
 * `str_starts_with($name, '_')` do AdrFrontmatterLinterTest::adrsParaValidar(). Assim o
 * template (e futuros `_*.md` auxiliares) NUNCA é tratado como REQ real.
 *
 * @return array<array{path:string, name:string, id:string, content:string}>
 *         `id` = nome do arquivo sem extensão (ex: "REQ-001").
 */
function designReqsReais(): array
{
    $base = designLedgerDir();
    if (! is_dir($base)) {
        return [];
    }
    $arquivos = glob("$base/REQ-*.md") ?: [];

    $reqs = [];
    foreach ($arquivos as $path) {
        $name = basename($path, '.md');
        // Defesa em profundidade: `_TEMPLATE-REQ.md` já não casa com glob `REQ-*.md`,
        // mas qualquer `_*.md` auxiliar futuro também não pode contar como REQ real.
        if (str_starts_with($name, '_')) {
            continue;
        }
        $reqs[] = [
            'path'    => $path,
            'name'    => $name,
            'id'      => $name,
            'content' => (string) file_get_contents($path),
        ];
    }
    return $reqs;
}

/**
 * Extrai o frontmatter YAML entre `---` (idêntico ao extrairFrontmatter do AdrFrontmatterLinterTest).
 *
 * @return array{frontmatter:?array, body:string, raw:?string}
 */
function extrairFrontmatterReq(string $conteudo): array
{
    if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $conteudo, $m)) {
        return ['frontmatter' => null, 'body' => $conteudo, 'raw' => null];
    }
    try {
        $fm = Yaml::parse($m[1]);
    } catch (\Throwable $e) {
        return ['frontmatter' => null, 'body' => $m[2], 'raw' => $m[1]];
    }
    return ['frontmatter' => is_array($fm) ? $fm : null, 'body' => $m[2], 'raw' => $m[1]];
}

/**
 * Lê o LEDGER.md e devolve os IDs REQ-NNN citados na tabela markdown do índice.
 *
 * A tabela tem a forma `| REQ-001 | ... |`. Hoje a única linha-dado é `| _(vazio)_ | ... |`
 * (placeholder de "tabela vazia"), que NÃO casa o padrão REQ-NNN e portanto vira lista vazia.
 *
 * @return list<string> IDs únicos preservando ordem de aparição (ex: ["REQ-001", "REQ-002"]).
 */
function ledgerReqIds(): array
{
    $ledger = designLedgerDir() . '/LEDGER.md';
    if (! is_file($ledger)) {
        return [];
    }
    $conteudo = (string) file_get_contents($ledger);

    $ids = [];
    // Casa qualquer "REQ-NNN" (>=1 dígito) dentro de uma célula de tabela markdown.
    // `\b` evita casar prefixo de "REQ-NNN" placeholder (que tem letras, não dígitos).
    if (preg_match_all('/\|\s*(REQ-\d+)\b/', $conteudo, $m)) {
        foreach ($m[1] as $id) {
            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
    }
    return $ids;
}

// ─── Sanidade da varredura — PROVA que o scan funciona mesmo com 0 REQs ───────────────

it('SCAN: a pasta do ledger e o LEDGER.md existem (pré-condição da governança)', function () {
    expect(is_dir(designLedgerDir()))->toBeTrue(
        'Pasta do Design Request Ledger ausente: ' . DESIGN_LEDGER_DIR_REL,
    );
    expect(is_file(designLedgerDir() . '/LEDGER.md'))->toBeTrue(
        'LEDGER.md (índice "já processei?") ausente em ' . DESIGN_LEDGER_DIR_REL,
    );
});

it('SCAN: _TEMPLATE-REQ.md existe e NÃO é contado como REQ real (prova o filtro com 0 REQs)', function () {
    // (1) O molde tem que existir — sem ele não há como abrir REQ novo.
    expect(is_file(designLedgerDir() . '/_TEMPLATE-REQ.md'))->toBeTrue(
        '_TEMPLATE-REQ.md (molde de pedido) ausente — não dá pra criar REQ-NNN.',
    );

    // (2) O template NÃO pode aparecer entre os REQs reais. Este assert sozinho prova que a
    //     varredura está funcionando mesmo HOJE (0 REQs reais): o filtro `_`-prefix segura o molde.
    $ids = array_column(designReqsReais(), 'id');
    expect($ids)->not->toContain('_TEMPLATE-REQ');
    expect($ids)->not->toContain('TEMPLATE-REQ');
});

// ─── (a) BIJEÇÃO REQ-file ⇄ linha no LEDGER ───────────────────────────────────────────

it('(a) todo REQ-*.md real tem linha correspondente na tabela do LEDGER.md', function () {
    $idsLedger = ledgerReqIds();
    $faltando = [];
    foreach (designReqsReais() as $req) {
        if (! in_array($req['id'], $idsLedger, true)) {
            $faltando[] = $req['id'];
        }
    }
    expect($faltando)->toBeEmpty(
        'REQ com arquivo mas SEM linha no LEDGER (atualizar a tabela faz parte do "pronto"):'
        . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $faltando),
    );
});

it('(a) toda linha REQ-NNN do LEDGER.md tem arquivo REQ-NNN.md correspondente', function () {
    $idsArquivo = array_column(designReqsReais(), 'id');
    $orfaos = [];
    foreach (ledgerReqIds() as $idLedger) {
        if (! in_array($idLedger, $idsArquivo, true)) {
            $orfaos[] = $idLedger;
        }
    }
    expect($orfaos)->toBeEmpty(
        'LEDGER cita REQ que NÃO tem arquivo (linha órfã — append-only quebrado):'
        . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $orfaos),
    );
});

// ─── (b) IDENTIDADE — IDs únicos + frontmatter `req:` casa com filename ────────────────

it('(b) IDs REQ são únicos (sem dois arquivos REQ-NNN com mesmo número)', function () {
    $ids = array_column(designReqsReais(), 'id');
    $duplicados = array_keys(array_filter(array_count_values($ids), fn ($n) => $n > 1));
    expect($duplicados)->toBeEmpty(
        'IDs REQ duplicados na pasta: ' . implode(', ', $duplicados),
    );
});

it('(b) campo `req:` do frontmatter de cada REQ bate com o nome do arquivo', function () {
    $erros = [];
    foreach (designReqsReais() as $req) {
        $fm = extrairFrontmatterReq($req['content'])['frontmatter'];
        if ($fm === null) {
            $erros[] = "{$req['id']}: sem frontmatter YAML válido (bloco --- ausente ou inválido)";
            continue;
        }
        if (! array_key_exists('req', $fm)) {
            $erros[] = "{$req['id']}: frontmatter sem campo `req:`";
            continue;
        }
        if ((string) $fm['req'] !== $req['id']) {
            $erros[] = "{$req['id']}: frontmatter req=`{$fm['req']}` divergente do nome do arquivo";
        }
    }
    expect($erros)->toBeEmpty(
        'Identidade req:/filename violada:' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $erros),
    );
});

// ─── (c) ANTI-VAZAMENTO — nenhum placeholder do template num REQ real ─────────────────

it('(c) nenhum REQ real contém placeholder do template (vazou sem preencher)', function () {
    $erros = [];
    foreach (designReqsReais() as $req) {
        $achados = [];
        foreach (REQ_PLACEHOLDERS_PROIBIDOS as $placeholder) {
            if (str_contains($req['content'], $placeholder)) {
                $achados[] = $placeholder;
            }
        }
        if ($achados !== []) {
            $erros[] = "{$req['id']}: placeholder(s) não preenchido(s) [" . implode(', ', $achados) . ']';
        }
    }
    expect($erros)->toBeEmpty(
        'REQ com placeholder do _TEMPLATE-REQ.md (copiou o molde e esqueceu de preencher):'
        . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $erros),
    );
});

// ─── (d) VOCABULÁRIO — status ∈ {received, processing, done} ──────────────────────────

it('(d) status de cada REQ está no vocabulário {received, processing, done}', function () {
    $erros = [];
    foreach (designReqsReais() as $req) {
        $fm = extrairFrontmatterReq($req['content'])['frontmatter'];
        if ($fm === null) {
            // Frontmatter ausente/ inválido já é reportado em (b); aqui só pula.
            continue;
        }
        if (! array_key_exists('status', $fm)) {
            $erros[] = "{$req['id']}: frontmatter sem campo `status:`";
            continue;
        }
        if (! in_array($fm['status'], REQ_STATUS_VALIDOS, true)) {
            $erros[] = "{$req['id']}: status inválido `{$fm['status']}` "
                . '(use received | processing | done)';
        }
    }
    expect($erros)->toBeEmpty(
        'Vocabulário de status violado:' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $erros),
    );
});
