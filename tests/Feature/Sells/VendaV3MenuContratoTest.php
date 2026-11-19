<?php

declare(strict_types=1);

use App\Http\Controllers\SellsV3Controller;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Item "Nova venda (V3)" do menu Vendas — aparece só onde a V3 grava e para quem a rota deixa entrar.
 *
 * [W] 2026-11-19 ligou a gravação da V3 (flag useV3SellsGravar) nas empresas de produção e pediu o
 * link no menu. O item segue as duas guardas da tela: a permissão do SellsV3Controller@create
 * (superadmin ou sell.create) e a flag por empresa — desligar a flag no GrowthBook tira o item
 * junto com a gravação, sem deploy.
 *
 * Caminho real: a requisição passa pelo AdminSidebarMenu, o menu é serializado em `shell.menu`
 * e o assert lê os hrefs que o React recebe. "Todas as vendas" (/sells) é o controle positivo:
 * se ele não aparece, o menu não foi serializado e os "não contém" passariam no vácuo.
 *
 * A flag vem de um FeatureFlagService parcial: só useV3SellsGravar é controlada, as demais seguem
 * a avaliação real. ⛔ Tenant de teste (ADR 0358), nunca biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Middleware/AdminSidebarMenu.php  bloco //Sell dropdown, item Nova venda (V3)
 * @see app/Http/Controllers/SellsV3Controller.php  create(), FLAG_GRAVAR
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    v3MenuFlag(true);
});

function v3MenuFlag(bool $ligada): void
{
    $GLOBALS['v3_menu_flag'] = $ligada;
    if (! (app()->bound(FeatureFlagService::class) && app(FeatureFlagService::class) instanceof \Mockery\MockInterface)) {
        $real = new FeatureFlagService();
        $mock = Mockery::mock(FeatureFlagService::class)->makePartial();
        $mock->shouldReceive('isOn')->andReturnUsing(
            fn (string $flag, array $attrs = []) => $flag === SellsV3Controller::FLAG_GRAVAR
                ? $GLOBALS['v3_menu_flag']
                : $real->isOn($flag, $attrs)
        );
        app()->instance(FeatureFlagService::class, $mock);
    }
}

/** Caminhos de todos os itens do menu serializado, achatados (pai + filhos). */
function v3MenuPaths(\Illuminate\Testing\TestResponse $resp): array
{
    $menu = (array) data_get($resp->viewData('page'), 'props.shell.menu', []);
    $paths = [];
    $walk = function (array $itens) use (&$walk, &$paths) {
        foreach ($itens as $item) {
            if (! empty($item['href'])) {
                $paths[] = (string) (parse_url((string) $item['href'], PHP_URL_PATH) ?? '');
            }
            $walk((array) ($item['children'] ?? []));
        }
    };
    $walk($menu);

    return $paths;
}

test('com sell.create e a flag ligada, o menu mostra Nova venda (V3) e a tela abre [T0]', function () {
    $this->actingAs($this->usuarioComPermissoes(['sell.view', 'sell.create', 'access_all_locations'], $this->business));

    $resp = $this->get('/sells');
    $resp->assertOk();
    $paths = v3MenuPaths($resp);

    $this->assertContains('/sells', $paths, 'controle positivo: o menu Vendas foi serializado');
    $this->assertContains('/sells/create-v3', $paths, 'com a gravação ligada o item aparece');
    $this->get('/sells/create-v3')->assertOk();
});

test('com a flag desligada, o menu não mostra Nova venda (V3) [T0]', function () {
    v3MenuFlag(false);
    $this->actingAs($this->usuarioComPermissoes(['sell.view', 'sell.create', 'access_all_locations'], $this->business));

    $resp = $this->get('/sells');
    $resp->assertOk();
    $paths = v3MenuPaths($resp);

    $this->assertContains('/sells', $paths, 'controle positivo: o menu Vendas foi serializado');
    $this->assertNotContains('/sells/create-v3', $paths, 'sem a gravação o item não aparece');
});

test('sem sell.create, o menu não mostra Nova venda (V3) e a tela dá 403 [T0]', function () {
    $this->actingAs($this->usuarioComPermissoes(['sell.view', 'access_all_locations'], $this->business));

    $resp = $this->get('/sells');
    $resp->assertOk();
    $paths = v3MenuPaths($resp);

    $this->assertContains('/sells', $paths, 'controle positivo: o menu Vendas foi serializado');
    $this->assertNotContains('/sells/create-v3', $paths, 'sem sell.create o item não aparece');
    $this->get('/sells/create-v3')->assertForbidden();
});
