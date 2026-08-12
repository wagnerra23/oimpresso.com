<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).
// NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * ARCHITECTURE TEST — direção de dependência: `app/` ↛ `Modules/`.
 *
 * A REGRA: num monolito modular a seta vai **módulo → núcleo**. O inverso acopla o
 * núcleo ao ciclo de vida de um módulo — desligar ou remover o módulo quebra o app.
 * Com PSR-4 no root (`Modules\` → `Modules/`, sem merge-plugin), classe de módulo
 * ausente é class-not-found em runtime.
 *
 * O DEFEITO MEDIDO (2026-08-12): **60 imports em 31 arquivos** de `app/` dependem de
 * `Modules/`. Alvos: Jana 24 arquivos · OficinaAuto 9 · NfeBrasil 2 · demais 1.
 * O caso emblemático era `app/Concerns/HasBusinessScope.php` — o trait multi-tenant
 * **Tier 0 do núcleo** — importando o scope de dentro de um módulo; **curado no mesmo
 * dia** movendo os dois scopes pra `App\Scopes\`. Dois alvos nem existem mais como
 * módulo: `Modules\Ecommerce` e `Modules\CustomDashboard`.
 *
 * FORWARD-ONLY (ADR 0275): a dívida atual está congelada em
 * `governance/dependency-direction-baseline.json`. Este teste morde quem **PIORA** —
 * arquivo NOVO de `app/` importando módulo. Backfill em massa do legado é
 * anti-padrão registrado (proibicoes.md §5 2026-07-12), então a baseline só DESCE.
 *
 * POR QUE TESTE E NÃO FERRAMENTA: Deptrac/PHPat resolveriam o eixo `use`, mas são
 * dependência nova (exige ADR) e nenhuma delas cobre os outros dois eixos deste
 * problema — chave de global scope (`get_class`) e FQCN gravado em coluna de banco.
 * Pest 4 já está instalado; zero dependência nova.
 *
 * Refs:
 *   - memory/sessions/2026-08-12-arte-shared-kernel-laravel.md (o parecer que originou)
 *   - memory/proibicoes.md §"Multi-tenant Tier 0 IRREVOGÁVEL" + ADR 0093
 *   - governance/dependency-direction-baseline.json (a dívida congelada)
 *   - tests/Feature/Architecture/MultiTenantScopeArchitectureTest.php (idioma da baseline)
 *
 * @group architecture
 */

const DD_ROOT = __DIR__ . '/../../..';
const DD_BASELINE_PATH = DD_ROOT . '/governance/dependency-direction-baseline.json';

/**
 * Extrai os módulos importados por um conteúdo PHP.
 *
 * Só `use Modules\X\…` em LINHA DE CÓDIGO. Docblock/comentário/string não contam —
 * `use` dentro de comentário é prosa, e tratar prosa como acoplamento foi o defeito
 * medido em proibicoes.md §5 2026-08-10 (comentário lido como import).
 *
 * @return list<string> nomes de módulo, sem repetição, ordenados
 */
function ddModulosImportados(string $conteudo): array
{
    $achados = [];
    foreach (preg_split('/\R/', $conteudo) as $linha) {
        // ^\s*use — o `*` ou `//` de comentário quebra o casamento por construção.
        if (preg_match('/^\s*use\s+Modules[\\\\\/]([A-Z][A-Za-z]*)/', $linha, $m) === 1) {
            $achados[$m[1]] = true;
        }
    }
    $mods = array_keys($achados);
    sort($mods);

    return $mods;
}

/** @return array<string,mixed> */
function ddBaseline(): array
{
    $raw = file_get_contents(DD_BASELINE_PATH);
    expect($raw)->not->toBeFalse('baseline não encontrada: ' . DD_BASELINE_PATH);

    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Varre `app/` e devolve os arquivos que importam de `Modules/`.
 *
 * @return list<string> caminhos relativos à raiz, ordenados
 */
function ddInfratores(): array
{
    $base = realpath(DD_ROOT . '/app');
    expect($base)->not->toBeFalse('app/ não encontrado — cwd errado?');

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    $out = [];
    foreach ($it as $arquivo) {
        if (! $arquivo->isFile() || $arquivo->getExtension() !== 'php') {
            continue;
        }
        $conteudo = file_get_contents($arquivo->getPathname());
        if ($conteudo === false || ddModulosImportados($conteudo) === []) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($arquivo->getPathname(), strlen(realpath(DD_ROOT)) + 1));
        $out[] = $rel;
    }
    sort($out);

    return $out;
}

