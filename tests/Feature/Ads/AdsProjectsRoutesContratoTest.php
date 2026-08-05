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
 * ele cruza DUAS fontes independentes — o registro de rotas do Laravel (runtime)
 * × a classe que existe no disco — e não a leitura de um arquivo com ele mesmo.
 * Um `use` stale derruba o boot das 4 rotas sem nenhum outro gate acusar.
 *
 * HISTÓRICO DO WIRING (o elo mudou de lugar; os asserts, não):
 *  - até 2026-07-31: o controller morava em `Modules/Forja` e a rota era registrada
 *    por `Modules/ADS/Routes/web.php` — elo CROSS-MÓDULO, que era o que o rename
 *    `ProjectMgmt`→`Forja` (PR #5089) podia quebrar em silêncio.
 *  - desde 2026-07-31 (incorporação do ADS pelo Governance, parte 5/7): a rota
 *    passou pra `Modules/Forja/Http/routes.php`. Controller e rota agora moram no
 *    MESMO módulo — o elo deixou de ser cross-módulo.
 *
 * O que o teste guarda HOJE, e por isso ele continua valendo: a URL e o name
 * `/ads/admin/projects` / `ads.admin.projects.*` são PINADOS por ADR 0087 (drift
 * resolution SEM mover URL) e consumidos por string literal pelo frontend
 * (`Pages/ads/Admin/Projects*.tsx`, ZERO `route('ads.`). Renomear o name ou o path
 * na mudança de host quebraria a tela em silêncio — este teste é o gate disso.
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
        expect(Route::has($name))->toBeTrue("rota {$name} deveria existir (Modules/Forja/Http/routes.php)");
    }
});

it('as rotas apontam para o controller que vive em Modules/Forja', function () {
    // Desde 2026-07-31 quem registra E quem implementa é a Forja (parte 5/7).
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
