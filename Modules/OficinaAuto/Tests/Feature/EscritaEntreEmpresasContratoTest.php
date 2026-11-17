<?php
// @covers-us US-OFICINA-001

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Contrato Tier 0 (ADR 0093): a empresa só escreve no que é dela.
 *
 * Usuário do tenant 98 (ADR 0358) tenta alterar registros do 99 pelas rotas de escrita
 * de OS, veículo, item e pelo arraste do kanban (fsm/execute). O lote 0 cobriu os campos
 * que apontam para outra empresa (vehicle_id, contact_id, product_id); aqui é o registro
 * inteiro da URL, mais o business_id forjado no corpo do store.
 *
 * O usuário NÃO é superadmin de propósito: as policies têm `before`, e superadmin
 * esconderia o que a permissão comum faz. O 404 sozinho também não basta (a rota podia
 * nem existir): cada escrita barrada confere o banco, e os casos de controle mostram
 * que a mesma requisição passa quando o registro é da própria empresa.
 */

const ESCR_PLACA = 'ESCR';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('vehicles') || ! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('tabelas do OficinaAuto ausentes — rode o migrate do módulo');
    }

    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    $this->biz = (int) $this->b98->id;
    $this->outra = (int) $this->b99->id;

    // O gate de pacote é outra regra (OficinaAutoPacoteGateTest): aqui as duas empresas têm.
    $fake = \Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === 'oficina_auto_module');
    app()->instance(ModuleUtil::class, $fake);

    $this->user = $this->usuarioComPermissoes([
        'oficinaauto.access',
        'oficinaauto.vehicle.view',
        'oficinaauto.vehicle.create',
        'oficinaauto.vehicle.update',
        'oficinaauto.vehicle.delete',
        'oficinaauto.service_order.view',
        'oficinaauto.service_order.create',
        'oficinaauto.service_order.update',
        'oficinaauto.service_order.delete',
    ], $this->b98);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->biz]);

    $this->fixturas = function (int $biz, string $sufixo): array {
        $veiculo = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture em tenant explícito
            'business_id'  => $biz,
            'plate'        => ESCR_PLACA . $sufixo,
            'vehicle_type' => 'automovel',
            'engine'       => 'motor original',
        ]);
        $os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: fixture em tenant explícito
            'business_id' => $biz,
            'vehicle_id'  => $veiculo->id,
            'order_type'  => 'mecanica',
            'status'      => 'aberta',
            'entered_at'  => now(),
        ]);
        $item = ServiceOrderItem::withoutGlobalScopes()->create([ // SUPERADMIN: fixture em tenant explícito
            'business_id'      => $biz,
            'service_order_id' => $os->id,
            'tipo'             => 'mao_obra',
            'descricao'        => 'descricao original',
            'quantidade'       => 1,
            'valor_unitario'   => 10,
            'valor_total'      => 10,
        ]);

        return [$veiculo, $os, $item];
    };
});

afterEach(function () {
    $ids = Vehicle::withoutGlobalScopes() // SUPERADMIN: limpeza só das placas deste teste
        ->where('plate', 'like', ESCR_PLACA . '%')
        ->pluck('id')
        ->all();
    if ($ids === []) {
        return;
    }
    $osIds = ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $ids)->pluck('id')->all();
    ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
    Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->update(['current_rental_id' => null]);
    ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
});

// ── Ordem de serviço ─────────────────────────────────────────────────────────

it('editar a OS de outra empresa dá 404 e não altera a linha', function () {
    [$veiculoAlheio, $osAlheia] = ($this->fixturas)($this->outra, 'A1');
    [$veiculoProprio] = ($this->fixturas)($this->biz, 'A2');

    $this->put("/oficina-auto/ordens-servico/{$osAlheia->id}", [
        'vehicle_id' => $veiculoProprio->id,
        'status'     => 'alterada_por_outra_empresa',
        'notes'      => 'alterada por outra empresa',
    ])->assertNotFound();

    $linha = ServiceOrder::withoutGlobalScopes()->find($osAlheia->id);
    expect($linha->status)->toBe('aberta');
    expect($linha->notes)->toBeNull();
    expect((int) $linha->vehicle_id)->toBe((int) $veiculoAlheio->id);
    expect((int) $linha->business_id)->toBe($this->outra);
});

