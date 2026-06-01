<?php

declare(strict_types=1);

use Modules\Jana\Services\CharterHealthChecker;

uses(Tests\TestCase::class);

/**
 * GUARD — CharterHealthChecker (item 2 / handoff design 2026-05-31, COWORK_NOTES).
 *
 * Checks ADVISORY de charter que estendem `jana:health-check` (PROTOCOL §6):
 * charter_missing · charter_stale · charter_refs_broken ·
 * charter_method_missing · readme_handoff_block_missing.
 *
 * Lógica testada com FIXTURES (basePath injetado) — sem DB, determinístico.
 */
if (! function_exists('charterHcTmp')) {
    /** @param array<string,string> $files */
    function charterHcTmp(array $files): string
    {
        $base = sys_get_temp_dir() . '/charter-hc-' . bin2hex(random_bytes(6));
        foreach ($files as $rel => $content) {
            $abs = $base . '/' . $rel;
            if (! is_dir(dirname($abs))) {
                mkdir(dirname($abs), 0777, true);
            }
            file_put_contents($abs, $content);
        }

        return $base;
    }
}

if (! function_exists('charterHcRmrf')) {
    function charterHcRmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}

if (! function_exists('charterHcRow')) {
    /**
     * @param  array<int,array<string,mixed>>  $checks
     * @return array<string,mixed>
     */
    function charterHcRow(array $checks, string $name): array
    {
        foreach ($checks as $c) {
            if ($c['name'] === $name) {
                return $c;
            }
        }
        throw new RuntimeException("check {$name} ausente");
    }
}

if (! function_exists('charterHcValid')) {
    /** @param array<string,string> $overrides */
    function charterHcValid(array $overrides = []): string
    {
        $fm = array_merge([
            'page' => '/demo/show',
            'component' => 'resources/js/Pages/Demo/Show.tsx',
            'owner' => 'wagner',
            'status' => 'live',
            'last_validated' => '"' . date('Y-m-d') . '"',
            'parent_module' => 'Demo',
            'tier' => 'B',
            'charter_version' => '1',
        ], $overrides);

        $lines = ['---'];
        foreach ($fm as $k => $v) {
            $lines[] = "{$k}: {$v}";
        }
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '# Charter Demo';
        $lines[] = '';

        return implode("\n", $lines);
    }
}

it('charter_missing conta página .tsx sem .charter.md ao lado', function () {
    $tmp = charterHcTmp([
        'resources/js/Pages/Demo/Index.tsx' => 'export default () => null;',
        'resources/js/Pages/Demo/Show.tsx' => 'export default () => null;',
        'resources/js/Pages/Demo/Show.charter.md' => charterHcValid(),
    ]);

    $row = charterHcRow((new CharterHealthChecker($tmp))->checks(), 'charter_missing');

    expect($row['value'])->toBe(1)            // só Index.tsx ficou sem charter
        ->and($row['ok'])->toBeFalse()
        ->and($row['advisory'])->toBeTrue();

    charterHcRmrf($tmp);
});

it('charter_missing ignora dirs _underscore e arquivos não-PascalCase', function () {
    $tmp = charterHcTmp([
        'resources/js/Pages/Demo/Index.tsx' => 'x',
        'resources/js/Pages/Demo/Index.charter.md' => charterHcValid(),
        'resources/js/Pages/Demo/_components/Widget.tsx' => 'x',  // underscore dir → ignora
        'resources/js/Pages/Demo/columns.tsx' => 'x',             // lowercase → não é página
    ]);

    $row = charterHcRow((new CharterHealthChecker($tmp))->checks(), 'charter_missing');

    expect($row['value'])->toBe(0)->and($row['ok'])->toBeTrue();

    charterHcRmrf($tmp);
});

