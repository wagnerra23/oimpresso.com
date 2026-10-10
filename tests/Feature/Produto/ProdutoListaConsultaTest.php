<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Lista de produtos no desenho do protótipo (`prototipo-ui/cowork/Felipe/produto-blade.jsx`,
 * vista `lista`) — etapa 1: os DADOS da Page React `Produto/Index` (prop `lista`).
 *
 * O contrato é a lista Blade que roda hoje em `/products`: a tela nova tem que listar os
 * MESMOS produtos para os MESMOS filtros e buscar com a MESMA regra. Por isso o 1º caso
 * compara as duas pelo próprio servidor (DataTable do Blade × prop `lista`), em vez de
 * afirmar o que uma delas "deveria" trazer.
 *
 * Regra da busca medida em produção em 2026-10-10 (DataTable do Blade + config/datatables.php):
 * cada palavra precisa aparecer em alguma coluna; colunas: nome, SKU do produto, SKU da
 * variação, tipo, categoria, marca, campos personalizados 1-7.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

function plcVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

function plcUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'PLC Lista',
        'username' => 'plc_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function plcLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);

    return $test->actingAs($user);
}

/** A prop `lista` da Page React, pedida por partial reload (é deferida). */
function plcLista(object $test, array $query = []): array
{
    $url = '/products' . ($query ? '?' . http_build_query($query) : '');
    $resp = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => plcVersao(),
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'lista',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get($url);
    $resp->assertOk();
    $page = json_decode($resp->getContent(), true);
    expect($page['component'] ?? null)->toBe('Produto/Index');
    expect($page['props'] ?? [])->toHaveKey('lista');

    return $page['props']['lista'];
}

/** Os ids que o DataTable da lista Blade devolve (todas as linhas, mesmos filtros). */
function plcIdsBlade(object $test, array $filtros): array
{
    $query = array_merge(['draw' => 1, 'start' => 0, 'length' => -1], $filtros);
    $resp = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->get('/products?' . http_build_query($query));
    $resp->assertOk();
    $json = json_decode($resp->getContent(), true);
    expect($json)->toHaveKey('data');
    $ids = array_map(fn ($r) => (int) $r['id'], $json['data']);
    sort($ids);

    return $ids;
}

function plcIdsReact(array $lista): array
{
    $ids = array_map(fn ($r) => (int) $r['id'], $lista['data']);
    sort($ids);

    return $ids;
}

