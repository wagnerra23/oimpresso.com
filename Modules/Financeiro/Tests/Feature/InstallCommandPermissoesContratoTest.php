<?php

declare(strict_types=1);

use Modules\Financeiro\Console\Commands\InstallCommand;
use Modules\Financeiro\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * O `financeiro:install` cria e atribui ao Admin#<biz> EXATAMENTE as permissões que o
 * DataController cadastra (a lista que /roles mostra).
 *
 * Até 2026-09-23 o comando tinha uma cópia escrita à mão com 13 nomes, contra 17 no
 * DataController. Agora a lista é derivada; este teste prende a derivação para que uma
 * cópia manual não volte.
 */
it('InstallCommand usa a mesma lista de permissões do DataController', function () {
    $doDataController = array_map(
        fn (array $p) => $p['value'],
        (new DataController())->user_permissions()
    );

    $metodo = new ReflectionMethod(InstallCommand::class, 'perms');
    $doComando = $metodo->invoke(app(InstallCommand::class));

    // Piso: as 17 medidas em 2026-09-23. Se o DataController crescer, o comando acompanha.
    expect(count($doDataController))->toBeGreaterThanOrEqual(17);
    expect($doComando)->toBe(array_values($doDataController));
    expect($doComando)->toContain(
        'financeiro.lancamentos.create',
        'financeiro.extrato.view',
        'financeiro.titulo.aprovar',
        'financeiro.advisor.grant',
    );
});
