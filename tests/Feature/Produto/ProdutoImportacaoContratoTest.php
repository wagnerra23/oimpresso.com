<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da tela Produto/Importacao (`/import-products` modo produtos · `/import-opening-stock` modo estoque) — playbook Produto · thread 05.
 *
 * Os UCs vêm do contrato, não do código:
 *   resources/js/Pages/Produto/Importacao/Index.casos.md (UC-PIMP-01..10)
 *
 * Regra mestre VALOR/ESTOQUE: a importação MOVE estoque. A thread só troca a tela e acrescenta o
 * dry-run; o UC-PIMP-04 prova por dois caminhos (store direto × conferir→store, em dois negócios)
 * que o que fica gravado é idêntico.
 *
 * ⛔ Tenant 98 (ADR 0358) contra o cliente fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Controllers/ImportProductsController.php index() · store() · conferencia()
 * @see app/Http/Controllers/ImportOpeningStockController.php index() · store() · conferencia()
 */
uses(DatabaseTransactions::class);

const PIMP_TAG = '[pimp05]';

function pimpUsuario(int $bizId, array $permissoes = ['product.create']): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Importacao T05', 'username' => 'pimp_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function pimpLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id, 'user.id' => $user->id,
        'business.default_profit_percent' => 25, 'financial_year.start' => '2026-01-01',
        // num_f() do cálculo de preço lê os separadores da sessão, como em produção.
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    return $test->actingAs($user);
}

/** Planilha de 37 colunas: 1 produto com estoque inicial + 1 sem. Cabeçalho na 1ª linha. */
function pimpPlanilha(string $unidade, string $local, string $sufixo): UploadedFile
{
    $linha = function (string $nome, string $sku, string $estoque) use ($unidade, $local, $sufixo): string {
        $c = array_fill(0, 37, '');
        [$c[0], $c[1], $c[2], $c[3], $c[5]] = ["$nome " . PIMP_TAG, 'Marca ' . PIMP_TAG, $unidade, 'Categoria ' . PIMP_TAG, $sku . $sufixo];
        [$c[7], $c[12], $c[13], $c[17], $c[19], $c[21], $c[22]] = ['1', 'exclusive', 'single', '10', '50', $estoque, $estoque === '' ? '' : $local];

        return implode(',', $c);
    };
    $csv = implode("\n", [implode(',', array_fill(0, 37, 'col')), $linha('Lona fosca', 'PIMP-A', '5'), $linha('Vinil branco', 'PIMP-B', '')]);

    return UploadedFile::fake()->createWithContent('produtos.csv', $csv . "\n");
}

/** O que a importação gravou no negócio — sem ids nem datas, comparável entre negócios. */
function pimpRetrato(int $bizId): array
{
    return DB::table('products as p')
        ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
        ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
        ->join('variations as v', 'v.product_id', '=', 'p.id')
        ->leftJoin('variation_location_details as vld', 'vld.variation_id', '=', 'v.id')
        ->leftJoin('purchase_lines as pl', 'pl.variation_id', '=', 'v.id')
        ->leftJoin('transactions as t', 't.id', '=', 'pl.transaction_id')
        ->where('p.business_id', $bizId)->where('p.name', 'like', '%' . PIMP_TAG . '%')
        ->orderBy('p.name')
        ->get(['p.name', 'p.sku', 'p.type', 'p.enable_stock', 'p.tax_type', 'b.name as marca', 'c.name as categoria',
            'v.default_purchase_price', 'v.dpp_inc_tax', 'v.profit_percent', 'v.default_sell_price', 'v.sell_price_inc_tax',
            'vld.qty_available', 'pl.quantity', 'pl.purchase_price', 'pl.purchase_price_inc_tax', 't.type as tipo_tx', 't.final_total'])
        ->map(fn ($r) => (array) $r)->all();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->vizinho = $this->seededSupportClientTenant();
    // Assinatura/cota do Superadmin não é o que está sob teste: fixa "assinado, com cota".
    $this->partialMock(ModuleUtil::class, function ($m) {
        $m->shouldReceive('isSubscribed')->andReturn(true);
        $m->shouldReceive('isQuotaAvailable')->andReturn(true);
    });
    foreach ([$this->biz->id, $this->vizinho->id] as $b) {
        EstoqueFixture::unitId($b);
        EstoqueFixture::locationId($b, '-PIMP');
    }
});

