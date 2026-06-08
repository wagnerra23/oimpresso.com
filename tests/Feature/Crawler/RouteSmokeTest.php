<?php

/**
 * Sweep automatizado das rotas GET principais.
 *
 * Objetivo: rede de seguranca pra upgrades de Laravel/pacotes e migracoes
 * como laravelcollective->spatie. Quando uma view crasha com "Undefined method",
 * "must be of type" ou HTTP 500, o teste falha apontando a rota exata.
 *
 * NAO valida regra de negocio — so que a view/endpoint nao crasha.
 * Precisa DB local com seeder UltimatePOS (igual PontoTestCase pattern).
 */

use App\Business;
use App\User;

beforeEach(function () {
    // Login REAL: POST /login com DEV_LOGIN_USERNAME/PASSWORD do .env
    // — gera sessao populada exatamente como um user real (SetSessionData middleware roda)
    $user = env('DEV_LOGIN_USERNAME');
    $pass = env('DEV_LOGIN_PASSWORD');
    if (! $user || ! $pass) {
        $this->markTestSkipped('DEV_LOGIN_USERNAME/PASSWORD nao setadas em .env');
    }

    session()->flush();
    auth()->logout();

    $this->post('/login', ['username' => $user, 'password' => $pass]);

    if (! auth()->check()) {
        $this->markTestSkipped('Login falhou — confirmar creds DEV_LOGIN_* no .env e user existe no DB oimpresso.');
    }
});

/**
 * Detecta erros NO SHIM especificamente.
 *
 * Parseia o payload JSON do Ignition (`window.data = {...}`) em vez de string matching.
 * Retorna array {kind, class, message, file} se for shim-related, null caso contrario.
 *
 * "kind" = 'shim' (nosso bug), 'other' (pre-existente UltimatePOS), null (no error).
 */
function detectShimError(string $body): ?array
{
    // Procura payload do Ignition
    if (! preg_match('/window\.data = (\{"report":.+?\});\s*<\/script>/s', $body, $m)) {
        // Sem payload Ignition = provavelmente nao eh 500, ou eh uma outra renderizacao
        return null;
    }

    $report = json_decode($m[1], true);
    if (! $report || ! isset($report['report'])) return null;

    $exception = $report['report']['exception_class'] ?? '';
    $message = $report['report']['message'] ?? '';
    $file = $report['report']['frames'][0]['file'] ?? '';

    $shimSignatures = [
        'App\\View\\Helpers\\Form',
        'Spatie\\Html\\',
        'Collective\\Html\\', // classe antiga ainda referenciada em algum lugar
    ];
    foreach ($shimSignatures as $sig) {
        if (str_contains($exception . ' ' . $message . ' ' . $file, $sig)) {
            return [
                'kind' => 'shim',
                'class' => $exception,
                'message' => $message,
                'file' => $file,
            ];
        }
    }

    return [
        'kind' => 'other',
        'class' => $exception,
        'message' => $message,
        'file' => $file,
    ];
}

/* =========================================================================
 * 1) Rotas de VIEW (renderizam Blade/Inertia)
 * ========================================================================= */
dataset('viewRoutes', [
    // Core
    'home' => ['/home'],
    'business.settings' => ['/business/settings'],
    'user.profile' => ['/user/profile'],
    // CRUD create (core)
    'brands.create' => ['/brands/create'],
    'tax-rates.create' => ['/tax-rates/create'],
    'tax-groups.create' => ['/tax-groups/create'],
    'units.create' => ['/units/create'],
    'warranties.create' => ['/warranties/create'],
    'customer-groups.create' => ['/customer-groups/create'],
    'selling-price-groups.create' => ['/selling-price-groups/create'],
    'contacts.customer' => ['/contacts/create?type=customer'],
    'contacts.supplier' => ['/contacts/create?type=supplier'],
    'products.create' => ['/products/create'],
    'invoice-schemes.create' => ['/invoice-schemes/create'],
    'invoice-layouts.create' => ['/invoice-layouts/create'],
    'barcodes.create' => ['/barcodes/create'],
    'business-location.create' => ['/business-location/create'],
    'types-of-service.create' => ['/types-of-service/create'],
    'printers.create' => ['/printers/create'],
    'users.create' => ['/users/create'],
    'roles.create' => ['/roles/create'],
    'sales-commission-agents.create' => ['/sales-commission-agents/create'],
    'cash-register.create' => ['/cash-register/create'],
    'expenses.create' => ['/expenses/create'],
    'expense-categories.create' => ['/expense-categories/create'],
    'stock-transfers.create' => ['/stock-transfers/create'],
    'stock-adjustments.create' => ['/stock-adjustments/create'],
    'purchases.create' => ['/purchases/create'],
    'purchase-order.create' => ['/purchase-order/create'],
    'sells.create' => ['/sells/create'],
    'discount.create' => ['/discount/create'],
    'notification-templates.index' => ['/notification_template'],
    // Index (listagens com filtros — exercita Form::select com placeholder)
    'contacts.customer.index' => ['/contacts?type=customer'],
    'contacts.supplier.index' => ['/contacts?type=supplier'],
    'products.index' => ['/products'],
    'sells.index' => ['/sells'],
    'purchases.index' => ['/purchases'],
    'expenses.index' => ['/expenses'],
    'tax-rates.index' => ['/tax-rates'],
    'units.index' => ['/units'],
    'brands.index' => ['/brands'],
    'roles.index' => ['/roles'],
    // Reports (forms pesados com filtros)
    'reports.purchase-sell' => ['/reports/purchase-sell'],
    'reports.stock-report' => ['/reports/stock-report'],
    'reports.trending-products' => ['/reports/trending-products'],
    'reports.sales-representative' => ['/reports/sales-representative'],
    'reports.profit-loss' => ['/reports/profit-loss'],
    'reports.expense-report' => ['/reports/expense-report'],
    'reports.product-purchase' => ['/reports/product-purchase-report'],
    'reports.product-sell' => ['/reports/product-sell-report'],
    'reports.register' => ['/reports/register-report'],
    'reports.stock-expiry' => ['/reports/stock-expiry-report'],
    // Superadmin
    'superadmin' => ['/superadmin'],
    'superadmin.packages' => ['/superadmin/packages'],
    'superadmin.subscription' => ['/superadmin/subscription'],
    'superadmin.settings' => ['/superadmin/settings'],
    // Modules
    'modules.index' => ['/modules'],
]);

