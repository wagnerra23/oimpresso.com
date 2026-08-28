<?php

declare(strict_types=1);

/**
 * Pest GUARD — a "Visão geral" é entry de LANDING (topo fixo), não de grupo.
 *
 * Por que existe (medido 2026-08-28): a entry do destino pós-login caía em
 * SISTEMA — o ÚLTIMO grupo do sidebar — por MATCH DE LABEL, porque 'Dashboard'
 * está em `SIDEBAR_GROUPS.sistema.items` no `Sidebar.tsx`. Ao trocar o label
 * pra 'Visão geral' esse match deixa de existir (medido: 'Visão geral' não
 * aparece em `items[]` de grupo nenhum), e sem um `group` declarado o
 * `findGroupKey` cai no passo 5 e joga a entry em **MAIS**.
 *
 * É por isso que label e `group` são ACOPLADOS e mudam juntos. Este guard é o
 * que torna o acoplamento barulhento: mexer num sem o outro quebra aqui.
 *
 * Escopo honesto: prova o CONTRATO que o React consome (o `group` sobrevive à
 * serialização do `LegacyMenuAdapter`) e a existência das chaves de tradução.
 * NÃO prova pixel — que o item apareça no topo é render, coberto por VRT.
 *
 * Não toca banco (usa `Menu::create` em memória, igual ao
 * LegacyMenuAdapterSidebarV3Test).
 */

use App\Services\LegacyMenuAdapter;
use Illuminate\Support\Facades\Facade;

beforeEach(function () {
    if (\Menu::instance('visao-geral-landing-test')) {
        Facade::clearResolvedInstance('menu');
    }
});

it('o group `landing` sobrevive à serialização — é o que tira a entry dos grupos', function () {
    \Menu::create('admin-sidebar-menu', function ($menu) {
        $menu->url('/dashboard-legacy', 'Visão geral', [
            'icon'  => '<svg></svg>',
            'group' => 'landing',
        ])->order(5);
    });

    $built = (new LegacyMenuAdapter())->build();

    expect($built)->toHaveCount(1);

    // O `group` é o ÚNICO canal que põe a entry no topo. Se o adapter parar de
    // propagá-lo, o React cai no match por label → 'Visão geral' não casa nada
    // → MAIS. Este assert é a fronteira entre "topo" e "fim do menu".
    expect($built[0]['group'])->toBe('landing')
        ->and($built[0]['label'])->toBe('Visão geral')
        ->and($built[0]['href'])->toBe('/dashboard-legacy');
});

it('a entry aponta pra /dashboard-legacy — /home é 302, não destino', function () {
    // `action([HomeController::class,'index'])` resolve pra `/dashboard-legacy`:
    // é a ÚNICA rota que declara esse controller/método (varredura contada
    // 2026-08-28). `/home` é um redirect e não serve de âncora de `active`.
    $url = action([\App\Http\Controllers\HomeController::class, 'index']);

    expect($url)->toContain('/dashboard-legacy')
        ->and($url)->not->toEndWith('/home');
});

it('`home.visao_geral` existe nos dois locales e NÃO reusa `home.home`', function () {
    // `home.home` também rotula o dashboard do CLIENTE final (Modules/Crm
    // ContactSidebarMenu + views/dashboard/index.blade). Reusar aquela chave
    // faria o rename vazar pra outra audiência — por isso a chave é própria.
    foreach (['pt', 'en'] as $locale) {
        $visaoGeral = trans('home.visao_geral', [], $locale);
        $home       = trans('home.home', [], $locale);

        expect($visaoGeral)
            ->not->toBe('home.visao_geral', "locale {$locale}: chave ausente (trans devolveu a própria chave)")
            ->and($visaoGeral)->not->toBe($home, "locale {$locale}: a entry do painel não pode reusar `home.home` (é do cliente final)");
    }
});

it('o locale pt diz exatamente `Visão geral` — casa com título, breadcrumb e contrato da tela', function () {
    // O charter, o breadcrumb e o `data-contract=cabecalho` da tela dizem
    // "Visão geral". O menu dizia "Dashboard". Este assert é o que impede os
    // dois voltarem a divergir.
    expect(trans('home.visao_geral', [], 'pt'))->toBe('Visão geral');
});
