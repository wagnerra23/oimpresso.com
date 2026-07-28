<?php

declare(strict_types=1);

/**
 * Regressão Fase 3.7 PR-2 (rename Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS).
 *
 * Cobre os 3 bugs catastróficos descobertos em prod nesta sessão:
 *
 *   1. InstallController::moduleName() retornava nome legacy → toast vermelho
 *      "Module [MemCofre] does not exist!" ao clicar Install em /manage-modules.
 *      ADR 0088 + skill migrar-modulo §Pegadinha install.
 *
 *   2. DataController::modifyAdminMenu() chamava isModuleInstalled('NomeAntigo')
 *      → false silencioso → menu sumia da sidebar pra superadmin.
 *      Skill migrar-modulo §Pegadinha sidebar.
 *
 *   3. URL hardcoded em Menu::modify apontando pra rota legacy (/docs DocVault).
 *      Click → 404. Skill migrar-modulo §Pegadinha URLs hardcoded.
 *
 * Estes testes são static analysis — não bootam Laravel pra evitar dependência
 * de DB/auth. Se algum dia o teste virar dynamic (FactoryBuilder + Auth mock),
 * substituir static greps por reflection + dispatched assertions.
 */

it('InstallController.moduleName retorna nome novo do módulo (regressão Fase 3.7)', function () {
    $cases = [
        \Modules\Jana\Http\Controllers\InstallController::class  => 'Jana',
        \Modules\Ponto\Http\Controllers\InstallController::class => 'Ponto',
        \Modules\SRS\Http\Controllers\InstallController::class   => 'SRS',
    ];

    foreach ($cases as $class => $expected) {
        $method = new ReflectionMethod($class, 'moduleName');
        $method->setAccessible(true);
        $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        expect($method->invoke($instance))
            ->toBe($expected, "{$class}::moduleName() deveria retornar '{$expected}' (nome novo após rename)");
    }
});

it('InstallController.moduleSystemKey retorna key lowercase nova', function () {
    $cases = [
        \Modules\Jana\Http\Controllers\InstallController::class  => 'jana',
        \Modules\Ponto\Http\Controllers\InstallController::class => 'ponto',
        \Modules\SRS\Http\Controllers\InstallController::class   => 'srs',
    ];

    foreach ($cases as $class => $expected) {
        $method = new ReflectionMethod($class, 'moduleSystemKey');
        $method->setAccessible(true);
        $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        expect($method->invoke($instance))->toBe($expected);
    }
});

it('DataController.modifyAdminMenu chama isModuleInstalled com nome novo (não legacy)', function () {
    // Static analysis: lê o source e verifica que `isModuleInstalled('NomeNovo')`
    // está presente E `isModuleInstalled('NomeAntigo')` NÃO está.
    $cases = [
        base_path('Modules/Jana/Http/Controllers/DataController.php') => [
            'new' => 'Jana',
            'legacy' => 'Copiloto',
        ],
        base_path('Modules/Ponto/Http/Controllers/DataController.php') => [
            'new' => 'Ponto',
            'legacy' => 'PontoWr2',
        ],
    ];

    foreach ($cases as $path => $names) {
        $content = file_get_contents($path);

        expect($content)
            // FALHA AQUI SIGNIFICA: {$path} deveria chamar isModuleInstalled('{$names['new']}')
            ->toContain("isModuleInstalled('{$names['new']}')")
            ->not->toContain("isModuleInstalled('{$names['legacy']}')",
                "{$path} NÃO deveria chamar isModuleInstalled com nome legacy '{$names['legacy']}' — sidebar quebra silenciosa.");
    }
});

it('SRS DataController não aponta pra URL legacy /docs (DocVault, 3 renames atrás)', function () {
    $content = file_get_contents(base_path('Modules/SRS/Http/Controllers/DataController.php'));

    // /docs era a URL do DocVault (renomeado pra MemCofre em 2026-04-24, depois SRS em 2026-05-06).
    // Menu::modify chamando $sub->url('/docs', ...) gera 404 ao clicar.
    expect($content)
        ->not->toContain("\$sub->url('/docs',",
            'SRS/DataController não pode apontar pra URL legacy /docs (DocVault) — gera 404.');
});

it('modules_statuses.json não contém keys legacy renomeadas', function () {
    $json = json_decode(file_get_contents(base_path('modules_statuses.json')), true);
    $legacy = ['Copiloto', 'PontoWr2', 'MemCofre'];

    foreach ($legacy as $key) {
        expect($json)->not->toHaveKey($key,
            "modules_statuses.json não deveria ter key legacy '{$key}' — pasta foi renomeada, nWidart vai listar módulo fantasma em /manage-modules.");
    }

    // Sanity: keys novas têm que estar lá
    expect($json)
        ->toHaveKey('Jana')
        ->toHaveKey('Ponto')
        ->toHaveKey('SRS');
});

it('module.json dos 3 módulos renomeados tem name/alias com nome novo', function () {
    $cases = [
        'Modules/Jana/module.json'  => ['name' => 'Jana',  'alias' => 'jana'],
        'Modules/Ponto/module.json' => ['name' => 'Ponto', 'alias' => 'ponto'],
        'Modules/SRS/module.json'   => ['name' => 'SRS',   'alias' => 'srs'],
    ];

    foreach ($cases as $path => $expected) {
        $json = json_decode(file_get_contents(base_path($path)), true);

        expect($json['name'])->toBe($expected['name'], "{$path}: name");
        expect($json['alias'])->toBe($expected['alias'], "{$path}: alias");
    }
});

it('ServiceProvider class names refletem nome novo do módulo', function () {
    $cases = [
        'Modules/Jana/Providers/JanaServiceProvider.php'  => 'class JanaServiceProvider',
        'Modules/Ponto/Providers/PontoServiceProvider.php' => 'class PontoServiceProvider',
        'Modules/SRS/Providers/SrsServiceProvider.php'   => 'class SrsServiceProvider',
    ];

    foreach ($cases as $path => $expectedClass) {
        $fullPath = base_path($path);
        expect(file_exists($fullPath))->toBeTrue("{$path} deveria existir após rename");

        $content = file_get_contents($fullPath);
        expect($content)->toContain($expectedClass);
    }
});

it('composer.json autoload PSR-4 aponta pro namespace novo', function () {
    $cases = [
        'Modules/Jana/composer.json'  => 'Modules\\\\Jana\\\\',
        'Modules/Ponto/composer.json' => 'Modules\\\\Ponto\\\\',
        'Modules/SRS/composer.json'   => 'Modules\\\\SRS\\\\',
    ];

    foreach ($cases as $path => $expectedNamespace) {
        $content = file_get_contents(base_path($path));
        // FALHA AQUI SIGNIFICA: {$path}: PSR-4 autoload deveria mapear `{$expectedNamespace}`.
        expect($content)->toContain($expectedNamespace);
    }
});