it('controle: editar a OS da própria empresa grava', function () {
    [$veiculo, $os] = ($this->fixturas)($this->biz, 'A3');

    $this->put("/oficina-auto/ordens-servico/{$os->id}", [
        'vehicle_id' => $veiculo->id,
        'status'     => 'em_execucao',
        'notes'      => 'nota da própria empresa',
    ])->assertSessionHasNoErrors();

    $linha = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($linha->status)->toBe('em_execucao');
    expect($linha->notes)->toBe('nota da própria empresa');
});

it('apagar a OS de outra empresa dá 404 e a linha continua lá', function () {
    [, $osAlheia] = ($this->fixturas)($this->outra, 'A4');

    $this->delete("/oficina-auto/ordens-servico/{$osAlheia->id}")->assertNotFound();

    $linha = ServiceOrder::withoutGlobalScopes()->find($osAlheia->id);
    expect($linha)->not->toBeNull();
    expect($linha->deleted_at)->toBeNull();
});

it('controle: apagar a OS da própria empresa remove', function () {
    [, $os] = ($this->fixturas)($this->biz, 'A5');

    $this->delete("/oficina-auto/ordens-servico/{$os->id}")->assertRedirect();

    expect(ServiceOrder::query()->find($os->id))->toBeNull();
});

it('criar OS com business_id forjado grava na empresa de quem está logado', function () {
    [$veiculo] = ($this->fixturas)($this->biz, 'A6');
    $antesNaOutra = ServiceOrder::withoutGlobalScopes()->where('business_id', $this->outra)->count();

    $this->post('/oficina-auto/ordens-servico', [
        'vehicle_id'  => $veiculo->id,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'business_id' => $this->outra,
    ])->assertSessionHasNoErrors();

    $nova = ServiceOrder::withoutGlobalScopes()
        ->where('vehicle_id', $veiculo->id)
        ->orderByDesc('id')
        ->first();
    expect((int) $nova->business_id)->toBe($this->biz);
    expect(ServiceOrder::withoutGlobalScopes()->where('business_id', $this->outra)->count())->toBe($antesNaOutra);
});

// ── Veículo ──────────────────────────────────────────────────────────────────

it('editar o veículo de outra empresa dá 404 e não altera a linha', function () {
    [$veiculoAlheio] = ($this->fixturas)($this->outra, 'B1');

    $this->put("/oficina-auto/veiculos/{$veiculoAlheio->id}", [
        'plate'        => ESCR_PLACA . 'B1X',
        'vehicle_type' => 'caminhao',
        'engine'       => 'motor trocado',
    ])->assertNotFound();

    $linha = Vehicle::withoutGlobalScopes()->find($veiculoAlheio->id);
    expect($linha->plate)->toBe(ESCR_PLACA . 'B1');
    expect($linha->engine)->toBe('motor original');
    expect((int) $linha->business_id)->toBe($this->outra);
});

it('controle: editar o veículo da própria empresa grava', function () {
    [$veiculo] = ($this->fixturas)($this->biz, 'B2');

    $this->put("/oficina-auto/veiculos/{$veiculo->id}", [
        'plate'        => ESCR_PLACA . 'B2',
        'vehicle_type' => 'automovel',
        'engine'       => 'motor trocado',
    ])->assertSessionHasNoErrors();

    expect(Vehicle::withoutGlobalScopes()->find($veiculo->id)->engine)->toBe('motor trocado');
});

it('apagar o veículo de outra empresa dá 404 e a linha continua lá', function () {
    [$veiculoAlheio] = ($this->fixturas)($this->outra, 'B3');

    $this->delete("/oficina-auto/veiculos/{$veiculoAlheio->id}")->assertNotFound();

    $linha = Vehicle::withoutGlobalScopes()->find($veiculoAlheio->id);
    expect($linha)->not->toBeNull();
    expect($linha->deleted_at)->toBeNull();
});