it('UC-PIMP-01 · /import-products abre Produto/Importacao/Index; ?classico=1 segue Blade; sem permissão 403', function () {
    $user = pimpUsuario($this->biz->id);

    pimpLogin($this, $user)->get('/import-products')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/Importacao/Index', false)->where('modo', 'produtos'));
    pimpLogin($this, $user)
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get('/import-products?classico=1')->assertOk()->assertViewIs('import_products.index');
    pimpLogin($this, pimpUsuario($this->biz->id, []))->get('/import-products')->assertForbidden();
});

it('UC-PIMP-02 · conferência devolve uma linha por produto que seria criado', function () {
    $user = pimpUsuario($this->biz->id);

    pimpLogin($this, $user)->post('/import-products/store', [
        'products_csv' => pimpPlanilha('EST-UN', 'EST-FIX-LOC-PIMP', '-C2'), 'conferir' => 1,
    ])->assertRedirect('import-products');

    $linhas = session('conferencia');
    expect($linhas)->toHaveCount(2);
    expect($linhas[0]['linha'])->toBe(1);
    expect($linhas[0]['sku'])->toBe('PIMP-A-C2');
    expect((float) $linhas[0]['estoque'])->toBe(5.0);
    expect($linhas[1]['linha'])->toBe(2);
    expect($linhas[1]['estoque'])->toBeNull();
});

it('UC-PIMP-03 · conferência não grava produto, marca, categoria nem estoque', function () {
    $user = pimpUsuario($this->biz->id);
    $antes = [DB::table('transactions')->where('business_id', $this->biz->id)->count(),
        DB::table('variation_location_details')->count()];

    pimpLogin($this, $user)->post('/import-products/store', [
        'products_csv' => pimpPlanilha('EST-UN', 'EST-FIX-LOC-PIMP', '-C3'), 'conferir' => 1,
    ])->assertRedirect('import-products');

    expect(session('conferencia'))->toHaveCount(2); // a conferência rodou — não é vácuo
    expect(pimpRetrato($this->biz->id))->toBe([]);
    expect(DB::table('brands')->where('business_id', $this->biz->id)->where('name', 'Marca ' . PIMP_TAG)->exists())->toBeFalse();
    expect(DB::table('categories')->where('business_id', $this->biz->id)->where('name', 'Categoria ' . PIMP_TAG)->exists())->toBeFalse();
    expect([DB::table('transactions')->where('business_id', $this->biz->id)->count(),
        DB::table('variation_location_details')->count()])->toBe($antes);
});

it('UC-PIMP-04 · conferir→enviar grava exatamente o que o envio direto grava [T0]', function () {
    // Caminho antigo: envio direto no negócio 98.
    pimpLogin($this, pimpUsuario($this->biz->id))->post('/import-products/store', [
        'products_csv' => pimpPlanilha('EST-UN', 'EST-FIX-LOC-PIMP', ''),
    ])->assertRedirect('import-products');
    $antigo = pimpRetrato($this->biz->id);

    // Caminho novo: conferir e depois enviar a mesma planilha no negócio 99.
    $user = pimpUsuario($this->vizinho->id);
    pimpLogin($this, $user)->post('/import-products/store', [
        'products_csv' => pimpPlanilha('EST-UN', 'EST-FIX-LOC-PIMP', ''), 'conferir' => 1,
    ])->assertRedirect('import-products');
    expect(pimpRetrato($this->vizinho->id))->toBe([]);
    pimpLogin($this, $user)->post('/import-products/store', [
        'products_csv' => pimpPlanilha('EST-UN', 'EST-FIX-LOC-PIMP', ''),
    ])->assertRedirect('import-products');
    $novo = pimpRetrato($this->vizinho->id);

    expect($antigo)->toHaveCount(2);
    $comEstoque = collect($antigo)->firstWhere('name', 'Lona fosca ' . PIMP_TAG);
    expect((float) $comEstoque['qty_available'])->toBe(5.0);
    expect($comEstoque['tipo_tx'])->toBe('opening_stock');
    expect($novo)->toBe($antigo);
});

