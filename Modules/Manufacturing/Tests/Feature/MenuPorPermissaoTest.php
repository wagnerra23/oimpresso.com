<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-RECIPE-24 — o menu lateral da Fabricação só mostra o que a permissão abre, e tem Insumos.
 * Auditoria do Manufacturing, lote 2.4 (eixo 5 M5).
 *
 * Contrato: cada item leva a uma rota que o usuário consegue abrir. Receitas e Insumos pedem
 * `manufacturing.access_recipe`; Ordens de produção, Relatório e Configurações pedem
 * `manufacturing.access_production` (decisão B3 de [W] em 2026-11-09, lote 2.1). Item que dá 403 ao
 * clicar não aparece; Insumos, que só existia pela aba, entra no menu.
 *
 * Caminho real: a requisição passa pelo AdminSidebarMenu, o menu é serializado no prop compartilhado
 * `shell.menu`, e o teste lê o item da Fabricação que o React recebe — mesmo padrão do
 * `tests/Feature/Sells/CatalogueQrMenuContratoTest.php`. A assinatura vem de um ModuleUtil parcial
 * (o pacote semeado na lane não é o que se testa); a permissão do papel é a real.
 *
 * Substitui o caso do `CutoverRotasCanonicasTest` que procurava 'insumos' no
 * `layouts/partials/sidebar.blade.php`, uma Blade que nenhuma tela inclui.
 *
 * Tenant de teste (ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('Schema MySQL UltimatePOS ausente.');
    }
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);
});

/** O item da Fabricação no menu serializado: o que tem ghost apontando para /manufacturing/. */
function mfgMenuItem(\Illuminate\Testing\TestResponse $resp): ?array
{
    $menu = (array) data_get($resp->viewData('page'), 'props.shell.menu', []);
    $achado = null;
    $walk = function (array $itens) use (&$walk, &$achado) {
        foreach ($itens as $item) {
            foreach ((array) ($item['ghosts'] ?? []) as $g) {
                if (str_starts_with((string) ($g['href'] ?? ''), '/manufacturing/')) {
                    $achado ??= $item;
                }
            }
            $walk((array) ($item['children'] ?? []));
        }
    };
    $walk($menu);

    return $achado;
}

function mfgMenuGhosts(?array $item): array
{
    return array_values(array_map(fn ($g) => (string) $g['href'], (array) ($item['ghosts'] ?? [])));
}

describe('UC-RECIPE-24 — o menu da Fabricação segue a permissão', function () {
    it('UC-RECIPE-24 só receitas: Receitas e Insumos, sem ordem, relatório, configurações nem "Nova ordem"', function () {
        $user = $this->usuarioComPermissoes(['manufacturing.access_recipe']);
        $resp = $this->actingAs($user)->get('/manufacturing/insumos');
        $resp->assertOk();

        $item = mfgMenuItem($resp);
        expect($item)->not->toBeNull('O item da Fabricação não veio no menu serializado.');
        expect(mfgMenuGhosts($item))->toBe(['/manufacturing/recipe', '/manufacturing/insumos']);
        expect($item['primary'] ?? null)->toBeNull();
        expect($item['href'])->toEndWith('/manufacturing/recipe');
    });

    it('UC-RECIPE-24 só produção: Ordens, Relatório e Configurações, e o item principal abre as ordens', function () {
        $user = $this->usuarioComPermissoes(['manufacturing.access_production']);
        $resp = $this->actingAs($user)->get('/manufacturing/production');
        $resp->assertOk();

        $item = mfgMenuItem($resp);
        expect($item)->not->toBeNull('O item da Fabricação não veio no menu serializado.');
        expect(mfgMenuGhosts($item))->toBe(['/manufacturing/production', '/manufacturing/report', '/manufacturing/settings']);
        expect($item['href'])->toEndWith('/manufacturing/production');
    });

    it('UC-RECIPE-24 âncora: com as duas permissões, os cinco itens', function () {
        $user = $this->usuarioComPermissoes(['manufacturing.access_recipe', 'manufacturing.access_production']);
        $resp = $this->actingAs($user)->get('/manufacturing/recipe');
        $resp->assertOk();

        expect(mfgMenuGhosts(mfgMenuItem($resp)))->toBe([
            '/manufacturing/recipe', '/manufacturing/insumos', '/manufacturing/production',
            '/manufacturing/report', '/manufacturing/settings',
        ]);
    });
});
