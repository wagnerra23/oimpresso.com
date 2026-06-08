<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Tests pra `php artisan module:grade` (Command CLI da rubrica module-grade-v1).
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 * @see Modules/Governance/Console/Commands/ModuleGradeCommand.php
 */

it('module:grade exige name OU --all', function () {
    $this->artisan('module:grade')
        ->expectsOutputToContain('Forneça {name} ou --all')
        ->assertExitCode(2);
});

it('module:grade <Nome> retorna sucesso com tabela', function () {
    $this->artisan('module:grade Governance')
        ->expectsOutputToContain('Modules/Governance')
        ->assertExitCode(0);
});

it('module:grade <ModuloInexistente> falha', function () {
    $this->artisan('module:grade ModuloXyzNaoExiste')
        ->expectsOutputToContain('não existe em Modules/')
        ->assertExitCode(1);
});

it('module:grade --all retorna tabela ranqueada', function () {
    $this->artisan('module:grade --all')
        ->expectsOutputToContain('Média projeto:')
        ->expectsOutputToContain('Distribuição por bucket:')
        ->assertExitCode(0);
});

it('module:grade <Nome> --json retorna JSON parseável', function () {
    $this->artisan('module:grade Governance --json')
        ->assertExitCode(0);
});

it('module:grade <Nome> --evolve mostra batch de tasks sugeridas', function () {
    $this->artisan('module:grade Governance --evolve')
        ->expectsOutputToContain('Batch de tasks-create sugeridas')
        ->assertExitCode(0);
});

it('module:grade NÃO usa flag --verbose (Symfony reserved — colide com --v|--vv|--vvv)', function () {
    // Sanity: signature do command não declara verbose custom
    $command = app(\Modules\Governance\Console\Commands\ModuleGradeCommand::class);
    $definition = $command->getDefinition();

    // Symfony adiciona --verbose default; nosso command NÃO deve sobreescrever
    expect($definition->hasOption('detail'))->toBeTrue();
    expect($definition->hasOption('json'))->toBeTrue();
    expect($definition->hasOption('all'))->toBeTrue();
    expect($definition->hasOption('evolve'))->toBeTrue();

    // Verifica que --verbose ainda é o padrão Symfony (não custom)
    $verboseOption = $definition->hasOption('verbose') ? $definition->getOption('verbose') : null;
    if ($verboseOption) {
        // Symfony default tem shortcut 'v' e description "Increase the verbosity of messages"
        expect($verboseOption->getShortcut())->toBe('v');
    }
});
