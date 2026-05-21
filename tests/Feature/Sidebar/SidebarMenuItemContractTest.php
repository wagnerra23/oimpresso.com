<?php

declare(strict_types=1);

use App\Sidebar\SidebarGhost;
use App\Sidebar\SidebarGroup;
use App\Sidebar\SidebarMenuItem;
use App\Sidebar\SidebarPrimaryAction;

/*
|--------------------------------------------------------------------------
| Sidebar v3 — Contrato MenuItem (ADR 0180 Fase 1)
|--------------------------------------------------------------------------
|
| Garante que o DTO canônico aceita o feliz-path e REJEITA todas as
| violações de schema antes do payload chegar ao frontend.
|
| Não testa multi-tenant (responsabilidade do DataController, não do DTO).
| Não testa Menu::modify integration (Fase 2 do roadmap ADR 0180).
*/

// ── Happy path ──────────────────────────────────────────────────────────

it('cria item canônico mínimo (label + href + group)', function () {
    $item = new SidebarMenuItem(
        label: 'Financeiro',
        href:  '/financeiro',
        group: SidebarGroup::Financas,
    );

    expect($item->toArray())->toBe([
        'label' => 'Financeiro',
        'href'  => '/financeiro',
        'group' => 'financas',
    ]);
});

it('serializa item completo com icon + shortcut + primary + ghosts', function () {
    $item = new SidebarMenuItem(
        label:    'Financeiro',
        href:     '/financeiro',
        group:    SidebarGroup::Financas,
        icon:     'currency-dollar',
        shortcut: 'G F',
        primary:  new SidebarPrimaryAction('Novo título', '/financeiro/create', 'N'),
        ghosts:   [
            new SidebarGhost('unificado', 'Unificado', '/financeiro?tab=unificado'),
            new SidebarGhost('pagar',     'Pagar',     '/financeiro?tab=pagar'),
            new SidebarGhost('receber',   'Receber',   '/financeiro?tab=receber'),
        ],
    );

    $arr = $item->toArray();

    expect($arr)
        ->toHaveKey('label', 'Financeiro')
        ->toHaveKey('group', 'financas')
        ->toHaveKey('icon', 'currency-dollar')
        ->toHaveKey('shortcut', 'G F');

    expect($arr['primary'])->toBe([
        'label'    => 'Novo título',
        'href'     => '/financeiro/create',
        'shortcut' => 'N',
    ]);

    expect($arr['ghosts'])->toHaveCount(3)
        ->and($arr['ghosts'][0])->toBe([
            'key' => 'unificado', 'label' => 'Unificado', 'href' => '/financeiro?tab=unificado',
        ]);
});

it('omite campos null no toArray (sem icon, sem primary, sem ghosts)', function () {
    $arr = (new SidebarMenuItem(
        label: 'RH', href: '/rh', group: SidebarGroup::Pessoas,
    ))->toArray();

    expect($arr)->not->toHaveKey('icon')
        ->not->toHaveKey('shortcut')
        ->not->toHaveKey('primary')
        ->not->toHaveKey('ghosts');
});

// ── Validação MenuItem ──────────────────────────────────────────────────

it('rejeita label vazio', function () {
    new SidebarMenuItem(label: '', href: '/x', group: SidebarGroup::Vender);
})->throws(InvalidArgumentException::class, 'label cannot be empty');

it('rejeita label whitespace-only', function () {
    new SidebarMenuItem(label: '   ', href: '/x', group: SidebarGroup::Vender);
})->throws(InvalidArgumentException::class, 'label cannot be empty');

it('rejeita href que não é URL nem path absoluto', function () {
    new SidebarMenuItem(label: 'X', href: 'financeiro', group: SidebarGroup::Vender);
})->throws(InvalidArgumentException::class, 'href must be URL or absolute path');

it('aceita href URL absoluta (https://)', function () {
    $item = new SidebarMenuItem(
        label: 'Suporte', href: 'https://suporte.oimpresso.com', group: SidebarGroup::Sistema,
    );
    expect($item->href)->toBe('https://suporte.oimpresso.com');
});

it('rejeita shortcut fora do padrão G X ou G X Y', function () {
    new SidebarMenuItem(
        label: 'X', href: '/x', group: SidebarGroup::Vender, shortcut: 'CTRL+F',
    );
})->throws(InvalidArgumentException::class, "shortcut must match 'G X' or 'G X Y'");

it('aceita shortcut G X', function () {
    $item = new SidebarMenuItem(
        label: 'Financeiro', href: '/financeiro', group: SidebarGroup::Financas, shortcut: 'G F',
    );
    expect($item->shortcut)->toBe('G F');
});

it('aceita shortcut G X Y (cascata)', function () {
    $item = new SidebarMenuItem(
        label: 'Financeiro', href: '/financeiro', group: SidebarGroup::Financas, shortcut: 'G F R',
    );
    expect($item->shortcut)->toBe('G F R');
});

