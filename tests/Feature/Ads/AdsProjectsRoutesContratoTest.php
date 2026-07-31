<?php

declare(strict_types=1);

// @covers-us US-ADS-003
// @covers-us US-ADS-004

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * ADS · contrato de wiring das rotas de Project (US-ADS-003 / US-ADS-004).
 *
 * POR QUE ESTE TESTE EXISTE, e por que ele NÃO é tautológico:
 * o controller destas rotas mora em `Modules/Forja` (dono do domínio Jira-style,
 * `mcp_jira_projects`) mas é registrado por `Modules/ADS/Routes/web.php` via `use`
 * de namespace. Isso cruza DUAS fontes independentes — o registro de rotas do
 * Laravel × a classe que existe no disco — e é exatamente o elo que o rename
 * `ProjectMgmt`→`Forja` (PR #5089) podia quebrar em silêncio: um `use` stale
 * derruba o boot das 4 rotas sem nenhum outro gate acusar.
 *
 * Não testa regra de negócio (isso exige seed/auth e vive nas US); testa que o
 * contrato de wiring declarado nas US-ADS-003/004 é verdade no runtime.
 */
it('as 4 rotas de Project do ADS estão registradas', function () {
    foreach ([
        'ads.admin.projects.index',
        'ads.admin.projects.store',
        'ads.admin.projects.show',
        'ads.admin.projects.decompose',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("rota {$name} deveria existir (Modules/ADS/Routes/web.php)");
    }
});

it('as rotas apontam para o controller que vive em Modules/Forja', function () {
    // O elo cross-módulo: quem registra é o ADS, quem implementa é a Forja.
    // Se o `use` do namespace ficar stale, isto quebra — que é o ponto.
    $esperado = Modules\Forja\Http\Controllers\Admin\ProjectsController::class;

    foreach (['index', 'store', 'show', 'decompose'] as $metodo) {
        $action = Route::getRoutes()
            ->getByName("ads.admin.projects.{$metodo}")
            ->getActionName();

        expect($action)->toBe("{$esperado}@{$metodo}",
            "ads.admin.projects.{$metodo} deveria apontar pro ProjectsController da Forja");
    }
});

it('a classe do controller resolve de fato (namespace Modules\\Forja)', function () {
    // Controle-negativo do rename: `class_exists` prova que o PSR-4 do módulo
    // renomeado carrega. O namespace antigo NÃO pode mais resolver.
    expect(class_exists(Modules\Forja\Http\Controllers\Admin\ProjectsController::class))->toBeTrue();
    expect(class_exists('Modules\ProjectMgmt\Http\Controllers\Admin\ProjectsController'))->toBeFalse(
        'namespace antigo não deve resolver — o módulo foi renomeado pra Forja em 2026-07-30 (ADR 0088)'
    );
});

it('show e decompose só aceitam id numérico (whereNumber)', function () {
    // Parte do aceite de US-ADS-004: o `whereNumber('id')` é o que faz um id
    // não-numérico cair em 404 de rota antes de chegar no controller.
    foreach (['show', 'decompose'] as $metodo) {
        $rota = Route::getRoutes()->getByName("ads.admin.projects.{$metodo}");
        expect($rota->wheres)->toHaveKey('id');
        expect($rota->wheres['id'])->toBe('[0-9]+');
    }
});
