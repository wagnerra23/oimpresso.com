<?php

/**
 * P2 — prova de que o status "Com erro" da tela /modulos é alcançável.
 *
 * Estratégia de fixture igual à do DetectDriftCommandTest: cria Modules/__ErrFixture__/
 * de verdade (o Service lê base_path('Modules') direto, não aceita override) e remove no
 * afterEach. Nome com underscore duplo para não colidir com módulo real nem com o
 * ModuleScaffoldingTest.
 *
 * Escrito por [CC] 2026-08-19, não executado.
 */

use App\Services\ModuleManagerService;
use Illuminate\Support\Facades\File;

const ERR_FIXTURE = '__ErrFixture__';

function errFixturePath(): string
{
    return base_path('Modules/' . ERR_FIXTURE);
}

function acharFixture(array $modules): ?array
{
    return collect($modules)->firstWhere('name', ERR_FIXTURE);
}

afterEach(function () {
    File::deleteDirectory(errFixturePath());
});

it('module.json malformado vira linha com erro (status "Com erro")', function () {
    File::ensureDirectoryExists(errFixturePath());
    File::put(errFixturePath() . '/module.json', '{ "name": "__ErrFixture__", ');  // JSON truncado

    $linha = acharFixture(app(ModuleManagerService::class)->list());

    expect($linha)->not->toBeNull();
    expect($linha['error'])->toContain('module.json inválido');
});

it('module.json sem providers[] vira linha com erro — o módulo não carrega', function () {
    File::ensureDirectoryExists(errFixturePath());
    File::put(errFixturePath() . '/module.json', json_encode([
        'name'  => ERR_FIXTURE,
        'alias' => 'errfixture',
    ]));

    $linha = acharFixture(app(ModuleManagerService::class)->list());

    expect($linha['error'])->toContain('sem providers[]');
});

it('module.json ausente vira linha com erro, não linha silenciosa', function () {
    File::ensureDirectoryExists(errFixturePath());

    $linha = acharFixture(app(ModuleManagerService::class)->list());

    expect($linha['error'])->toBe('module.json ausente.');
});

it('módulo saudável continua com error null', function () {
    $linha = collect(app(ModuleManagerService::class)->list())->firstWhere('name', 'Financeiro');

    expect($linha['error'])->toBeNull();
});
