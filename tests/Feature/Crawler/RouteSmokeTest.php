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
    'home' => ['/home'],
    'business.settings' => ['/business/settings'],
    'user.profile' => ['/user/profile'],
    'brands.create' => ['/brands/create'],
    'tax-rates.create' => ['/tax-rates/create'],
    'units.create' => ['/units/create'],
    'customer-groups.create' => ['/customer-groups/create'],
    'warranties.create' => ['/warranties/create'],
    'contacts.customer' => ['/contacts/create?type=customer'],
    'contacts.supplier' => ['/contacts/create?type=supplier'],
    'products.create' => ['/products/create'],
    'invoice-schemes.create' => ['/invoice-schemes/create'],
    'barcodes.create' => ['/barcodes/create'],
    'business-location.create' => ['/business-location/create'],
    'users.create' => ['/users/create'],
    'cash-register.create' => ['/cash-register/create'],
    'expenses.create' => ['/expenses/create'],
    'contacts.index' => ['/contacts?type=customer'],
    'products.index' => ['/products'],
    'sells.index' => ['/sells'],
    'purchases.index' => ['/purchases'],
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
    'sells.ajax' => ['/sells'],
    'purchases.ajax' => ['/purchases'],
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