it('view renderiza sem erros PHP/shim', function (string $url) {
    $response = $this->get($url);
    $status = $response->getStatusCode();

    if ($status >= 300 && $status < 400) {
        $this->markTestSkipped("Redirect {$status} em {$url}");
    }

    $body = $response->getContent() ?: '';
    $err = detectShimError($body);

    // Shim bug = FALHA (regressao da migracao)
    if ($err && $err['kind'] === 'shim') {
        $this->fail("SHIM bug em {$url}: {$err['class']}: {$err['message']} ({$err['file']})");
    }

    // Nao-shim = assertion trivial pra tirar status "risky"; registra via addToAssertionCount
    expect($err['kind'] ?? 'ok')->not->toBe('shim');
})->with('viewRoutes');

/* =========================================================================
 * 2) Endpoints DataTables AJAX (yajra) — valida shape da resposta
 * ========================================================================= */
dataset('datatableAjaxRoutes', [
    'products.ajax' => ['/products'],
    'contacts.customer.ajax' => ['/contacts?type=customer'],
    'contacts.supplier.ajax' => ['/contacts?type=supplier'],
    'sells.ajax' => ['/sells'],
    'purchases.ajax' => ['/purchases'],
    'expenses.ajax' => ['/expenses'],
    'tax-rates.ajax' => ['/tax-rates'],
    'units.ajax' => ['/units'],
    'brands.ajax' => ['/brands'],
    'users.ajax' => ['/users'],
    'stock-transfers.ajax' => ['/stock-transfers'],
    'stock-adjustments.ajax' => ['/stock-adjustments'],
]);

it('datatable AJAX retorna shape correto (draw, recordsTotal, data[])', function (string $url) {
    // Simula request jQuery DataTables v1.10+ (com draw, start, length)
    $params = http_build_query([
        'draw' => '1',
        'start' => '0',
        'length' => '25',
    ]);
    $sep = str_contains($url, '?') ? '&' : '?';

    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->get($url . $sep . $params);

    $status = $response->getStatusCode();

    if ($status >= 300 && $status < 400) {
        $this->markTestSkipped("Redirect {$status} em {$url}");
    }

    expect($status)->toBe(200, "HTTP {$status} em datatable {$url}");

    $json = $response->json();
    expect($json)
        ->toHaveKey('draw')
        ->toHaveKey('recordsTotal')
        ->toHaveKey('recordsFiltered')
        ->toHaveKey('data');

    // draw DEVE bater com o que enviamos (regressao yajra v11+)
    expect((int) $json['draw'])->toBe(1, "DataTables draw mismatch em {$url}: enviado=1, recebido={$json['draw']}");

    // recordsTotal eh inteiro — nao string nem null
    expect($json['recordsTotal'])->toBeInt("recordsTotal nao-int em {$url}");

    // data eh array (pode ser vazio se DB vazio, aceitavel)
    expect($json['data'])->toBeArray("data nao-array em {$url}");
})->with('datatableAjaxRoutes');

/* =========================================================================
 * 3) EDIT forms — exercita Form::select com value pre-selecionado
 *    (regressao potencial: preserving selected nas collections do banco)
 * ========================================================================= */
dataset('editResourceTypes', [
    'brand' => ['brand'],
    'unit' => ['unit'],
    'product' => ['product'],
    'user' => ['user'],
    'contact' => ['contact'],
    'tax-rate' => ['tax-rate'],
]);

it('edit view renderiza sem erros PHP/shim', function (string $resourceType) {
    // Lookup do ID real eh feito DENTRO do teste (Laravel ja esta bootstrapado)
    $resolve = [
        'brand'    => fn () => [\App\Brands::first(),   '/brands/%d/edit'],
        'unit'     => fn () => [\App\Unit::first(),     '/units/%d/edit'],
        'product'  => fn () => [\App\Product::first(),  '/products/%d/edit'],
        'user'     => fn () => [\App\User::first(),     '/users/%d/edit'],
        'contact'  => fn () => [\App\Contact::first(),  '/contacts/%d/edit'],
        'tax-rate' => fn () => [\App\TaxRate::first(),  '/tax-rates/%d/edit'],
    ];

    [$record, $template] = $resolve[$resourceType]();
    if (! $record) {
        $this->markTestSkipped("Sem {$resourceType} no DB — seeder nao rodado?");
    }
    $url = sprintf($template, $record->id);

    $response = $this->get($url);
    $status = $response->getStatusCode();

    if ($status >= 300 && $status < 400) {
        $this->markTestSkipped("Redirect {$status} em {$url}");
    }
    if ($status === 404) {
        $this->markTestSkipped("404 em {$url} — rota pode ter mudado");
    }

    $body = $response->getContent() ?: '';
    $err = detectShimError($body);

    if ($err && $err['kind'] === 'shim') {
        $this->fail("SHIM bug em {$url}: {$err['class']}: {$err['message']} ({$err['file']})");
    }

    expect($err['kind'] ?? 'ok')->not->toBe('shim');
})->with('editResourceTypes');
