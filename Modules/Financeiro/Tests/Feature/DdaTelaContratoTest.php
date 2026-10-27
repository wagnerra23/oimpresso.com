<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Dto\BoletoDdaRecebido;
use Modules\Financeiro\Http\Controllers\DdaController;
use Modules\Financeiro\Services\DdaService;

uses(Tests\TestCase::class);

/**
 * US-FIN-078 — boletos DDA na Visão Unificada: rota e prop. Contrato:
 * resources/js/Pages/Financeiro/Unificado/Index.casos.md (UC-DDA-08..10).
 * Tenant 98 (ADR 0358), adversário 99; usuário NOVO com só as permissões da rota.
 *
 * @covers-us US-FIN-078
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('fin_dda_boletos')) {
        $this->markTestSkipped('DDA usa schema MySQL do Financeiro (rode migrate).');
    }

    DB::beginTransaction();
    config(['financeiro.dda.enabled' => true]);
    $this->bizA = $this->seededTenant();
    $this->bizB = $this->seededSupportClientTenant();
    $this->sugA = app(DdaService::class)->registrar((int) $this->bizA->id, 'teste',
        new BoletoDdaRecebido('0339'.str_pad((string) random_int(1, PHP_INT_MAX), 40, '0', STR_PAD_LEFT), '11222333000181', 'Papelaria', '250.00', Carbon::parse('2031-05-02')));
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function ddaTelaSessao($biz): array
{
    return ['user.business_id' => $biz->id, 'business.id' => $biz->id,
        'business' => ['id' => $biz->id, 'name' => $biz->name, 'currency_symbol' => 'R$']];
}

it('UC-DDA-08 · aceitar pela tela exige financeiro.contas_pagar.create e cria a conta a pagar', function () {
    $sem = $this->usuarioComPermissoes(['financeiro.dashboard.view'], $this->bizA);
    $this->actingAs($sem)->withSession(ddaTelaSessao($this->bizA))
        ->post("/financeiro/dda/{$this->sugA->id}/aceitar")->assertForbidden();
    expect(DB::table('fin_dda_boletos')->where('id', $this->sugA->id)->value('status'))->toBe('sugerido');

    $com = $this->usuarioComPermissoes(['financeiro.dashboard.view', 'financeiro.contas_pagar.create'], $this->bizA);
    $this->actingAs($com)->withSession(ddaTelaSessao($this->bizA))
        ->post("/financeiro/dda/{$this->sugA->id}/aceitar")->assertRedirect()->assertSessionHas('success');

    $tituloId = (int) DB::table('fin_dda_boletos')->where('id', $this->sugA->id)->value('titulo_id');
    $t = DB::table('fin_titulos')->where('id', $tituloId)->first();
    expect($t->tipo)->toBe('pagar');
    expect((float) $t->valor_aberto)->toBe(250.0);
    expect((int) $t->business_id)->toBe((int) $this->bizA->id);
});

it('UC-DDA-09 · sessão de outra empresa não aceita a sugestão; desligado é 404', function () {
    $userB = $this->usuarioComPermissoes(['financeiro.dashboard.view', 'financeiro.contas_pagar.create'], $this->bizB);
    $this->actingAs($userB)->withSession(ddaTelaSessao($this->bizB))
        ->post("/financeiro/dda/{$this->sugA->id}/aceitar")->assertNotFound();
    expect(DB::table('fin_dda_boletos')->where('id', $this->sugA->id)->value('status'))->toBe('sugerido');

    config(['financeiro.dda.enabled' => false]);
    $userA = $this->usuarioComPermissoes(['financeiro.dashboard.view', 'financeiro.contas_pagar.create'], $this->bizA);
    $this->actingAs($userA)->withSession(ddaTelaSessao($this->bizA))
        ->post("/financeiro/dda/{$this->sugA->id}/descartar")->assertNotFound();
});

it('UC-DDA-10 · a lista da tela traz só as sugestões em aberto da própria empresa, e some com a flag desligada', function () {
    $lista = DdaController::sugestoes((int) $this->bizA->id);
    expect(collect($lista)->pluck('id')->all())->toContain($this->sugA->id);
    expect(collect(DdaController::sugestoes((int) $this->bizB->id))->pluck('id')->all())->not->toContain($this->sugA->id);

    app(DdaService::class)->descartar((int) $this->bizA->id, $this->sugA->id, 1);
    expect(collect(DdaController::sugestoes((int) $this->bizA->id))->pluck('id')->all())->not->toContain($this->sugA->id);

    config(['financeiro.dda.enabled' => false]);
    expect(DdaController::sugestoes((int) $this->bizA->id))->toBeNull();
});
