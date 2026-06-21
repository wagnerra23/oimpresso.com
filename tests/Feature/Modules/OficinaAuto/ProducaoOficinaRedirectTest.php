<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * ProducaoOficinaController@index — redirect 301 pro Quadro de OS canônico (ADR 0265).
 *
 * A tela "Produção · Oficina" (kanban veículo-cêntrico com colunas de status de
 * LOCAÇÃO do processo legado cacamba_locacao) foi unificada no Quadro de OS
 * (/oficina-auto/ordens-servico/board — OS-cêntrico, etapas reais do FSM
 * oficina_mecanica_os). A rota antiga sobrevive como redirect permanente
 * (menu antigo, bookmarks, links compartilhados) repassando filtros compatíveis.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 */

it('redireciona 301 pro board canônico', function () {
    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');

    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(301)
        ->and($response->getTargetUrl())->toContain('/oficina-auto/ordens-servico/board');
});

it('repassa filtros compatíveis (q/mecanico/box) e descarta capacidade (conceito de caçamba)', function () {
    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create(
        '/oficina-auto/producao-oficina',
        'GET',
        ['q' => 'ABC1D23', 'mecanico' => '7', 'box' => 'Box 1', 'capacidade' => '5'],
    );

    $target = $controller->index($request)->getTargetUrl();

    expect($target)->toContain('q=ABC1D23')
        ->and($target)->toContain('mecanico=7')
        ->and($target)->toContain('box=Box+1')
        ->and($target)->not->toContain('capacidade');
});

it('sem filtros redireciona pra URL limpa (sem query string)', function () {
    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');

    $target = $controller->index($request)->getTargetUrl();

    expect($target)->toEndWith('/oficina-auto/ordens-servico/board');
});