it('charter_stale flag charters com last_validated > 90 dias', function () {
    $tmp = charterHcTmp([
        'resources/js/Pages/Demo/Velho/Index.charter.md' => charterHcValid(['last_validated' => '"2020-01-01"']),
        'resources/js/Pages/Demo/Novo/Index.charter.md' => charterHcValid(),  // hoje
    ]);

    $row = charterHcRow((new CharterHealthChecker($tmp))->checks(), 'charter_stale');

    expect($row['value'])->toBe(1)            // só o de 2020
        ->and($row['ok'])->toBeFalse()
        ->and($row['advisory'])->toBeTrue();

    charterHcRmrf($tmp);
});

it('charter_refs_broken detecta component + link de corpo inexistentes', function () {
    $charter = charterHcValid(['component' => 'resources/js/Pages/Demo/Gone.tsx'])
        . "\nVeja [doc](../Inexistente/nope.md) e [ok](./presente.md).\n";

    $tmp = charterHcTmp([
        'resources/js/Pages/Demo/Index.charter.md' => $charter,
        'resources/js/Pages/Demo/presente.md' => 'existe',
        // Gone.tsx NÃO criado, ../Inexistente/nope.md NÃO criado → 2 refs quebradas
    ]);

    $row = charterHcRow((new CharterHealthChecker($tmp))->checks(), 'charter_refs_broken');

    expect($row['value'])->toBe(2)->and($row['ok'])->toBeFalse();

    charterHcRmrf($tmp);
});

it('charter_refs_broken zero quando todas as refs existem', function () {
    $charter = charterHcValid(['component' => 'resources/js/Pages/Demo/Index.tsx'])
        . "\nVeja [ok](./presente.md).\n";

    $tmp = charterHcTmp([
        'resources/js/Pages/Demo/Index.charter.md' => $charter,
        'resources/js/Pages/Demo/Index.tsx' => 'x',
        'resources/js/Pages/Demo/presente.md' => 'existe',
    ]);

    $row = charterHcRow((new CharterHealthChecker($tmp))->checks(), 'charter_refs_broken');

    expect($row['value'])->toBe(0)->and($row['ok'])->toBeTrue();

    charterHcRmrf($tmp);
});

it('readme_handoff_block_missing: ok com marcador, alerta sem', function () {
    $com = charterHcTmp(['prototipo-ui/README.md' => "# x\n<!-- HANDOFF-ENTRY -->\nfila aqui\n"]);
    $sem = charterHcTmp(['prototipo-ui/README.md' => "# x\nsem marcador\n"]);

    $rowCom = charterHcRow((new CharterHealthChecker($com))->checks(), 'readme_handoff_block_missing');
    $rowSem = charterHcRow((new CharterHealthChecker($sem))->checks(), 'readme_handoff_block_missing');

    expect($rowCom['ok'])->toBeTrue()->and($rowCom['value'])->toBe('present');
    expect($rowSem['ok'])->toBeFalse()->and($rowSem['value'])->toBe('MISSING');

    charterHcRmrf($com);
    charterHcRmrf($sem);
});

it('todos os 5 checks são advisory e têm o shape esperado', function () {
    $tmp = charterHcTmp(['resources/js/Pages/Demo/Index.tsx' => 'x']);
    $checks = (new CharterHealthChecker($tmp))->checks();

    expect($checks)->toHaveCount(5);
    foreach ($checks as $c) {
        expect($c)->toHaveKeys(['name', 'ok', 'value', 'threshold', 'message', 'advisory'])
            ->and($c['advisory'])->toBeTrue()
            ->and($c['ok'])->toBeBool();
    }

    charterHcRmrf($tmp);
});

it('repo real (fromApp): roda os 5 checks advisory sem quebrar', function () {
    $checks = CharterHealthChecker::fromApp()->checks();

    $names = array_column($checks, 'name');

    expect($checks)->toHaveCount(5)
        ->and($names)->toContain(
            'charter_missing',
            'charter_stale',
            'charter_refs_broken',
            'charter_method_missing',
            'readme_handoff_block_missing',
        );

    foreach ($checks as $c) {
        expect($c['advisory'])->toBeTrue();
    }
});
