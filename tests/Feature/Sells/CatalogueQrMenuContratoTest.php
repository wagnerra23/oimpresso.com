<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Item "Catálogo QR" do hub Vendas — o menu só mostra o link para quem a rota deixa entrar.
 *
 * Antes (até 2026-11-12) o ghost `catalogue-qr` era incondicional: todo usuário que via o hub
 * Vendas recebia o link, e quem não tinha o pacote `productcatalogue_module` ou a permissão
 * `product.view` caía em 403 ao clicar. A guarda da rota é CatalogueQrService::authorizeAccess,
 * e o AdminSidebarMenu passou a chamar o mesmo método, em vez de repetir a regra.
 *
 * O teste roda o caminho real: a requisição passa pelo AdminSidebarMenu, o menu é serializado
 * no prop compartilhado `shell.menu`, e o assert lê os ghosts que o React recebe. O ghost
 * WooCommerce, que segue incondicional, é o controle positivo: se ele não aparece, o menu não
 * foi serializado e os "não contém" passariam no vácuo.
 *
 * Também cobre o CheckUserLogin que entrou na stack admin do módulo: usuário com
 * allow_login = 0 recebe 403 na tela do QR.
 *
 * A assinatura vem de um ModuleUtil parcial (o pacote semeado na lane não é o que se testa);
 * a permissão do papel é a real. ⛔ Tenant de teste (ADR 0358), nunca biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Middleware/AdminSidebarMenu.php  bloco //Sell dropdown, chave 'ghosts'
 * @see Modules/ProductCatalogue/Services/CatalogueQrService.php  authorizeAccess()
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    cqrMenuAssinatura(true);
});

function cqrMenuAssinatura(bool $temModulo): void
{
    // Flag mutável em vez de um mock novo por chamada: o serviço resolvido na 1ª requisição
    // pode ser reaproveitado nas seguintes do mesmo teste.
    $GLOBALS['cqr_menu_tem_modulo'] = $temModulo;
    if (! (app()->bound(ModuleUtil::class) && app(ModuleUtil::class) instanceof \Mockery\MockInterface)) {
        $mu = Mockery::mock(ModuleUtil::class)->makePartial();
        $mu->shouldReceive('hasThePermissionInSubscription')
            ->andReturnUsing(fn ($biz, $perm) => $perm === 'productcatalogue_module' ? $GLOBALS['cqr_menu_tem_modulo'] : true);
        app()->instance(ModuleUtil::class, $mu);
    }
}

function cqrMenuEntrar($teste, array $permissoes): \App\User
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);

    return $user;
}

/** Hrefs dos ghosts de todos os itens do menu serializado, achatados (pai + filhos). */
function cqrMenuGhosts(\Illuminate\Testing\TestResponse $resp): array
{
    $menu = (array) data_get($resp->viewData('page'), 'props.shell.menu', []);
    $hrefs = [];
    $walk = function (array $itens) use (&$walk, &$hrefs) {
        foreach ($itens as $item) {
            foreach ((array) ($item['ghosts'] ?? []) as $ghost) {
                $hrefs[] = (string) ($ghost['href'] ?? '');
            }
            $walk((array) ($item['children'] ?? []));
        }
    };
    $walk($menu);

    return $hrefs;
}

test('com o pacote e product.view, o menu mostra Catálogo QR e a tela abre [T0]', function () {
    cqrMenuEntrar($this, ['sell.view', 'product.view', 'access_all_locations']);

    $resp = $this->get('/sells');
    $resp->assertOk();
    $ghosts = cqrMenuGhosts($resp);

    $this->assertContains('/woocommerce', $ghosts, 'controle positivo: o hub Vendas foi serializado com ghosts');
    $this->assertContains('/product-catalogue/catalogue-qr', $ghosts, 'quem passa pela guarda da rota vê o item');
    $this->get('/product-catalogue/catalogue-qr')->assertOk();
});

test('sem o pacote productcatalogue_module, o menu não mostra Catálogo QR e a tela dá 403 [T0]', function () {
    cqrMenuAssinatura(false);
    cqrMenuEntrar($this, ['sell.view', 'product.view', 'access_all_locations']);

    $resp = $this->get('/sells');
    $resp->assertOk();
    $ghosts = cqrMenuGhosts($resp);

    $this->assertContains('/woocommerce', $ghosts, 'controle positivo: o hub Vendas foi serializado com ghosts');
    $this->assertNotContains('/product-catalogue/catalogue-qr', $ghosts, 'sem o pacote o item não aparece');
    $this->get('/product-catalogue/catalogue-qr')->assertForbidden();
});

test('sem product.view, o menu não mostra Catálogo QR e a tela dá 403 [T0]', function () {
    cqrMenuEntrar($this, ['sell.view', 'access_all_locations']);

    $resp = $this->get('/sells');
    $resp->assertOk();
    $ghosts = cqrMenuGhosts($resp);

    $this->assertContains('/woocommerce', $ghosts, 'controle positivo: o hub Vendas foi serializado com ghosts');
    $this->assertNotContains('/product-catalogue/catalogue-qr', $ghosts, 'sem product.view o item não aparece');
    $this->get('/product-catalogue/catalogue-qr')->assertForbidden();
});

test('CheckUserLogin: usuário com allow_login = 0 recebe 403 na tela do QR, mesmo com pacote e permissão', function () {
    $user = cqrMenuEntrar($this, ['sell.view', 'product.view', 'access_all_locations']);

    // controle: o mesmo usuário, com login permitido, entra
    $this->get('/product-catalogue/catalogue-qr')->assertOk();

    $user->forceFill(['allow_login' => 0])->save();
    $this->actingAs($user->fresh());

    $this->get('/product-catalogue/catalogue-qr')->assertForbidden();
});