it('criar veículo com business_id forjado grava na empresa de quem está logado', function () {
    $this->post('/oficina-auto/veiculos', [
        'plate'        => ESCR_PLACA . 'B4',
        'vehicle_type' => 'automovel',
        'business_id'  => $this->outra,
    ])->assertSessionHasNoErrors();

    $novo = Vehicle::withoutGlobalScopes()->where('plate', ESCR_PLACA . 'B4')->first();
    expect($novo)->not->toBeNull();
    expect((int) $novo->business_id)->toBe($this->biz);
});

// ── Itens da OS ──────────────────────────────────────────────────────────────

it('incluir item na OS de outra empresa dá 404 e não cria linha', function () {
    [, $osAlheia] = ($this->fixturas)($this->outra, 'C1');
    $antes = ServiceOrderItem::withoutGlobalScopes()->where('service_order_id', $osAlheia->id)->count();

    $this->postJson("/oficina-auto/ordens-servico/{$osAlheia->id}/items", [
        'tipo'      => 'mao_obra',
        'descricao' => 'item de outra empresa',
    ])->assertNotFound();

    expect(ServiceOrderItem::withoutGlobalScopes()->where('service_order_id', $osAlheia->id)->count())->toBe($antes);
});

it('editar item de outra empresa dá 404, pela OS alheia e pela própria na URL', function () {
    [, $osAlheia, $itemAlheio] = ($this->fixturas)($this->outra, 'C2');
    [, $osPropria] = ($this->fixturas)($this->biz, 'C3');

    $this->putJson("/oficina-auto/ordens-servico/{$osAlheia->id}/items/{$itemAlheio->id}", [
        'descricao' => 'alterado pela outra empresa',
    ])->assertNotFound();

    $this->putJson("/oficina-auto/ordens-servico/{$osPropria->id}/items/{$itemAlheio->id}", [
        'descricao' => 'alterado pela outra empresa',
    ])->assertNotFound();

    $linha = ServiceOrderItem::withoutGlobalScopes()->find($itemAlheio->id);
    expect($linha->descricao)->toBe('descricao original');
    expect((int) $linha->service_order_id)->toBe((int) $osAlheia->id);
});

it('controle: editar item da própria OS grava', function () {
    [, $os, $item] = ($this->fixturas)($this->biz, 'C4');

    $this->putJson("/oficina-auto/ordens-servico/{$os->id}/items/{$item->id}", [
        'descricao' => 'descricao nova',
    ])->assertOk();

    expect(ServiceOrderItem::withoutGlobalScopes()->find($item->id)->descricao)->toBe('descricao nova');
});

it('apagar item de outra empresa dá 404 e a linha continua lá', function () {
    [, $osAlheia, $itemAlheio] = ($this->fixturas)($this->outra, 'C5');

    $this->deleteJson("/oficina-auto/ordens-servico/{$osAlheia->id}/items/{$itemAlheio->id}")
        ->assertNotFound();

    expect(ServiceOrderItem::withoutGlobalScopes()->find($itemAlheio->id))->not->toBeNull();
});

// ── Arraste do kanban (fsm/execute) ──────────────────────────────────────────

it('mover no kanban a OS de outra empresa dá 404 e não muda etapa nem histórico', function () {
    [, $osAlheia] = ($this->fixturas)($this->outra, 'D1');
    $etapaAntes = $osAlheia->current_stage_id;

    $this->postJson("/oficina-auto/service-orders/{$osAlheia->id}/fsm/execute", [
        'action_key' => 'enviar_orcamento',
    ])->assertNotFound();

    expect(ServiceOrder::withoutGlobalScopes()->find($osAlheia->id)->current_stage_id)->toBe($etapaAntes);
    expect(DB::table('sale_stage_history')
        ->where('business_id', $this->outra)
        ->where('transaction_id', $osAlheia->id) // a OS reusa sale_stage_history: transaction_id guarda o id dela
        ->count())->toBe(0);
});

it('controle: a mesma rota alcança a OS da própria empresa (o gate responde 422, não 404)', function () {
    [, $os] = ($this->fixturas)($this->biz, 'D2');

    $this->postJson("/oficina-auto/service-orders/{$os->id}/fsm/execute", [
        'action_key' => 'enviar_orcamento',
    ])->assertStatus(422);
});
