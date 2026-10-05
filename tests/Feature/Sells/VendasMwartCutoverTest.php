<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Cutover MWART das telas React de Vendas no GET comum, POR EMPRESA (`config/mwart.php`,
 * regra em `App\Support\Mwart`).
 *
 * Hoje as 7 telas abaixo só viram React com `X-Inertia`; GET comum (URL direta, menu) cai no
 * Blade. As chaves `vendas_*` ligam a tela React no GET comum para as empresas listadas na env
 * e NASCEM DESLIGADAS. Por tela, este arquivo prova:
 *   (a) flag desligada + GET comum → Blade;
 *   (b) flag ligada com a empresa na lista → GET comum devolve a Page React certa;
 *   (c) flag ligada com OUTRA empresa na lista → esta empresa continua no Blade;
 *   (d) com `X-Inertia` (flag desligada) → React, como hoje.
 * E mais: AJAX sem `X-Inertia` (DataTable / fetch da tela) segue em JSON com a flag ligada.
 *
 * Os Blades são trocados por stubs de uma linha: o que está sob teste é QUAL ramo o controller
 * escolhe, não o layout AdminLTE (que tem contrato próprio nos testes de cada tela).
 *
 * Tenant: o semeado (98, ADR 0358). Nunca biz=4. Lane: sells-pest.yml (MySQL). Não roda local.
 */
uses(DatabaseTransactions::class);

/** [chave de config, URL, Page React, view Blade] — o GET de cada tela. */
function vmcTelas(): array
{
    return [
        'pos' => ['vendas_pos_index', '/pos', 'Sells/Pos/Index', 'sale_pos.index'],
        'remessas' => ['vendas_shipments_index', '/shipments', 'Sells/Shipments/Index', 'sell.shipments'],
        'devolucoes' => ['vendas_sell_return_index', '/sell-return', 'SellReturn/Index', 'sell_return.index'],
        'descontos' => ['vendas_discount_index', '/discount', 'Discount/Index', 'discount.index'],
        'importacao' => ['vendas_import_sales', '/import-sales', 'ImportSales/Index', 'import_sales.index'],
        'pedidos' => ['vendas_sales_order_index', '/sales-order', 'SalesOrder/Index', 'sales_order.index'],
    ];
}

function vmcFlag(string $chave, bool $ligada, array $empresas): void
{
    config(["mwart.{$chave}" => ['enabled' => $ligada, 'business_ids' => $empresas]]);
}

