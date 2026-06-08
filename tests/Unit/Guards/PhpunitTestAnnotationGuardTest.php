<?php

declare(strict_types=1);

/**
 * PHPUnit Test Annotation Guard — varredura estática garantindo que nenhum
 * arquivo `*Test.php` use `/** @test *\/` doc-comment.
 *
 * **Por quê:** PHPUnit 12 IGNORA SILENCIOSAMENTE doc-comment `@test` —
 * tests com essa anotação NÃO RODAM (sem error, sem warning, sem skip).
 * Falsa cobertura é o pior caso possível em test suite. Trocar por
 * `#[\PHPUnit\Framework\Attributes\Test]` (PHP 8 attribute).
 *
 * **Histórico de recidiva:**
 * - PR #393: corrigiu 6 tests com `/** @test *\/`
 * - PR #437: corrigiu mais 35 tests com mesmo padrão
 * - Sem guard automático, vai voltar.
 *
 * **Regra:** zero ocorrências de `/** @test *\/` em arquivos `*Test.php`
 * dentro de `tests/` ou `Modules/<X>/Tests/`.
 *
 * **Filtragem:** SOMENTE `*Test.php` — não toca código de produção pra evitar
 * falso-positivo (algum phpdoc legítimo poderia mencionar `@test`).
 */

use Symfony\Component\Finder\Finder;

/**
 * Coleta arquivos `*Test.php` em tests/ + Modules/<X>/Tests/.
 *
 * @return array<int, array{path: string, relpath: string, content: string}>
 */
function phpunitGuardCollectFiles(): array
{
    $base = realpath(__DIR__.'/../../..');
    if ($base === false) {
        return [];
    }

    $paths = [];

    if (is_dir($base.'/tests')) {
        $paths[] = $base.'/tests';
    }

    if (is_dir($base.'/Modules')) {
        foreach (glob($base.'/Modules/*/Tests', GLOB_ONLYDIR) ?: [] as $modTests) {
            $paths[] = $modTests;
        }
    }

    if (empty($paths)) {
        return [];
    }

    $finder = (new Finder)
        ->in($paths)
        ->name('*Test.php')
        ->files();

    $files = [];
    foreach ($finder as $file) {
        $relpath = str_replace('\\', '/', substr($file->getRealPath(), strlen($base) + 1));

        // Não auditar este próprio guard — phpdoc dele cita o pattern literal
        if (str_ends_with($relpath, 'PhpunitTestAnnotationGuardTest.php')) {
            continue;
        }

        $files[] = [
            'path'    => $file->getRealPath(),
            'relpath' => $relpath,
            'content' => $file->getContents(),
        ];
    }

    return $files;
}

/**
 * Procura `/** @test *\/` (single-line ou multi-line) em cada arquivo.
 *
 * @param  array<int, array{path: string, relpath: string, content: string}>  $files
 * @return array<int, string>
 */
function phpunitGuardScan(array $files): array
{
    $violations = [];

    // Pattern 1: doc-comment single-line  /** @test */
    $patternSingle = '/\/\*\*\s*@test\s*\*\//';

    // Pattern 2: doc-comment multi-line
    //   /**
    //    * @test
    //    */
    // (qualquer indentação, qualquer linha extra antes/depois do @test no bloco doc)
    $patternMulti = '/\/\*\*[^*\/]*?\*\s*@test\s*\R[^*]*?\*\//s';

    foreach ($files as $file) {
        $content = $file['content'];

        $countSingle = preg_match_all($patternSingle, $content, $mSingle);
        $countMulti = preg_match_all($patternMulti, $content, $mMulti);

        $total = ($countSingle ?: 0) + ($countMulti ?: 0);

        if ($total > 0) {
            $violations[] = sprintf('%s: %d ocorrência(s) de /** @test */', $file['relpath'], $total);
        }
    }

    return $violations;
}

it('guard: nenhum *Test.php usa /** @test */ doc-comment (PHPUnit 12 silent skip)', function () {
    $files = phpunitGuardCollectFiles();
    expect($files)->not->toBeEmpty('Nenhum arquivo *Test.php encontrado — guard test inerte');

    $violations = phpunitGuardScan($files);

    if (! empty($violations)) {
        $msg = "VIOLAÇÃO: /** @test */ doc-comment em PHPUnit 12 NÃO RODA tests.\n\n"
            ."Trocar por: #[\\PHPUnit\\Framework\\Attributes\\Test]\n\n"
            ."Histórico recidiva: PR #393 (6 tests), PR #437 (35 tests).\n"
            ."Auditoria 2026-05-10 reforçou guard automático.\n\n"
            ."Violações encontradas:\n  - "
            .implode("\n  - ", $violations);

        expect($violations)->toBeEmpty($msg);
    }

    expect($violations)->toBeEmpty();
})->group('guard');
