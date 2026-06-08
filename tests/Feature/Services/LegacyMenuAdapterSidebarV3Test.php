<?php

declare(strict_types=1);

/**
 * Pest test — LegacyMenuAdapter propaga shortcut/primary/ghosts (ADR 0180 Fase 4).
 *
 * Garante que os 3 campos novos do contrato Sidebar v3 (declarados pelo
 * DataController via attributes) são propagados intactos pelo adapter pro
 * shape consumido pelo frontend (PageHeaderTabs + Cmd+K em fases futuras).
 *
 * Não toca banco. Usa Menu::create direto pra montar instância em memória.
 */

use App\Services\LegacyMenuAdapter;
use Illuminate\Support\Facades\Facade;

beforeEach(function () {
    // Reset da instância do menu entre testes (Menu é facade singleton).
    if (\Menu::instance('sidebar-v3-test')) {
        Facade::clearResolvedInstance('menu');
    }
});

it('propaga shortcut/primary/ghosts quando declarados nos attributes', function () {
    \Menu::create('admin-sidebar-menu', function ($menu) {
        $menu->url('/financeiro/unificado', 'Financeiro', [
            'icon'     => 'fa fa-coins',
            'group'    => 'fin-op',
            'shortcut' => 'G F',
            'primary'  => [
                'label'    => 'Novo título',
                'href'     => '/financeiro/lancamentos/create',
                'shortcut' => 'N',
            ],
            'ghosts'   => [
                ['key' => 'unificado',      'label' => 'Unificado',      'href' => '/financeiro/unificado'],
                ['key' => 'contas-receber', 'label' => 'Contas a Receber','href' => '/financeiro/contas-receber'],
                ['key' => 'contas-pagar',   'label' => 'Contas a Pagar', 'href' => '/financeiro/contas-pagar'],
            ],
        ])->order(85);
    });

    $built = (new LegacyMenuAdapter())->build();

    expect($built)->toHaveCount(1);

    $item = $built[0];

    expect($item['label'])->toBe('Financeiro')
        ->and($item['href'])->toBe('/financeiro/unificado')
        ->and($item['group'])->toBe('fin-op')
        ->and($item['shortcut'])->toBe('G F');

    expect($item['primary'])->toBe([
        'label'    => 'Novo título',
        'href'     => '/financeiro/lancamentos/create',
        'shortcut' => 'N',
    ]);

    expect($item['ghosts'])->toHaveCount(3)
        ->and($item['ghosts'][0])->toBe([
            'key' => 'unificado', 'label' => 'Unificado', 'href' => '/financeiro/unificado',
        ])
        ->and($item['ghosts'][2]['key'])->toBe('contas-pagar');
});

it('omite shortcut/primary/ghosts quando não declarados (backward-compat)', function () {
    \Menu::create('admin-sidebar-menu', function ($menu) {
        $menu->url('/financeiro/relatorios', 'Relatórios', [
            'icon' => 'fa fa-chart-pie',
            'group' => 'fin-analise',
        ])->order(85);
    });

    $item = (new LegacyMenuAdapter())->build()[0];

    expect($item)->toHaveKey('label', 'Relatórios')
        ->toHaveKey('group', 'fin-analise')
        ->not->toHaveKey('shortcut')
        ->not->toHaveKey('primary')
        ->not->toHaveKey('ghosts');
});

it('aceita os 3 atributos novos via aninhamento attributes[] (alternativa)', function () {
    \Menu::create('admin-sidebar-menu', function ($menu) {
        $menu->url('/x', 'X', [
            'attributes' => [
                'shortcut' => 'G X',
                'ghosts'   => [['key' => 'foo', 'label' => 'Foo', 'href' => '/x?tab=foo']],
            ],
        ]);
    });

    $item = (new LegacyMenuAdapter())->build()[0];

    expect($item['shortcut'])->toBe('G X')
        ->and($item['ghosts'])->toHaveCount(1)
        ->and($item['ghosts'][0]['key'])->toBe('foo');
});

it('ignora ghosts vazios (array vazio NÃO entra no payload)', function () {
    \Menu::create('admin-sidebar-menu', function ($menu) {
        $menu->url('/x', 'X', [
            'group'  => 'sistema',
            'ghosts' => [],
        ]);
    });

    $item = (new LegacyMenuAdapter())->build()[0];

    expect($item)->not->toHaveKey('ghosts');
});
