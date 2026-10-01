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
 * O `generateQr` NÃO redireciona (opção (b) da sessão-mãe): o gate daqui aceita a
 * assinatura `officeimpresso_module` e o do ProductCatalogue exige
 * `productcatalogue_module` — redirecionar tiraria acesso de quem só tem a primeira.
 * Os dois últimos casos travam isso.
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

it('catálogo 4 · o QR do Officeimpresso continua abrindo aqui (200), sem desviar pro ProductCatalogue', function () {
    $business = $this->seededTenant();
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

    $user = makeOiCatalogoTestUser($business->id);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);

    $response = $this->get('/officeimpresso/catalogue-qr');

    $response->assertOk();
    expect($response->getContent())->toContain('catalogue/');

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
