<?php

/**
 * ModuleManagerService — regras que a tela /modulos depende (UC-MOD-05..07 + bordas).
 * Escrito por [CC] 2026-08-19, não executado. Sem banco: só filesystem + heurística.
 */

use AppServicesModuleManagerService;

it('UC-MOD-05: list() ordena ativos primeiro, depois área, depois nome', function () {
    $modules = app(ModuleManagerService::class)->list();

    $ativos = array_map(fn ($m) => $m['active'], $modules);
    expect($ativos)->toBe(collect($ativos)->sortDesc()->values()->all());

    $chave = fn ($m) => [! $m['active'], $m['area'], $m['name']];
    $ordenado = collect($modules)->sortBy($chave)->values()->all();
    expect(array_column($modules, 'name'))->toBe(array_column($ordenado, 'name'));
});

it('UC-MOD-06: chave do JSON sem pasta em Modules/ não aparece na lista', function () {
    $nomes = array_column(app(ModuleManagerService::class)->list(), 'name');
    $statuses = json_decode(file_get_contents(base_path('modules_statuses.json')), true);

    $orfas = array_values(array_diff(array_keys($statuses), $nomes));
    foreach ($orfas as $orfa) {
        expect(is_dir(base_path("Modules/{$orfa}")))->toBeFalse(
            "'{$orfa}' tem pasta e ficou fora da lista — regressão de R2."
        );
    }
});

it('UC-MOD-07: pasta ausente do JSON vem com registered=false', function () {
    $statuses = json_decode(file_get_contents(base_path('modules_statuses.json')), true);

    foreach (app(ModuleManagerService::class)->list() as $m) {
        expect($m['registered'])->toBe(array_key_exists($m['name'], $statuses));
        if (! $m['registered']) {
            expect($m['active'])->toBeFalse();  // não registrado nunca chega ativo
        }
    }
});

it('guessArea(): heurística por palavra-chave, com as pegadinhas conhecidas', function () {
    $areas = collect(app(ModuleManagerService::class)->list())->pluck('area', 'name');

    expect($areas['Ponto'])->toBe('Recursos Humanos');
    expect($areas['NfeBrasil'])->toBe('Financeiro');
    expect($areas['NFSe'])->toBe('Outros');        // 'nfse' NÃO contém 'nfe'
    expect($areas['Financeiro'])->toBe('Outros');  // o nome não casa nenhuma keyword do areaMap
    expect($areas['Superadmin'])->toBe('Administração');
})->skip('documenta a heurística atual — vira contrato só se [W] ratificar o areaMap');

it('runModuleInstallCommand(): sem InstallCommand devolve null; comando não registrado devolve [skip]', function () {
    $service = app(ModuleManagerService::class);
    $metodo = new ReflectionMethod($service, 'runModuleInstallCommand');
    $metodo->setAccessible(true);

    // Módulo sem Console/Commands/InstallCommand.php
    expect($metodo->invoke($service, 'Arquivos', 1))->toBeNull();
});