/** Headers que o cliente Inertia manda de fato (X-Inertia E X-Requested-With — §5 2026-09-08). */
function vmcInertiaHeaders(): array
{
    $manifest = public_path('build-inertia/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

/** Troca cada Blade das telas por um stub de uma linha (ver docblock). */
function vmcStubsBlade(): void
{
    $dir = sys_get_temp_dir().'/vmc-blade-stubs';
    $views = ['sale_pos.index', 'sell.shipments', 'sell_return.index', 'discount.index',
        'import_sales.index', 'import_sales.preview', 'sales_order.index'];
    foreach ($views as $view) {
        $arquivo = $dir.'/'.str_replace('.', '/', $view).'.blade.php';
        if (! is_dir(dirname($arquivo))) {
            mkdir(dirname($arquivo), 0777, true);
        }
        file_put_contents($arquivo, "BLADE {$view}\n");
    }
    app('view')->getFinder()->prependLocation($dir);
}

/** CSV mínimo para a prévia, onde ela grava (public/uploads/temp). */
function vmcPlanilha(): UploadedFile
{
    $dir = public_path('uploads/temp');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $caminho = $dir.'/vmc_'.bin2hex(random_bytes(4)).'.csv';
    file_put_contents($caminho, "Fatura,SKU,Quantidade\nVMC-1,VMC-SKU,1\n");

    return new UploadedFile($caminho, 'vendas.csv', 'text/csv', null, true);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'business', 'users', 'discounts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outraEmpresa = $this->bizId + 1000; // qualquer id ≠ do tenant sob teste

    $id = DB::table('users')->insertGetId([
        'first_name' => 'VMC Cutover',
        'username' => 'vmc_'.uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $this->bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->user = User::findOrFail($id);
    foreach (['sell.view', 'sell.create', 'access_shipping', 'access_sell_return', 'discount.view',
        'so.view_all', 'access_all_locations'] as $p) {
        Permission::findOrCreate($p, 'web');
        $this->user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id]);

    // Estado de partida explícito: todas as chaves de Vendas desligadas.
    foreach (vmcTelas() as [$chave]) {
        vmcFlag($chave, false, []);
    }
    vmcStubsBlade();
});

it('(a) flag desligada: o GET comum segue no Blade', function (string $chave, string $url, string $page, string $view) {
    $this->get($url)->assertOk()->assertViewIs($view);
})->with(vmcTelas());

it('(b) flag ligada para a empresa: o GET comum devolve a Page React', function (string $chave, string $url, string $page, string $view) {
    vmcFlag($chave, true, [$this->bizId]);

    $res = $this->get($url);
    $res->assertOk();
    $res->assertInertia(fn (AssertableInertia $p) => $p->component($page));
})->with(vmcTelas());

it('(b) flag ligada sem lista de empresas vale para todas', function (string $chave, string $url, string $page, string $view) {
    vmcFlag($chave, true, []);

    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component($page));
})->with(vmcTelas());

it('(c) flag ligada só para outra empresa: esta continua no Blade', function (string $chave, string $url, string $page, string $view) {
    vmcFlag($chave, true, [$this->outraEmpresa]);

    $this->get($url)->assertOk()->assertViewIs($view);
})->with(vmcTelas());

it('(d) com X-Inertia a tela é React como hoje, com a flag desligada', function (string $chave, string $url, string $page, string $view) {
    $res = $this->withHeaders(vmcInertiaHeaders())->get($url);
    $res->assertOk();
    expect(json_decode($res->getContent(), true)['component'] ?? null)->toBe($page);
})->with(vmcTelas());

it('AJAX sem X-Inertia segue no JSON do DataTable mesmo com a flag ligada', function (string $chave, string $url) {
    vmcFlag($chave, true, [$this->bizId]);

    $res = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->get($url);
    $res->assertOk();
    expect($res->headers->has('X-Inertia'))->toBeFalse();
    expect($res->json('data'))->toBeArray();
})->with([
    'devolucoes' => ['vendas_sell_return_index', '/sell-return'],
    'descontos' => ['vendas_discount_index', '/discount'],
]);

it('prévia da importação: flag desligada no Blade, ligada na Page React, X-Inertia como hoje', function () {
    // (a) desligada → Blade
    $blade = $this->post('/import-sales/preview', ['sales' => vmcPlanilha()]);
    $blade->assertOk()->assertViewIs('import_sales.preview');
    @unlink(public_path('uploads/temp/'.$blade->viewData('file_name')));

    // (c) ligada só para outra empresa → Blade
    vmcFlag('vendas_import_sales', true, [$this->outraEmpresa]);
    $outra = $this->post('/import-sales/preview', ['sales' => vmcPlanilha()]);
    $outra->assertOk()->assertViewIs('import_sales.preview');
    @unlink(public_path('uploads/temp/'.$outra->viewData('file_name')));

    // (b) ligada para a empresa → React no POST comum do formulário
    vmcFlag('vendas_import_sales', true, [$this->bizId]);
    $react = $this->post('/import-sales/preview', ['sales' => vmcPlanilha()]);
    $react->assertOk();
    $react->assertInertia(fn (AssertableInertia $p) => $p->component('ImportSales/Preview'));
    @unlink(public_path('uploads/temp/'.$react->viewData('page')['props']['arquivo']));

    // (d) desligada + X-Inertia → React, como hoje
    vmcFlag('vendas_import_sales', false, []);
    $inertia = $this->withHeaders(vmcInertiaHeaders())->post('/import-sales/preview', ['sales' => vmcPlanilha()]);
    $inertia->assertOk();
    $pagina = json_decode($inertia->getContent(), true);
    expect($pagina['component'] ?? null)->toBe('ImportSales/Preview');
    @unlink(public_path('uploads/temp/'.$pagina['props']['arquivo']));
});