function plcCategoria(int $bizId, string $nome): int
{
    return (int) DB::table('categories')->insertGetId([
        'name' => $nome,
        'business_id' => $bizId,
        'parent_id' => 0,
        'category_type' => 'product',
        'created_by' => EstoqueFixture::userId($bizId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function plcMarca(int $bizId, string $nome): int
{
    return (int) DB::table('brands')->insertGetId([
        'name' => $nome,
        'business_id' => $bizId,
        'created_by' => EstoqueFixture::userId($bizId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->bizId = (int) $this->seededTenant()->id;
});

it('a lista nova traz os mesmos produtos que a lista antiga, filtro a filtro', function () {
    $biz = $this->bizId;
    $cat = plcCategoria($biz, 'PLC Categoria ' . uniqid());
    $marca = plcMarca($biz, 'PLC Marca ' . uniqid());

    $unico = EstoqueFixture::singleProduct($biz);
    $variavel = EstoqueFixture::variableProduct($biz, 3);
    $inativo = EstoqueFixture::singleProduct($biz);
    $naoVenda = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $unico->productId)->update(['category_id' => $cat]);
    DB::table('products')->where('id', $variavel->productId)->update(['brand_id' => $marca]);
    DB::table('products')->where('id', $inativo->productId)->update(['is_inactive' => 1]);
    DB::table('products')->where('id', $naoVenda->productId)->update(['not_for_selling' => 1]);

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations']));

    // [filtros do Blade, filtros da lista nova, produto que TEM de aparecer]
    $casos = [
        [[], [], $unico->productId],
        [['type' => 'variable'], ['type' => 'variable'], $variavel->productId],
        [['active_state' => 'inactive'], ['active_state' => 'inactive'], $inativo->productId],
        [['active_state' => 'active'], ['active_state' => 'active'], $unico->productId],
        [['category_id' => $cat], ['category_id' => $cat], $unico->productId],
        [['brand_id' => $marca], ['brand_id' => $marca], $variavel->productId],
        [['not_for_selling' => 'true'], ['not_for_selling' => 1], $naoVenda->productId],
    ];

    foreach ($casos as [$blade, $nova, $esperado]) {
        $idsBlade = plcIdsBlade($this, $blade);
        $idsNova = plcIdsReact(plcLista($this, array_merge($nova, ['per_page' => -1])));

        // Pré-condição anti-vácuo: o produto do cenário está nas duas — comparar duas
        // listas vazias provaria nada.
        expect($idsBlade)->toContain($esperado);
        expect($idsNova)->toEqual($idsBlade);
    }
});

it('a busca acha pelo nome em qualquer ordem e exige todas as palavras', function () {
    $biz = $this->bizId;
    $marcador = 'PLCREF' . random_int(10000, 99999);
    $p = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $p->productId)->update(['name' => "CAMISA POLO {$marcador} AZUL"]);

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations']));

    expect(plcIdsReact(plcLista($this, ['q' => $marcador])))->toBe([$p->productId]);
    expect(plcIdsReact(plcLista($this, ['q' => "azul {$marcador}"])))->toBe([$p->productId]);
    expect(plcIdsReact(plcLista($this, ['q' => "{$marcador} vermelho"])))->toBe([]);
});

it('a busca acha pelo SKU da variação, pela categoria, pela marca e pelo campo personalizado', function () {
    $biz = $this->bizId;
    $sufixo = strtoupper(bin2hex(random_bytes(4)));

    $variavel = EstoqueFixture::variableProduct($biz, 2);
    DB::table('variations')->where('id', $variavel->variations[1]['variation_id'])->update(['sub_sku' => "VARSKU{$sufixo}"]);

    $comCategoria = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $comCategoria->productId)
        ->update(['category_id' => plcCategoria($biz, "CATBUSCA{$sufixo}")]);

    $comMarca = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $comMarca->productId)
        ->update(['brand_id' => plcMarca($biz, "MARCABUSCA{$sufixo}")]);

    $comCampo = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $comCampo->productId)->update(['product_custom_field1' => "CAMPO{$sufixo}"]);

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations']));

    expect(plcIdsReact(plcLista($this, ['q' => "VARSKU{$sufixo}"])))->toBe([$variavel->productId]);
    expect(plcIdsReact(plcLista($this, ['q' => "CATBUSCA{$sufixo}"])))->toBe([$comCategoria->productId]);
    expect(plcIdsReact(plcLista($this, ['q' => "MARCABUSCA{$sufixo}"])))->toBe([$comMarca->productId]);
    expect(plcIdsReact(plcLista($this, ['q' => "CAMPO{$sufixo}"])))->toBe([$comCampo->productId]);
});

it('a lista pagina no servidor e não corta o catálogo', function () {
    $biz = $this->bizId;
    $marcador = 'PLCPAG' . strtoupper(bin2hex(random_bytes(3)));
    $ids = [];
    for ($i = 1; $i <= 30; $i++) {
        $p = EstoqueFixture::singleProduct($biz, false);
        DB::table('products')->where('id', $p->productId)
            ->update(['name' => $marcador . ' ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        $ids[] = $p->productId;
    }
    sort($ids);

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations']));

    $p1 = plcLista($this, ['q' => $marcador, 'per_page' => 25]);
    expect($p1['total'])->toBe(30);
    expect($p1['last_page'])->toBe(2);
    expect(count($p1['data']))->toBe(25);

    $p2 = plcLista($this, ['q' => $marcador, 'per_page' => 25, 'page' => 2]);
    expect(count($p2['data']))->toBe(5);
    expect(array_values(array_unique(array_merge(plcIdsReact($p1), plcIdsReact($p2)))))->toEqual($ids);

    // Tamanho fora das opções do DataTable (25/50/100/200/500/1000/todas) cai no padrão.
    expect(plcLista($this, ['q' => $marcador, 'per_page' => 37])['per_page'])->toBe(25);
    // "Todas" traz o catálogo inteiro numa página.
    expect(count(plcLista($this, ['q' => $marcador, 'per_page' => -1])['data']))->toBe(30);
});

it('preço de compra e de venda só viajam para quem pode vê-los', function () {
    $biz = $this->bizId;
    $marcador = 'PLCPRECO' . strtoupper(bin2hex(random_bytes(3)));
    $p = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $p->productId)->update(['name' => $marcador]);

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations']));
    $sem = plcLista($this, ['q' => $marcador])['data'][0];
    expect($sem)->not->toHaveKey('compra_min');
    expect($sem)->not->toHaveKey('venda_min');

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations', 'view_purchase_price', 'access_default_selling_price']));
    $com = plcLista($this, ['q' => $marcador])['data'][0];
    // EstoqueFixture grava dpp_inc_tax = 10 e sell_price_inc_tax = 20 na variação.
    expect($com['compra_min'])->toEqual(10.0);
    expect($com['venda_min'])->toEqual(20.0);
});

it('a lista só mostra produtos da própria empresa', function () {
    $biz = $this->bizId;
    // O 2º business é qualquer outro além do tenant da sessão (o `secondBusinessId` da fixture
    // é relativo ao tenant DELA, que pode não ser o `seededTenant`).
    $outra = (int) DB::table('business')->where('id', '!=', $biz)->orderBy('id')->value('id') ?: null;
    if ($outra === null) {
        $this->markTestSkipped('Sem 2º business semeado para o cruzamento.');
    }
    $marcador = 'PLCTENANT' . strtoupper(bin2hex(random_bytes(3)));
    $meu = EstoqueFixture::singleProduct($biz);
    $alheio = EstoqueFixture::singleProduct($outra);
    DB::table('products')->whereIn('id', [$meu->productId, $alheio->productId])->update(['name' => $marcador]);

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations']));

    expect(plcIdsReact(plcLista($this, ['q' => $marcador])))->toBe([$meu->productId]);
});

it('o estoque é a soma das variações e marca quem está abaixo do alerta', function () {
    $biz = $this->bizId;
    $local = EstoqueFixture::locationId($biz);
    $marcador = 'PLCESTOQUE' . strtoupper(bin2hex(random_bytes(3)));

    $variavel = EstoqueFixture::variableProduct($biz, 2);
    EstoqueFixture::setStock($variavel, 0, $local, 3);
    EstoqueFixture::setStock($variavel, 1, $local, 4);
    $semControle = EstoqueFixture::singleProduct($biz, false);
    DB::table('products')->where('id', $variavel->productId)->update(['name' => $marcador . ' A', 'alert_quantity' => 10]);
    DB::table('products')->where('id', $semControle->productId)->update(['name' => $marcador . ' B']);

    plcLogin($this, plcUsuario($biz, ['product.view', 'access_all_locations']));
    $linhas = collect(plcLista($this, ['q' => $marcador])['data'])->keyBy('id');

    expect($linhas[$variavel->productId]['estoque'])->toEqual(7.0);
    expect($linhas[$variavel->productId]['abaixo_do_alerta'])->toBeTrue();
    expect($linhas[$variavel->productId]['variacoes'])->toBe(2);
    expect($linhas[$semControle->productId]['estoque'])->toBeNull();
    expect($linhas[$semControle->productId]['controla_estoque'])->toBeFalse();
});