it('UC-PIMP-05 · unidade que só existe em outro negócio é recusada com a linha, sem gravar [T0]', function () {
    DB::table('units')->insert([
        'business_id' => $this->vizinho->id, 'actual_name' => 'So do vizinho ' . PIMP_TAG, 'short_name' => 'PIMPVIZ',
        'allow_decimal' => 0, 'created_by' => EstoqueFixture::userId($this->vizinho->id),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = pimpUsuario($this->biz->id);

    pimpLogin($this, $user)->post('/import-products/store', [
        'products_csv' => pimpPlanilha('PIMPVIZ', 'EST-FIX-LOC-PIMP', '-C5'), 'conferir' => 1,
    ])->assertRedirect('import-products');

    expect(session('conferencia'))->toBeNull();
    expect((string) session('notification.msg'))->toContain('PIMPVIZ')->toContain('row no. 1');
    expect(pimpRetrato($this->biz->id))->toBe([]);
    expect(pimpRetrato($this->vizinho->id))->toBe([]);
});

// ─── Modo estoque inicial (`/import-opening-stock`) — PR-b da thread 05 · UC-PIMP-06..10 ───

/** Produto com estoque gerenciado e SKU fixo (o mesmo nos dois negócios — a planilha é a mesma). */
function pimpProdutoEstoque(int $bizId, string $sku): void
{
    $p = EstoqueFixture::singleProduct($bizId);
    DB::table('variations')->where('id', $p->variationId())->update(['sub_sku' => $sku]);
}

/** Planilha de 6 colunas: A duas vezes no mesmo local (a 2ª soma no mesmo lançamento) + B. */
function pimpPlanilhaEstoque(string $local = 'EST-FIX-LOC-PIMP'): UploadedFile
{
    $csv = implode("\n", ['sku,local,qtd,custo,lote,validade',
        "PIMP05-A,$local,7,12.5,L1,", "PIMP05-A,$local,3,12.5,L1,", "PIMP05-B,$local,4,8,,"]);

    return UploadedFile::fake()->createWithContent('estoque.csv', $csv . "\n");
}

/** O que o estoque inicial gravou no negócio — sem ids nem datas, comparável entre negócios. */
function pimpRetratoEstoque(int $bizId): array
{
    return DB::table('purchase_lines as pl')
        ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
        ->join('variations as v', 'v.id', '=', 'pl.variation_id')
        ->join('business_locations as bl', 'bl.id', '=', 't.location_id')
        ->leftJoin('variation_location_details as vld', fn ($j) => $j->on('vld.variation_id', '=', 'v.id')->on('vld.location_id', '=', 't.location_id'))
        ->where('t.business_id', $bizId)->whereIn('v.sub_sku', ['PIMP05-A', 'PIMP05-B'])
        ->orderBy('v.sub_sku')->orderBy('pl.id')
        ->get(['v.sub_sku', 'bl.name as local', 't.type', 't.status', 't.final_total', 't.total_before_tax', 'pl.quantity',
            'pl.purchase_price', 'pl.purchase_price_inc_tax', 'pl.item_tax', 'pl.lot_number', 'vld.qty_available'])
        ->map(fn ($r) => (array) $r)->all();
}

it('UC-PIMP-06 · /import-opening-stock abre a Importacao no modo estoque; ?classico=1 segue Blade; sem permissão 403', function () {
    $user = pimpUsuario($this->biz->id, ['product.opening_stock']);

    pimpLogin($this, $user)->get('/import-opening-stock')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/Importacao/Index', false)->where('modo', 'estoque'));
    pimpLogin($this, $user)
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get('/import-opening-stock?classico=1')->assertOk()->assertViewIs('import_opening_stock.index');
    pimpLogin($this, pimpUsuario($this->biz->id))->get('/import-opening-stock')->assertForbidden();
});

it('UC-PIMP-07 · conferência do estoque mostra saldo e total do lançamento como gravados', function () {
    pimpProdutoEstoque($this->biz->id, 'PIMP05-A');
    pimpProdutoEstoque($this->biz->id, 'PIMP05-B');

    pimpLogin($this, pimpUsuario($this->biz->id, ['product.opening_stock']))->post('/import-opening-stock/store', [
        'products_csv' => pimpPlanilhaEstoque(), 'conferir' => 1,
    ])->assertRedirect('import-opening-stock');

    $l = session('conferencia');
    expect($l)->toHaveCount(3);
    expect([$l[0]['linha'], $l[0]['sku'], $l[0]['local']])->toBe([1, 'PIMP05-A', 'EST-FIX-LOC-PIMP']);
    expect([(float) $l[1]['saldo'], (float) $l[1]['total']])->toBe([10.0, 125.0]); // 7+3 no mesmo lançamento
    expect([(float) $l[2]['saldo'], (float) $l[2]['total']])->toBe([4.0, 32.0]);
});

it('UC-PIMP-08 · conferência do estoque não deixa lançamento, linha nem saldo', function () {
    pimpProdutoEstoque($this->biz->id, 'PIMP05-A');
    pimpProdutoEstoque($this->biz->id, 'PIMP05-B');
    $antes = [DB::table('transactions')->where('business_id', $this->biz->id)->count(),
        DB::table('purchase_lines')->count(), DB::table('variation_location_details')->count()];

    pimpLogin($this, pimpUsuario($this->biz->id, ['product.opening_stock']))->post('/import-opening-stock/store', [
        'products_csv' => pimpPlanilhaEstoque(), 'conferir' => 1,
    ])->assertRedirect('import-opening-stock');

    expect(session('conferencia'))->toHaveCount(3); // a conferência rodou — não é vácuo
    expect(pimpRetratoEstoque($this->biz->id))->toBe([]);
    expect([DB::table('transactions')->where('business_id', $this->biz->id)->count(),
        DB::table('purchase_lines')->count(), DB::table('variation_location_details')->count()])->toBe($antes);
});

it('UC-PIMP-09 · conferir→enviar lança exatamente o estoque que o envio direto lança [T0]', function () {
    foreach ([$this->biz->id, $this->vizinho->id] as $b) {
        pimpProdutoEstoque($b, 'PIMP05-A');
        pimpProdutoEstoque($b, 'PIMP05-B');
    }
    // Caminho antigo: envio direto no negócio 98.
    pimpLogin($this, pimpUsuario($this->biz->id, ['product.opening_stock']))->post('/import-opening-stock/store', [
        'products_csv' => pimpPlanilhaEstoque(),
    ])->assertRedirect('import-opening-stock');
    $antigo = pimpRetratoEstoque($this->biz->id);

    // Caminho novo: conferir e depois enviar a mesma planilha no negócio 99.
    $user = pimpUsuario($this->vizinho->id, ['product.opening_stock']);
    pimpLogin($this, $user)->post('/import-opening-stock/store', ['products_csv' => pimpPlanilhaEstoque(), 'conferir' => 1])
        ->assertRedirect('import-opening-stock');
    expect(pimpRetratoEstoque($this->vizinho->id))->toBe([]);
    pimpLogin($this, $user)->post('/import-opening-stock/store', ['products_csv' => pimpPlanilhaEstoque()])
        ->assertRedirect('import-opening-stock');
    $novo = pimpRetratoEstoque($this->vizinho->id);

    expect($antigo)->toHaveCount(3);
    expect([$antigo[0]['type'], (float) $antigo[0]['qty_available'], (float) $antigo[0]['final_total']])
        ->toBe(['opening_stock', 10.0, 125.0]); // saldo não-zero: não compara vazio com vazio
    expect($novo)->toBe($antigo);
});

it('UC-PIMP-10 · SKU ou local que só existem em outro negócio são recusados com a linha, sem gravar [T0]', function () {
    pimpProdutoEstoque($this->vizinho->id, 'PIMP05-A');
    $user = pimpUsuario($this->biz->id, ['product.opening_stock']);

    pimpLogin($this, $user)->post('/import-opening-stock/store', ['products_csv' => pimpPlanilhaEstoque(), 'conferir' => 1])
        ->assertRedirect('import-opening-stock');
    expect(session('conferencia'))->toBeNull();
    expect((string) session('notification.msg'))->toContain('PIMP05-A')->toContain('row no. 1');

    pimpProdutoEstoque($this->biz->id, 'PIMP05-A');
    pimpProdutoEstoque($this->biz->id, 'PIMP05-B');
    EstoqueFixture::locationId($this->vizinho->id, '-SOVIZ');
    pimpLogin($this, $user)->post('/import-opening-stock/store', ['products_csv' => pimpPlanilhaEstoque('EST-FIX-LOC-SOVIZ')])
        ->assertRedirect('import-opening-stock');
    expect((string) session('notification.msg'))->toContain('EST-FIX-LOC-SOVIZ')->toContain('row no. 1');

    expect(pimpRetratoEstoque($this->biz->id))->toBe([]);
    expect(pimpRetratoEstoque($this->vizinho->id))->toBe([]);
});
