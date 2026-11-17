<?php

declare(strict_types=1);
// @covers-us US-OFICINA-001

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Http\Controllers\DataController;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Pacote do superadmin manda no módulo (Camada 1 — feedback-habilitar-modulo-por-business).
 *
 * Antes: só o menu conferia `oficina_auto_module`; as rotas não. Como `Admin#{biz}` passa
 * em toda permissão, o admin de empresa SEM o pacote abria /oficina-auto/* pela URL.
 * Agora o middleware OficinaAutoLiberado barra a rota com a mesma regra do menu.
 *
 * Tenant 98 (com pacote) contra o 99 (sem) — ADR 0358. Usuário novo com só as permissões
 * de leitura/escrita do caso, para que o 403 venha do PACOTE e não da permissão: o
 * controle positivo (98 → 200) prova isso. ⚠️ Skip em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema OficinaAuto requer MySQL UltimatePOS (ADR 0358)');
    }
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('vehicles ausente — migrations do OficinaAuto não rodaram');
    }

    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    $perms = ['oficinaauto.access', 'oficinaauto.vehicle.view', 'oficinaauto.vehicle.create', 'oficinaauto.service_order.view'];
    $this->u98 = $this->usuarioComPermissoes($perms, $this->b98);
    $this->u99 = $this->usuarioComPermissoes($perms, $this->b99);

    // Pacote só para o 98. ModuleUtil resolvido pelo container (OficinaAutoAcesso usa app()).
    $GLOBALS['oficina_pacote'] = [(int) $this->b98->id];
    $fake = \Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === 'oficina_auto_module'
            && in_array((int) $biz, $GLOBALS['oficina_pacote'], true));
    app()->instance(ModuleUtil::class, $fake);
});

function oficinaComo($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

function oficinaMenuLabels(): array
{
    \Menu::create('admin-sidebar-menu', fn ($menu) => $menu);
    (new DataController())->modifyAdminMenu();

    return array_column((new \App\Services\LegacyMenuAdapter())->build(), 'label');
}

it('pacote · rotas do Oficina Auto dão 200 com o pacote e 403 sem', function () {
    oficinaComo($this, $this->u98);
    $this->get('/oficina-auto/veiculos')->assertOk();

    oficinaComo($this, $this->u99);
    foreach (['/oficina-auto/veiculos', '/oficina-auto/veiculos/create', '/oficina-auto/ordens-servico', '/oficina-auto/ordens-servico/board'] as $rota) {
        $this->get($rota)->assertForbidden();
    }

    // Escrita também barrada: nada gravado no business sem o pacote.
    $this->post('/oficina-auto/veiculos', [
        'plate' => 'PKG9A99',
        'vehicle_type' => 'automovel',
    ])->assertForbidden();
    expect(Vehicle::withoutGlobalScopes()->where('business_id', $this->b99->id)->where('plate', 'PKG9A99')->exists()) // SUPERADMIN: controle negativo cross-tenant
        ->toBeFalse();
});

it('pacote · desligar o pacote corta a rota no request seguinte', function () {
    oficinaComo($this, $this->u98);
    $this->get('/oficina-auto/veiculos')->assertOk();

    $GLOBALS['oficina_pacote'] = [];
    $this->get('/oficina-auto/veiculos')->assertForbidden();
});

it('pacote · menu Oficina Auto aparece com o pacote e some sem', function () {
    oficinaComo($this, $this->u98);
    expect(oficinaMenuLabels())->toContain('Oficina Auto');

    oficinaComo($this, $this->u99);
    expect(oficinaMenuLabels())->not->toContain('Oficina Auto');
});