test('o scanner ENXERGA a árvore (controle positivo — sem isto, lista vazia é indistinguível de não-execução)', function () {
    // LC-13: "0 infratores" só é notícia boa se o scanner conseguiu percorrer.
    $todos = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath(DD_ROOT . '/app'), FilesystemIterator::SKIP_DOTS));
    foreach ($it as $a) {
        if ($a->isFile() && $a->getExtension() === 'php') {
            $todos++;
        }
    }
    expect($todos)->toBeGreaterThan(300, 'app/ deveria ter centenas de .php — varredura não percorreu');
});

test('BITE: import de módulo em código é detectado; em comentário NÃO', function () {
    // ⚠️ FIXTURE — este literal REPRESENTA uma violação; não é um import real deste arquivo.
    // Um codemod de rename de namespace já o reescreveu uma vez (2026-08-12), o que zerou a
    // asserção sem quebrar sintaxe. Se um rename futuro tocar aqui, é a fixture que está
    // sendo comida — reverta a linha, não o teste.
    expect(ddModulosImportados('<?php' . PHP_EOL . 'use Modules\Jana\Services\Exemplo;'))
        ->toBe(['Jana'], 'MORDE: import real tem que ser visto');

    expect(ddModulosImportados('<?php' . PHP_EOL . 'use Modules\Jana\X; use Modules\Repair\Y;'))
        ->toBe(['Jana'], 'só o `use` em início de linha conta (o 2º é continuação da mesma linha)');

    // CONTROLE NEGATIVO — prosa não é acoplamento (proibicoes.md §5 2026-08-10)
    expect(ddModulosImportados('<?php' . PHP_EOL . ' * use Modules\Jana\X; (morava aqui até 2026-07)'))
        ->toBe([], 'docblock não é import');
    expect(ddModulosImportados('<?php' . PHP_EOL . '// use Modules\Jana\X;'))
        ->toBe([], 'comentário não é import');
    expect(ddModulosImportados('<?php' . PHP_EOL . 'use Illuminate\Support\Str;'))
        ->toBe([], 'import de vendor não é violação de direção');
});

test('CATRACA: nenhum arquivo NOVO de app/ importa de Modules/', function () {
    $baseline = ddBaseline();
    $isentos = array_merge($baseline['grandfathered'], $baseline['allowlist']);
    $novos = array_values(array_diff(ddInfratores(), $isentos));

    expect($novos)->toBe([], count($novos) . " arquivo(s) NOVO(s) de app/ importando de Modules/.\n"
        . "A seta tem que ir módulo→núcleo. Opções:\n"
        . "  (a) mover o símbolo compartilhado pra app/ (é o caminho canônico);\n"
        . "  (b) inverter via interface/contrato em app/, com o módulo implementando;\n"
        . "  (c) se for dívida consciente, entrar em governance/dependency-direction-baseline.json"
        . " > allowlist COM razão declarada.\n"
        . "Arquivos:\n  - " . implode("\n  - ", $novos));
});

test('a baseline não cita arquivo que já foi curado (catraca só desce)', function () {
    $baseline = ddBaseline();
    $infratores = ddInfratores();
    $curados = array_values(array_diff($baseline['grandfathered'], $infratores));

    // Não reprova: curar é o objetivo. Mas avisa alto pra baseline encolher no mesmo PR.
    if ($curados !== []) {
        fwrite(STDERR, PHP_EOL . '[dependency-direction] ' . count($curados)
            . " arquivo(s) da baseline JÁ FORAM CURADOS — remova do JSON:\n  - "
            . implode("\n  - ", $curados) . PHP_EOL);
    }

    expect(true)->toBeTrue();
});

test('a baseline não cita arquivo inexistente (drift de path)', function () {
    $baseline = ddBaseline();
    $fantasmas = array_values(array_filter(
        $baseline['grandfathered'],
        fn (string $p): bool => ! file_exists(DD_ROOT . '/' . $p),
    ));

    expect($fantasmas)->toBe([], "baseline cita arquivo que não existe mais — regenerar:\n  - "
        . implode("\n  - ", $fantasmas));
});
