<?php

declare(strict_types=1);

use App\Services\Menu\MenuItem;

/*
|--------------------------------------------------------------------------
| MenuItem::getAttributes() — render seguro de attributes mistos
|--------------------------------------------------------------------------
|
| Hotfix anti-regressão: ADR 0180 Fase 4 (PR #1350/#1353) populou os
| attributes dos menu items com keys `primary` (array) e `ghosts` (array of
| arrays) consumidos pelo LegacyMenuAdapter→React Sidebar. O renderer Blade
| legacy (AdminlteCustomPresenter::getMenuWithoutDropdownWrapper) chama
| MenuItem::getAttributes() que iterava o bag aplicando htmlspecialchars()
| em cada valor — estourava TypeError quando $v era array.
|
| Esses testes garantem que valores não-escalares são silenciosamente
| ignorados pelo renderer HTML e que valores escalares continuam saindo
| escapados.
*/

it('ignora attribute value array sem estourar TypeError (regressão PR #1353)', function () {
    $item = new MenuItem([
        'url'        => '/repair',
        'title'      => 'Reparo',
        'attributes' => [
            'style'    => 'background-color:#fff',
            'shortcut' => 'G O',
            'primary'  => [
                'label'    => 'Nova OS',
                'href'     => '/sells/pos/create?sub_type=repair',
                'shortcut' => 'N',
            ],
            'ghosts'   => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/repair/dashboard'],
                ['key' => 'os',        'label' => 'OS',        'href' => '/repair/repair'],
            ],
        ],
    ]);

    $rendered = $item->getAttributes();

    expect($rendered)->toBeString();
    expect($rendered)->toContain('style="background-color:#fff"');
    expect($rendered)->toContain('shortcut="G O"');
    expect($rendered)->not->toContain('primary=');
    expect($rendered)->not->toContain('ghosts=');
});

it('escapa valores escalares com htmlspecialchars()', function () {
    $item = new MenuItem([
        'url'        => '/x',
        'title'      => 'X',
        'attributes' => [
            'data-label' => 'a"b<c>',
        ],
    ]);

    expect($item->getAttributes())->toContain('data-label="a&quot;b&lt;c&gt;"');
});

it('booleans viram "1"/"0" em vez de quebrar e()', function () {
    $item = new MenuItem([
        'url'        => '/x',
        'title'      => 'X',
        'attributes' => [
            'data-active' => true,
            'data-empty'  => false,
        ],
    ]);

    $rendered = $item->getAttributes();

    expect($rendered)->toContain('data-active="1"');
    expect($rendered)->toContain('data-empty="0"');
});

it('null values são pulados', function () {
    $item = new MenuItem([
        'url'        => '/x',
        'title'      => 'X',
        'attributes' => [
            'style'     => 'color:red',
            'data-null' => null,
        ],
    ]);

    $rendered = $item->getAttributes();

    expect($rendered)->toContain('style="color:red"');
    expect($rendered)->not->toContain('data-null');
});

it('objetos são pulados (defensivo — não deveria acontecer mas not blow up)', function () {
    $item = new MenuItem([
        'url'        => '/x',
        'title'      => 'X',
        'attributes' => [
            'style' => 'color:red',
            'weird' => new stdClass(),
        ],
    ]);

    $rendered = $item->getAttributes();

    expect($rendered)->toContain('style="color:red"');
    expect($rendered)->not->toContain('weird');
});

it('attributes vazio retorna string vazia', function () {
    $item = new MenuItem([
        'url'        => '/x',
        'title'      => 'X',
        'attributes' => [],
    ]);

    expect($item->getAttributes())->toBe('');
});
