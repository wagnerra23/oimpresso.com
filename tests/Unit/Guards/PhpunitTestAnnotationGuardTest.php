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
 * - 2026-06: 39 tests (Ponto CLT + Contact/Cliente/CpfCnpj) escapavam — o regex
 *   antigo só pegava `/** @test *\/` puro; furava em `/** @test descrição *\/`
 *   (texto após a tag) e em bloco multi-line com descrição/@dataProvider antes
 *   do @test. Guard endurecido pra um pattern único robusto (tempered dot).
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

    // Um único pattern robusto: abre num `/**`, percorre o interior do bloco SEM
    // cruzar o fechamento `*/` (tempered dot `(?:(?!\*\/).)*?`) e exige a tag
    // `@test` seguida de espaço ou `*`. Cobre as 4 formas que já furaram o guard:
    //   /** @test */                        single-line
    //   /** @test Art. 58 ... */             single-line com texto (furou no Ponto, 2026-06)
    //   /**\n * @test\n */                   multi-line limpo
    //   /**\n * desc\n * @test\n */           multi-line com descrição/@dataProvider antes do @test
    // O lookahead (?=\s|\*) evita falso-positivo em email (foo@test.local) e em
    // tags vizinhas como @testdox/@testWith.
    $pattern = '/\/\*\*(?:(?!\*\/).)*?@test(?=\s|\*)/s';

    foreach ($files as $file) {
        $count = preg_match_all($pattern, $file['content']);

        if ($count) {
            $violations[] = sprintf('%s: %d ocorrência(s) de tag @test em doc-comment', $file['relpath'], $count);
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

/**
 * Procura `@dataProvider` em doc-comment — a IRMÃ do `@test`, removida no MESMO PHPUnit 12.
 *
 * **Por que existe (medido no CT 100 em 2026-08-23):** o `@test` some em SILÊNCIO (o teste
 * simplesmente não roda). O `@dataProvider` é pior de outro jeito — o método roda, o PHPUnit
 * o chama com ZERO argumentos, e ele morre em `ArgumentCountError`. Vermelho, sim, mas
 * vermelho numa lane que ninguém olhava:
 *
 *   Modules/Ponto/Tests/Feature/SpatiePermissionsTest    0 pass ·  2 fail ·  0 assertions
 *   Modules/Ponto/Tests/Feature/MultiTenantIsolationTest 1 pass ·  1 fail ·  1 skip
 *
 * Depois de trocar por `#[\PHPUnit\Framework\Attributes\DataProvider('nome')]`, os DOIS
 * juntos: **22 passed (28 assertions)**. Vinte casos que nunca tinham executado — incluindo
 * a varredura das 10 rotas do Ponto contra 5xx.
 *
 * Eram 3 ocorrências em 2 arquivos, ambos fora da allowlist da lane. O guard do `@test` já
 * existia e é o dono desta classe (annotation removida no PHPUnit 12); ele só não cobria a
 * irmã. Estender o dono, não abrir um segundo (ADR 0298).
 *
 * Mesmo pattern tempered-dot do irmão. O lookahead `(?=\s|\*)` evita casar `@dataProviders`
 * ou texto colado; a menção dentro de comentário de LINHA (`// \@dataProvider …`) não casa
 * porque o pattern exige abertura de doc-comment `/**`.
 *
 * @param  array<int, array{path: string, relpath: string, content: string}>  $files
 * @return array<int, string>
 */
function phpunitGuardScanDataProvider(array $files): array
{
    $violations = [];
    $pattern = '/\/\*\*(?:(?!\*\/).)*?@dataProvider(?=\s|\*)/s';

    foreach ($files as $file) {
        $count = preg_match_all($pattern, $file['content']);

        if ($count) {
            $violations[] = sprintf('%s: %d ocorrência(s) de tag @dataProvider em doc-comment', $file['relpath'], $count);
        }
    }

    return $violations;
}

it('guard: nenhum *Test.php usa @dataProvider doc-comment (removida no PHPUnit 12)', function () {
    $files = phpunitGuardCollectFiles();
    expect($files)->not->toBeEmpty('Nenhum arquivo *Test.php encontrado — guard test inerte');

    $violations = phpunitGuardScanDataProvider($files);

    if (! empty($violations)) {
        $msg = "VIOLAÇÃO: `@dataProvider` doc-comment foi REMOVIDA no PHPUnit 12.\n\n"
            ."O método é chamado com ZERO argumentos e morre em ArgumentCountError —\n"
            ."vermelho de verdade, mas invisível se o arquivo não estiver numa lane.\n\n"
            ."Trocar por: #[\PHPUnit\Framework\Attributes\DataProvider('nomeDoProvider')]\n\n"
            ."Origem: 3 ocorrências em 2 arquivos do Ponto, medidas no CT 100 em 2026-08-23.\n"
            ."Depois do conserto: 22 passed (28 assertions) — 20 casos que nunca rodaram.\n\n"
            ."Violações encontradas:\n  - "
            .implode("\n  - ", $violations);

        expect($violations)->toBeEmpty($msg);
    }

    expect($violations)->toBeEmpty();
})->group('guard');

/**
 * CONTROLE NEGATIVO do guard acima — sem isto ele é decorativo (Lei C).
 *
 * Prova as duas direções contra fixtures em memória, sem tocar disco: o doc-comment MORDE
 * (nas 3 formas que o corpus tinha), e o atributo + a menção em comentário de linha LIBERAM.
 * A 3ª asserção é a que impede o guard de acusar a própria prosa que explica o defeito —
 * foi exatamente o que meus comentários de conserto viraram nos 2 arquivos consertados.
 */
it('guard: o scan de @dataProvider morde o doc-comment e libera o atributo', function () {
    $morde = [
        'single-line'          => ['relpath' => 'a', 'path' => 'a', 'content' => "<?php\n/** @dataProvider rotas */\npublic function t(\$u) {}"],
        'multi-line limpo'     => ['relpath' => 'b', 'path' => 'b', 'content' => "<?php\n/**\n * @dataProvider rotas\n */\npublic function t(\$u) {}"],
        'multi com descricao'  => ['relpath' => 'c', 'path' => 'c', 'content' => "<?php\n/**\n * Verifica X.\n *\n * @dataProvider rotas\n */\npublic function t(\$u) {}"],
    ];
    foreach ($morde as $nome => $f) {
        expect(phpunitGuardScanDataProvider([$f]))->not->toBeEmpty("MORDE esperado: {$nome}");
    }

    $libera = [
        'atributo FQCN'        => ['relpath' => 'd', 'path' => 'd', 'content' => "<?php\n#[\PHPUnit\Framework\Attributes\DataProvider('rotas')]\npublic function t(\$u) {}"],
        'atributo importado'   => ['relpath' => 'e', 'path' => 'e', 'content' => "<?php\n#[DataProvider('rotas')]\npublic function t(\$u) {}"],
        'mencao em // comment' => ['relpath' => 'f', 'path' => 'f', 'content' => "<?php\n// a annotation @dataProvider foi removida no PHPUnit 12\n#[DataProvider('rotas')]\npublic function t(\$u) {}"],
        'metodo provider'      => ['relpath' => 'g', 'path' => 'g', 'content' => "<?php\n/**\n * Rotas cobertas.\n */\npublic static function rotas(): array { return []; }"],
    ];
    foreach ($libera as $nome => $f) {
        expect(phpunitGuardScanDataProvider([$f]))->toBeEmpty("LIBERA esperado: {$nome}");
    }
})->group('guard');
