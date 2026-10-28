<?php

declare(strict_types=1);

/**
 * GUARD — `it(...)->afterEach(fn)` nao roda a limpeza no Pest 4.
 *
 * `afterEach` nao e metodo do `TestCall` (vendor/pestphp/pest/src/PendingCalls/TestCall.php:728):
 * a chamada cai no `__call` e vira chain. A chain roda antes do corpo do teste e chama a
 * funcao GLOBAL `afterEach()` (src/Functions.php:174), que registra a closure sob a chave
 * de `Backtrace::testFile()`. Como o corpo e chamado por `call_user_func_array` sem
 * qualificacao dentro de `namespace Pest\Concerns` (src/Concerns/Testable.php:5,509), o
 * frame nao tem `file` e o backtrace para cedo (src/Support/Backtrace.php:25-35): a chave
 * vira `TestCaseMethodFactory.php`. O `tearDown` so le a chave do arquivo de teste
 * (Testable.php:305), entao a limpeza nunca executa e o teste fica verde.
 * A forma por teste e `->after()` (TestCall::after). Ela roda tambem quando o corpo chamou
 * `markTestSkipped` (o Pest marca `__ran` antes do corpo), entao a limpeza tem de aguentar
 * o caso em que o teste nao chegou a criar nada.
 * Medido no #10120 (ServiceOrderIndexStageFilterTest so ficou verde com ->after()) e no
 * CT 100 em 2026-09-08 (comentario em AssetManagement/Tests/Feature/SmokeRoutesTest.php).
 * Em 2026-10-28 eram 237 sites em 55 arquivos; trocados nos PRs #10135 #10136 #10139 #10140.
 *
 * O predicado e a linha que COMECA com `})->afterEach(` — o fecho da closure de um
 * it()/test()/describe(). Ficam de fora, de proposito, as formas validas do Pest:
 * a funcao global `afterEach(` de nivel de arquivo e `uses(...)->afterEach(`, que e
 * metodo real do UsesCall e nunca comeca a linha com `})`.
 *
 * FP MEDIDO ANTES DE INSTALAR (2026-10-28, `git grep` em origin/main, todos os *.php):
 *   237 linhas com `)->afterEach(` · 237 comecam com `})->afterEach(` · 0 sao `uses(`.
 *   Nenhuma classe do Pest declara `afterEach` alem do UsesCall e da funcao global.
 *   => 0 falso-positivo.
 *
 * O que o guard NAO pega (0 casos hoje, `git grep '^\s*->afterEach\('` = 0): `->afterEach(`
 * em linha propria, depois de outro metodo (`})->group('x')->afterEach(`), ou num it() de
 * arrow function numa linha so (`it('x', fn () => ...)->afterEach(`).
 */

function linhasComAfterEachEncadeado(string $codigo): array
{
    $hits = [];
    foreach (preg_split('/\R/', $codigo) ?: [] as $i => $linha) {
        if (preg_match('/^\s*\}\)->afterEach\(/', $linha) === 1) {
            $hits[] = $i + 1;
        }
    }

    return $hits;
}

/** .php de teste sob as raizes do repo (Modules/<X>/Tests e tests/). */
function arquivosDeTestePhp(): array
{
    $raizes = array_merge([base_path('tests')], glob(base_path('Modules/*/Tests'), GLOB_ONLYDIR) ?: []);
    $out = [];
    foreach ($raizes as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
    }

    return $out;
}

// Os fixtures montam `})` por concatenacao para este arquivo nao casar a propria regra.
$fecho = '}' . ')';

it('BITE: acusa a limpeza encadeada num it()', function () use ($fecho) {
    $ruim = "<?php\nit('x', function () {\n    expect(1)->toBe(1);\n" . $fecho . "->afterEach(fn () => limpa());\n";

    expect(linhasComAfterEachEncadeado($ruim))->toBe([4]);
});

it('BITE: acusa tambem dentro de describe (linha indentada)', function () use ($fecho) {
    $ruim = "<?php\ndescribe('d', function () {\n    it('x', function () {\n    " . $fecho . "->afterEach(function () {\n    });\n});\n";

    expect(linhasComAfterEachEncadeado($ruim))->toBe([4]);
});

it('BITE: nao acusa ->after(), afterEach() de arquivo nem uses()->afterEach()', function () use ($fecho) {
    $boa = "<?php\nuses(Tests\TestCase::class)->afterEach(fn () => null);\n"
        . "afterEach(function () {\n});\n"
        . "it('x', function () {\n" . $fecho . "->after(fn () => limpa());\n";

    expect(linhasComAfterEachEncadeado($boa))->toBe([]);
});

it('nenhum teste do repo encadeia ->afterEach() num it/test/describe', function () {
    $arquivos = arquivosDeTestePhp();
    expect(count($arquivos))->toBeGreaterThan(1000); // anti-vacuo: a varredura achou o corpus

    $violacoes = [];
    foreach ($arquivos as $arquivo) {
        foreach (linhasComAfterEachEncadeado((string) file_get_contents($arquivo)) as $n) {
            $violacoes[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $arquivo) . ':' . $n;
        }
    }

    expect($violacoes)->toBe([], "Troque `})->afterEach(` por `})->after(` (o afterEach encadeado nao roda no Pest 4):\n" . implode("\n", $violacoes));
});
