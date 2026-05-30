<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).
// NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * Design Index SINGLE-SOURCE GUARD — "índice = fonte única" (ADR 0236).
 *
 * O ADR 0236 (governanca-evolucao-doc-design) define a máquina onde TODO doc de
 * design é apontado pelo índice-mestre `INDEX-DESIGN-MEMORIAS.md`, e esse índice
 * NUNCA pode ter link quebrado/órfão. Antes deste teste não havia gate — um link
 * stale (apontando pra `proposals/governanca-evolucao-doc-design.md`, que já virou
 * o ADR aceito 0236) passou despercebido.
 *
 * Invariantes (puras de arquivo — lê disco + assert, SEM banco/business_id/rede):
 *   (a) HARD — todo link markdown LOCAL relativo dentro do INDEX RESOLVE (o arquivo
 *       destino existe). Caminhos relativos resolvidos a partir do diretório do INDEX.
 *       Ignora http(s)://, mailto: e âncoras puras (#...). Falha listando os quebrados.
 *       (Esta asserção teria pego o link stale.)
 *   (b) HARD (curado/determinístico) — todo doc CANÔNICO de leitura-obrigatória que o
 *       próprio INDEX designa (§1 ORDEM DE LEITURA + §2 ÍNDICE POSITIVO) está
 *       referenciado pelo nome de arquivo em ALGUM lugar do INDEX. Decisão deliberada
 *       de escopo (ver bloco "DECISÃO (b)" abaixo) — varredura total de órfãos é ruidosa.
 *
 * Refs:
 *   - memory/decisions/0236-governanca-evolucao-doc-design.md
 *   - memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md
 *   - memory/sessions/2026-05-30-screen-grade-metodo-estado-arte.md
 *
 * @group docs
 * @group design
 */

// Raiz do repo: este arquivo vive em tests/Feature/Design/ → sobe 3 níveis.
// Espelha WaveZ2DocumentationGuardTest (resolução por __DIR__, sem depender de base_path()/app bootada).
const DESIGN_INDEX_ROOT = __DIR__ . '/../../..';

// Caminho relativo (a partir da raiz) do índice-mestre e do seu diretório.
const DESIGN_INDEX_REL = 'memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md';
const DESIGN_INDEX_DIR_REL = 'memory/requisitos/_DesignSystem';

/**
 * DECISÃO (b) — conjunto CURADO em vez de varredura total de órfãos.
 *
 * Medição no estado atual (2026-05-30): uma checagem "todo *.md consumível em
 * prototipo-ui/ + _DesignSystem/ tem que estar no índice" acusa ~24 órfãos
 * LEGÍTIMOS — templates (BRIEFING-TEMPLATE, CHARTER-TEMPLATE), changelogs, notes
 * de sessão (COWORK_NOTES.amendment-*, COMPARISON-KB975-*), e vários RUNBOOKs que
 * o índice cita por brace-expansion (`RUNBOOK-{replicar-prototipo-cowork,onda-cowork,
 * design-deep,inertia-defer-pattern}.md`) — forma que um grep de basename literal
 * não casa. Isso tornaria a varredura total RUIDOSA (falha hoje em doc auxiliar
 * legítimo), violando determinismo.
 *
 * Escolha: assertar que o conjunto CANÔNICO que o índice se compromete a apontar
 * (§1 ORDEM DE LEITURA + §2 ÍNDICE POSITIVO — leitura-obrigatória) está, de fato,
 * referenciado pelo NOME DE ARQUIVO no índice. É exatamente a garantia "fonte única
 * aponta pro canon", sem falso-positivo. Cada um destes é citado por basename no
 * índice HOJE (verificado), então o teste PASSA no estado atual.
 *
 * NOTA de precisão: só `PT-01-Lista.md` entra. PT-02..PT-05 EXISTEM como arquivo,
 * mas o índice (§2b) declara explicitamente que ainda NÃO são goldens e os cita só
 * por rótulo curto ("PT-02 Form/Drawer"), nunca por filename — incluí-los aqui daria
 * falso-negativo. Quando virarem canon e forem listados por nome, adicionar à lista.
 */
const DESIGN_INDEX_CANON_DOCS = [
    // §1 ORDEM DE LEITURA (sempre, antes de qualquer tela)
    'PRE-FLIGHT-TELA.md',
    'GOLDEN-REFERENCE.md',
    'REGISTRY_DS_COMPONENTES.md',
    'LICOES_F3_FINANCEIRO_REJEITADO.md',
    'SCREEN-GRADE-METODO.md',
    // §2 ÍNDICE POSITIVO — padrões a copiar / validação
    'PT-01-Lista.md',
    'PRE-MERGE-UI.md',
    'SPEC.md',
    'framework-15-dimensoes.md',
    'pageheader-matriz-diferencas.md',
    'LEDGER.md',
];

beforeEach(function () {
    // Skip gracioso quando filesystem do repo não está acessível (CI ephemeral) —
    // mesmo padrão defensivo do WaveZ2DocumentationGuardTest.
    if (! is_dir(DESIGN_INDEX_ROOT)) {
        $this->markTestSkipped('Filesystem do repo não acessível (CI ephemeral).');
    }
    if (! is_file(DESIGN_INDEX_ROOT . '/' . DESIGN_INDEX_REL)) {
        $this->markTestSkipped('INDEX-DESIGN-MEMORIAS.md ausente nesta branch.');
    }
});

