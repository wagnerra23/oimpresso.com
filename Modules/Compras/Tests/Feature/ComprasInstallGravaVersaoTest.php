<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\WithSeededTenant;

/**
 * Instalar o Compras grava `system.compras_version`, a marca que o core lê para saber se o
 * módulo está instalado (ModuleUtil::isModuleInstalled). Sem ela, `getModuleData` pula o Compras
 * e os ganchos dele (campo "Método de custo" no cadastro do produto, US-COM-024 · UC-FLX-38, e o
 * menu) nunca rodam. Medido em produção em 2026-10-21: a chave não existia, e o install antigo
 * não a gravava.
 *
 * `Artisan::call` é dublado: o teste não roda `migrate` na base (no CT 100 ela persiste entre runs).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }

    DB::table('system')->where('key', 'compras_version')->delete();

    $this->user = User::factory()->create([
        'business_id' => $this->biz->id, 'username' => 'compras_install_'.uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->givePermissionTo(Permission::findOrCreate('superadmin', 'web'));
    $this->actingAs($this->user);
    session(['user.business_id' => $this->biz->id, 'user.id' => $this->user->id]);

    Artisan::shouldReceive('call')->andReturn(0);
});

it('instalar o Compras grava compras_version e o core passa a vê-lo como instalado', function () {
    expect((new ModuleUtil)->isModuleInstalled('Compras'))->toBeFalse();

    $this->post('/compras/install')->assertRedirect();

    expect(DB::table('system')->where('key', 'compras_version')->value('value'))->toBe('1.0')
        ->and((new ModuleUtil)->isModuleInstalled('Compras'))->toBeTrue();
});

it('atualizar mantém uma chave só, e desinstalar a remove', function () {
    $this->post('/compras/install')->assertRedirect();
    $this->post('/compras/install/update')->assertRedirect();

    expect(DB::table('system')->where('key', 'compras_version')->count())->toBe(1);

    $this->post('/compras/install/uninstall')->assertRedirect();

    expect(DB::table('system')->where('key', 'compras_version')->exists())->toBeFalse()
        ->and((new ModuleUtil)->isModuleInstalled('Compras'))->toBeFalse();
});

it('o GET só mostra a confirmação e não grava nada', function () {
    $this->get('/compras/install')->assertOk();

    expect(DB::table('system')->where('key', 'compras_version')->exists())->toBeFalse();
});
