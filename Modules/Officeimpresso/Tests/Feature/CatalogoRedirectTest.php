<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Officeimpresso\Http\Controllers\OfficeimpressoController;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Thread 08 do playbook Officeimpresso — catálogo duplicado.
 *
 * Decisão [W] 2026-10-01 (D3): o `Modules/ProductCatalogue` é o dono do catálogo.
 * As telas `index` e `show` do Officeimpresso eram cópia exata (mesma query, mesmas
 * views) e passam a REDIRECIONAR pra lá, levando parâmetros e query string.
 *
 * O `generateQr` também redireciona desde a 2ª rodada (D3, "pode ajustar primeiro"):
 * o gate daqui aceitava `officeimpresso_module` e o do ProductCatalogue exige
 * `productcatalogue_module`. Os pacotes foram alinhados ANTES pelo comando
 * `officeimpresso:alinhar-pacotes-catalogo` (AlinharPacotesCatalogoCommandTest prova
 * que, depois dele, o gate de lá libera quem tinha só o Officeimpresso). Os dois
 * últimos casos travam o redirect.
 *
 * Não usa RefreshDatabase (UltimatePOS legado). Tenant vem do seed da action
 * pest-mysql-setup — nunca biz=4.
 */

beforeEach(function () {
    // layouts/app.blade.php lê REMOTE_ADDR e HTTP_USER_AGENT do superglobal
    // (mesma razão documentada no LicencasAcessoPermissionTest).
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0101).');
    }
});

it('catálogo 1 · index do catálogo redireciona pro ProductCatalogue com params e query string', function () {
    $business = $this->seededTenant();
    $user = makeOiCatalogoTestUser($business->id);
    $this->actingAs($user);

    $this->get("/officeimpresso/catalogue/{$business->id}/7?origem=qr")
        ->assertRedirect(url("/catalogue/{$business->id}/7").'?origem=qr');

    $user->forceDelete();
});

it('catálogo 2 · show do catálogo redireciona pro ProductCatalogue preservando ?location_id', function () {
    $business = $this->seededTenant();
    $user = makeOiCatalogoTestUser($business->id);
    $this->actingAs($user);

    $this->get("/officeimpresso/show-catalogue/{$business->id}/123?location_id=7")
        ->assertRedirect(url("/show-catalogue/{$business->id}/123").'?location_id=7');

    $user->forceDelete();
});

it('catálogo 3 · a rota do QR continua apontando pro OfficeimpressoController@generateQr', function () {
    $acao = Route::getRoutes()->getByName('officeimpresso.catalogue-qr')?->getActionName();

    expect($acao)->toBe(OfficeimpressoController::class.'@generateQr');
});

it('catálogo 4 · o QR do Officeimpresso redireciona pro gerador do ProductCatalogue, com query string', function () {
    $business = $this->seededTenant();
    $user = makeOiCatalogoTestUser($business->id);
    $this->actingAs($user);

    // Sem superadmin e sem pacote nenhum: o redirect acontece ANTES de qualquer gate
    // aqui. Quem decide o acesso é o destino — não há segundo dono da regra.
    $this->get('/officeimpresso/catalogue-qr?origem=menu')
        ->assertRedirect(url('/product-catalogue/catalogue-qr').'?origem=menu');

    $user->forceDelete();
});

it('catálogo 5 · o destino do redirect abre (200) com o dropdown de locais', function () {
    // O redirect sozinho não prova nada se o destino quebra. Em 2026-10-01, logo após o
    // deploy do #8403, /product-catalogue/catalogue-qr dava 500 em prod:
    // locationsDropdown() declarava `array` e devolvia Collection. Superadmin aqui só
    // pra passar o gate de pacote — o que se mede é a tela montar.
    $business = $this->seededTenant();
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

    $user = makeOiCatalogoTestUser($business->id);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);

    $response = $this->followingRedirects()
        ->get('/officeimpresso/catalogue-qr');

    $response->assertOk();
    expect($response->getContent())->toContain('name="location_id"');

    $user->revokePermissionTo('superadmin');
    $user->forceDelete();
});

function makeOiCatalogoTestUser(int $businessId): User
{
    return User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Catalogo',
        'username'    => 'oi_catalogo_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_catalogo_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
}