/**
 * Lê o conteúdo do índice-mestre.
 */
function designIndexContent(): string
{
    return (string) file_get_contents(DESIGN_INDEX_ROOT . '/' . DESIGN_INDEX_REL);
}

/**
 * Extrai os ALVOS (URL part) de TODOS os links markdown `[texto](alvo)` do índice.
 * Regex robusto pedido pela tarefa: \[([^\]]+)\]\(([^)]+)\).
 *
 * @return string[] lista de alvos crus (na ordem em que aparecem)
 */
function designIndexLinkTargets(string $content): array
{
    preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $matches);

    return $matches[2] ?? [];
}

/**
 * Normaliza um caminho relativo resolvendo `.` e `..` SEM tocar o disco
 * (realpath() falharia/retornaria false pra inexistente — aqui queremos o caminho
 * candidato pra depois testar file_exists e reportar o que faltou).
 */
function designNormalizePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $isAbsolute = str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:/#', $path);

    $segments = explode('/', $path);
    $out = [];
    foreach ($segments as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..') {
            if (! empty($out) && end($out) !== '..') {
                array_pop($out);
            } elseif (! $isAbsolute) {
                $out[] = '..';
            }

            continue;
        }
        $out[] = $seg;
    }

    $prefix = $isAbsolute ? (str_starts_with($path, '/') ? '/' : '') : '';

    return $prefix . implode('/', $out);
}

// ─── (a) HARD — todo link LOCAL relativo RESOLVE ─────────────────────────────

it('(a) HARD: todo link markdown LOCAL do índice resolve (arquivo destino existe)', function () {
    $content = designIndexContent();
    $indexDir = DESIGN_INDEX_ROOT . '/' . DESIGN_INDEX_DIR_REL;

    $broken = [];
    foreach (designIndexLinkTargets($content) as $rawTarget) {
        $target = trim($rawTarget);

        // Ignora links externos e não-arquivo: http(s)://, mailto:, âncora pura (#...).
        if (preg_match('#^(https?:)//#i', $target)) {
            continue;
        }
        if (str_starts_with($target, 'mailto:')) {
            continue;
        }
        if (str_starts_with($target, '#')) {
            continue;
        }

        // Descarta fragmento de âncora e/ou "title" opcional do destino:
        //   ../foo.md#secao   ->  ../foo.md
        //   ../foo.md "Título" ->  ../foo.md
        $target = preg_split('/\s+/', $target, 2)[0];      // corta ` "title"`
        $target = explode('#', $target, 2)[0];             // corta `#ancora`
        $target = trim($target);

        // Após limpar, pode sobrar vazio (ex.: link era só "#") — já tratado acima,
        // mas guarda defensiva.
        if ($target === '') {
            continue;
        }

        // Resolve relativo ao DIRETÓRIO do índice (regra do ADR 0236).
        $candidate = designNormalizePath($indexDir . '/' . $target);

        if (! file_exists($candidate)) {
            $broken[] = $rawTarget;
        }
    }

    expect($broken)->toBe(
        [],
        "Links LOCAIS quebrados no INDEX-DESIGN-MEMORIAS.md (destino não existe):"
        . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $broken)
        . PHP_EOL . 'Corrija o link ou crie o arquivo. (ADR 0236: índice = fonte única, zero link órfão.)',
    );
});

// ─── (b) HARD (curado) — docs CANÔNICOS estão referenciados no índice ────────

it('(b) HARD: todo doc CANÔNICO de leitura-obrigatória está referenciado no índice (zero órfão canon)', function () {
    $content = designIndexContent();

    $orphans = [];
    foreach (DESIGN_INDEX_CANON_DOCS as $basename) {
        // "Referenciado" = nome do arquivo aparece em ALGUM lugar do texto do índice
        // (link markdown OU menção em prosa/tabela). str_contains é determinístico e
        // independe de o índice usar `[texto](path)` ou backticks/prosa.
        if (! str_contains($content, $basename)) {
            $orphans[] = $basename;
        }
    }

    expect($orphans)->toBe(
        [],
        "Docs CANÔNICOS de design NÃO referenciados no índice-mestre (órfãos):"
        . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $orphans)
        . PHP_EOL . 'Todo doc canon (ordem-de-leitura/índice-positivo) DEVE estar no índice. '
        . 'Adicione a entrada em INDEX-DESIGN-MEMORIAS.md. (ADR 0236: índice = fonte única.)',
    );
});

// ─── Sanidade — o índice e os alvos foram realmente parseados ────────────────

it('SANIDADE: índice tem links markdown e a lista canon não está vazia (guarda anti-regex-quebrado)', function () {
    $content = designIndexContent();
    $targets = designIndexLinkTargets($content);

    // Se o regex parar de casar (ex.: alguém reformatar o índice), os HARD acima
    // passariam vacuamente — esta guarda evita "verde falso".
    expect(count($targets))->toBeGreaterThan(0, 'Nenhum link markdown extraído do índice (regex quebrou?).');
    expect(DESIGN_INDEX_CANON_DOCS)->not->toBeEmpty('Lista de docs canônicos vazia.');
});
