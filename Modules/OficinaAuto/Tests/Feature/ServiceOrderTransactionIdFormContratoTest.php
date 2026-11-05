<?php
// @covers-us US-OFICINA-001

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OCR-05 (OficinaAuto/ServiceOrders/Create) — o vínculo com a venda não vem do formulário de criação
//   UC-OED-10 (OficinaAuto/ServiceOrders/Edit)   — o vínculo com a venda não muda pela edição

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Contrato: `service_orders.transaction_id` (vínculo da OS com a venda derivada) só é
 * escrito pelo ServiceOrderObserver ao concluir a OS (ADR 0192). O formulário não escreve.
 *
 * Antes deste teste, Store e Update aceitavam `transaction_id` `nullable|integer`:
 *  - o usuário podia apontar a OS pra qualquer venda, inclusive de outra empresa (a coluna
 *    não tem FK nem escopo);
 *  - com o campo preenchido, o Observer pula o faturamento automático (idempotência por
 *    `transaction_id !== null`), então a OS concluída não gerava venda.
 *
 * Nenhum chamador legítimo envia o campo: Edit.tsx só devolve o valor atual, Create.tsx
 * manda ''. A prova é: o pedido é aceito (sem erro de validação) e o transaction_id gravado
 * NÃO é o que veio no formulário.
 *
 * Tenant: 98 (ADR 0358). O id de venda usado não precisa existir: a coluna não tem FK, que é
 * justamente o que tornava o campo perigoso.
 */

const TXF_BIZ = 98;
const TXF_PLACA = 'TXFO';
const TXF_VENDA_FORJADA = 1999999999;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('vehicles') || ! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('tabelas do OficinaAuto ausentes');
    }
    if (! DB::table('business')->where('id', TXF_BIZ)->exists()) {
        $this->markTestSkipped('business 98 ausente — seed pest-mysql-setup não rodou');
    }

    session(['user.business_id' => TXF_BIZ]);
});

function txf_veiculo(): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id'  => TXF_BIZ,
        'plate'        => TXF_PLACA . random_int(100, 999),
        'vehicle_type' => 'caminhao',
    ]);
}

function txf_usuario(): User
{
    // user_type/allow_login explícitos: sem eles o CheckUserLogin devolve 403 antes do controller.
    $user = User::factory()->create([
        'business_id' => TXF_BIZ,
        'user_type'   => 'user',
        'allow_login' => 1,
    ]);
    $user->givePermissionTo('superadmin');

    return $user;
}

function txf_limpar(): void
{
    $ids = Vehicle::withoutGlobalScopes() // SUPERADMIN: limpeza só do tenant do teste
        ->where('business_id', TXF_BIZ)
        ->where('plate', 'like', TXF_PLACA . '%')
        ->pluck('id')
        ->all();
    if ($ids !== []) {
        Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->update(['current_rental_id' => null]);
        ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $ids)->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
    }
}

it('UC-OCR-05 · criar OS com transaction_id no formulário não grava o vínculo com a venda', function () {
    $veiculo = txf_veiculo();

    $this->actingAs(txf_usuario())
        ->post('/oficina-auto/ordens-servico', [
            'vehicle_id'     => $veiculo->id,
            'order_type'     => 'mecanica',
            'status'         => 'aberta',
            'transaction_id' => TXF_VENDA_FORJADA,
        ])
        ->assertSessionHasNoErrors();

    $os = ServiceOrder::withoutGlobalScopes()->where('vehicle_id', $veiculo->id)->first();
    expect($os)->not->toBeNull();
    expect($os->transaction_id)->not->toBe(TXF_VENDA_FORJADA);
})->after(fn () => txf_limpar());

it('UC-OED-10 · editar OS com transaction_id no formulário não muda o vínculo com a venda', function () {
    $veiculo = txf_veiculo();
    $os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id' => TXF_BIZ,
        'vehicle_id'  => $veiculo->id,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'entered_at'  => now(),
        'notes'       => 'antes',
    ]);
    expect($os->transaction_id)->toBeNull();

    $this->actingAs(txf_usuario())
        ->put('/oficina-auto/ordens-servico/' . $os->id, [
            'vehicle_id'     => $veiculo->id,
            'status'         => 'aberta',
            'notes'          => 'depois',
            'transaction_id' => TXF_VENDA_FORJADA,
        ])
        ->assertSessionHasNoErrors();

    $depois = ServiceOrder::withoutGlobalScopes()->find($os->id);
    // controle: o resto do formulário foi gravado, então a requisição chegou ao update
    expect($depois->notes)->toBe('depois');
    expect($depois->transaction_id)->toBeNull();
})->after(fn () => txf_limpar());