it('rejeita ghost que não é instância de SidebarGhost', function () {
    new SidebarMenuItem(
        label: 'X', href: '/x', group: SidebarGroup::Vender,
        ghosts: [['key' => 'foo', 'label' => 'Foo', 'href' => '/foo']],
    );
})->throws(InvalidArgumentException::class, 'ghosts must be SidebarGhost');

// ── Validação SidebarGhost ──────────────────────────────────────────────

it('rejeita Ghost com key não-kebab-case', function () {
    new SidebarGhost('Foo Bar', 'Foo', '/foo');
})->throws(InvalidArgumentException::class, 'key must be kebab-case');

it('rejeita Ghost com key começando com número', function () {
    new SidebarGhost('1foo', 'Foo', '/foo');
})->throws(InvalidArgumentException::class, 'key must be kebab-case');

it('aceita Ghost com key kebab-case com hífens', function () {
    $ghost = new SidebarGhost('plano-contas', 'Plano de Contas', '/financeiro?tab=plano-contas');
    expect($ghost->key)->toBe('plano-contas');
});

it('rejeita Ghost com label vazio', function () {
    new SidebarGhost('foo', '', '/foo');
})->throws(InvalidArgumentException::class, 'label cannot be empty');

it('rejeita Ghost com href inválido', function () {
    new SidebarGhost('foo', 'Foo', 'foo-page');
})->throws(InvalidArgumentException::class, 'href must be URL');

// ── Validação SidebarPrimaryAction ──────────────────────────────────────

it('rejeita PrimaryAction com label vazio', function () {
    new SidebarPrimaryAction('', '/foo');
})->throws(InvalidArgumentException::class, 'label cannot be empty');

it('rejeita PrimaryAction com shortcut composto não-canônico', function () {
    new SidebarPrimaryAction('Novo', '/foo', shortcut: 'shift+n');
})->throws(InvalidArgumentException::class, 'shortcut must match');

it('aceita PrimaryAction shortcut single letter', function () {
    $pa = new SidebarPrimaryAction('Novo', '/foo', shortcut: 'N');
    expect($pa->shortcut)->toBe('N');
});

it('serializa PrimaryAction omitindo shortcut null', function () {
    $arr = (new SidebarPrimaryAction('Novo', '/foo'))->toArray();
    expect($arr)->toBe(['label' => 'Novo', 'href' => '/foo']);
});

// ── SidebarGroup enum + LEGACY_GROUP_MAP ────────────────────────────────

it('mapeia keys legacy v2 pros 5 grupos v3', function () {
    expect(SidebarGroup::fromLegacy('office'))->toBe(SidebarGroup::Vender)
        ->and(SidebarGroup::fromLegacy('oficina'))->toBe(SidebarGroup::Operar)
        ->and(SidebarGroup::fromLegacy('estoque'))->toBe(SidebarGroup::Operar)
        ->and(SidebarGroup::fromLegacy('fin'))->toBe(SidebarGroup::Financas)
        ->and(SidebarGroup::fromLegacy('fin-op'))->toBe(SidebarGroup::Financas)
        ->and(SidebarGroup::fromLegacy('fin-analise'))->toBe(SidebarGroup::Financas)
        ->and(SidebarGroup::fromLegacy('fin-config'))->toBe(SidebarGroup::Financas)
        ->and(SidebarGroup::fromLegacy('fiscal'))->toBe(SidebarGroup::Financas)
        ->and(SidebarGroup::fromLegacy('rh'))->toBe(SidebarGroup::Pessoas)
        ->and(SidebarGroup::fromLegacy('conhecimento'))->toBe(SidebarGroup::Ia)
        ->and(SidebarGroup::fromLegacy('rel'))->toBe(SidebarGroup::Ia)
        ->and(SidebarGroup::fromLegacy('governanca'))->toBe(SidebarGroup::Sistema)
        ->and(SidebarGroup::fromLegacy('plataforma'))->toBe(SidebarGroup::Sistema);
});

it('aceita keys v3 pass-through em fromLegacy', function () {
    foreach (SidebarGroup::canonical() as $group) {
        expect(SidebarGroup::fromLegacy($group->value))->toBe($group);
    }
});

it('mapeia key desconhecida pra Mais (fallback)', function () {
    expect(SidebarGroup::fromLegacy('grupo-que-nao-existe'))->toBe(SidebarGroup::Mais);
});

it('canonical() retorna os 5 grupos v3 (sem topo, sem Mais)', function () {
    $groups = SidebarGroup::canonical();
    expect($groups)->toHaveCount(5);

    $values = array_map(static fn (SidebarGroup $g) => $g->value, $groups);
    expect($values)->toBe(['vender', 'operar', 'financas', 'pessoas', 'sistema']);
});
