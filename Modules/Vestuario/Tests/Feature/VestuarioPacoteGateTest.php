<?php
// @covers-us US-VEST-020

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Vestuario\Http\Middleware\VestuarioLiberado;
use Modules\Vestuario\Services\VestuarioSettingsResolver;
use Tests\Support\WithSeededTenant;

/**
 * Gate do pacote `vestuario_module` nas rotas do módulo (UC-VPAC-01, SDD CU-VEST-08).
 *
 * O pacote é fixado por mock do ModuleUtil: no CI a tabela `system` não tem `superadmin_version`,
 * então o ModuleUtil real libera qualquer pacote e o teste não provaria nada. Empresa 98 tem o
 * pacote, 99 não; os dois usuários têm as mesmas permissões, logo o 403 vem do pacote.
 * Tenant 98 × 99 (ADR 0358). NUNCA biz=4.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('Requer MySQL com o schema do UltimatePOS.');
    }
    try {
        $this->comPacote = $this->seededTenant();
        $this->semPacote = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }

    $idComPacote = $this->comPacote->id;
    $util = Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $permissao = null, ...$resto) => $permissao === VestuarioLiberado::PACOTE ? (int) $biz === $idComPacote : true);
    app()->instance(ModuleUtil::class, $util);

    $permissoes = ['vestuario.etiqueta.view', 'vestuario.etiqueta.create', 'purchase.create', 'vestuario.remarcacao.aplicar', 'product.update'];
    $this->userComPacote = $this->usuarioComPermissoes($permissoes, $this->comPacote);
    $this->userSemPacote = $this->usuarioComPermissoes($permissoes, $this->semPacote);

    $this->como = function (\App\User $u) {
        test()->flushHeaders();

        return test()->actingAs($u)->withSession(['user.business_id' => $u->business_id, 'user' => ['business_id' => $u->business_id, 'id' => $u->id]]);
    };
});

it('UC-VPAC-01 — empresa com o pacote acessa as telas e chega aos controllers', function () {
    ($this->como)($this->userComPacote)->get('/vestuario/etiquetas')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Vestuario/Etiquetas/Index', false));

    // 422 = passou do pacote e parou na validação do controller.
    ($this->como)($this->userComPacote)->postJson('/vestuario/etiquetas/lote/zpl', ['items' => []])->assertStatus(422);
    ($this->como)($this->userComPacote)->getJson('/vestuario/curva-compra')->assertStatus(422);
});

it('UC-VPAC-01 — empresa sem o pacote recebe 403 em etiquetas e curva [T0]', function () {
    ($this->como)($this->userSemPacote)->get('/vestuario/etiquetas')->assertForbidden();
    ($this->como)($this->userSemPacote)->postJson('/vestuario/etiquetas/lote/zpl', ['items' => []])->assertForbidden();
    ($this->como)($this->userSemPacote)->postJson('/vestuario/etiquetas/lote/pdf', ['items' => []])->assertForbidden();
    ($this->como)($this->userSemPacote)->getJson('/vestuario/curva-compra')->assertForbidden();
});

it('UC-VPAC-01 — sem o pacote a remarcação dá 403 mesmo com o setting ligado [T0]', function () {
    app(VestuarioSettingsResolver::class)->forBusiness($this->semPacote->id)->set('remarcacao.enabled', true);

    ($this->como)($this->userSemPacote)->get('/vestuario/remarcacao')->assertForbidden();
    ($this->como)($this->userSemPacote)
        ->postJson('/vestuario/remarcacao/previa', ['modo' => 'percentual', 'valor' => -10, 'filtro' => ['nome' => 'x']])
        ->assertForbidden();
});

it('UC-VPAC-01 — toda rota nomeada vestuario.* carrega o gate de pacote', function () {
    $rotas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'vestuario.'));

    expect($rotas->count())->toBeGreaterThanOrEqual(9);

    foreach ($rotas as $rota) {
        $this->assertContains(VestuarioLiberado::class, $rota->gatherMiddleware(), "rota {$rota->getName()} sem o gate de pacote");
    }
});
