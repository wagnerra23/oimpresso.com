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
 * Contrato: o formulário da OS não escreve `service_orders.transaction_id` (vínculo com a venda).
 * Quem escreve é o sistema: ServiceOrderObserver ao concluir a OS (ADR 0192) e
 * CriarOsPorVendaService ao abrir a OS a partir de uma venda.
 *
 * Antes deste teste, Store e Update aceitavam o campo como `nullable|integer`:
 *  - a OS podia apontar pra venda de outra empresa (a coluna não tem FK nem escopo);
 *  - com o campo preenchido, o Observer não fatura a conclusão (`transaction_id !== null`);
 *  - com o campo vazio, um formulário aberto antes da conclusão apagava o vínculo.
 *
 * Nenhuma tela edita o campo: Edit.tsx só devolve o valor que recebeu e Create.tsx manda ''.
 *
 * O 302 não prova nada (sucesso e recusa são ambos redirect). A prova é a linha no banco,
 * com um controle (`notes`) de que a requisição chegou ao update.
 *
 * Tenants: 98 é a empresa do usuário e 99 a empresa alheia (ADR 0358; os dois vêm do seed
 * pest-mysql-setup).
 */

const TXF_BIZ = 98;
const TXF_BIZ_ALHEIA = 99;
const TXF_PLACA = 'TXFO';
const TXF_NOTA = 'TXFV-';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('vehicles') || ! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('tabelas do OficinaAuto ausentes');
    }
    foreach ([TXF_BIZ, TXF_BIZ_ALHEIA] as $biz) {
        if (! DB::table('business')->where('id', $biz)->exists()) {
            $this->markTestSkipped("business {$biz} ausente — seed pest-mysql-setup não rodou");
        }
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

/** Venda real, gravada direto na tabela para não acionar observers de venda. */
function txf_venda(int $biz): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id'      => $biz,
        'type'             => 'sell',
        'status'           => 'final',
        'payment_status'   => 'due',
        'invoice_no'       => TXF_NOTA . uniqid(),
        'final_total'      => 0,
        'transaction_date' => now(),
        'created_by'       => (int) DB::table('business')->where('id', $biz)->value('owner_id'),
    ]);
}

function txf_os(Vehicle $veiculo, ?int $vendaId): ServiceOrder
{
    return ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id'    => TXF_BIZ,
        'vehicle_id'     => $veiculo->id,
        'transaction_id' => $vendaId,
        'order_type'     => 'mecanica',
        'status'         => 'aberta',
        'entered_at'     => now(),
        'notes'          => 'antes',
    ]);
}

function txf_limpar(): void
{
    // ->after() roda também quando o beforeEach pulou o teste.
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('vehicles')) {
        return;
    }

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

    DB::table('transactions')
        ->whereIn('business_id', [TXF_BIZ, TXF_BIZ_ALHEIA])
        ->where('invoice_no', 'like', TXF_NOTA . '%')
        ->delete();
}

it('UC-OCR-05 · criar OS com venda de OUTRA empresa no formulário não grava o vínculo', function () {
    $veiculo = txf_veiculo();
    $vendaAlheia = txf_venda(TXF_BIZ_ALHEIA);

    $this->actingAs(txf_usuario())
        ->post('/oficina-auto/ordens-servico', [
            'vehicle_id'     => $veiculo->id,
            'order_type'     => 'mecanica',
            'status'         => 'aberta',
            'notes'          => 'criada',
            'transaction_id' => $vendaAlheia,
        ])
        ->assertSessionHasNoErrors();

    $os = ServiceOrder::withoutGlobalScopes()->where('vehicle_id', $veiculo->id)->first();
    expect($os)->not->toBeNull();
    expect($os->notes)->toBe('criada');
    expect($os->transaction_id)->toBeNull();
})->after(fn () => txf_limpar());

it('UC-OED-10 · editar OS sem venda enviando venda de OUTRA empresa mantém o vínculo vazio', function () {
    $veiculo = txf_veiculo();
    $os = txf_os($veiculo, null);
    $vendaAlheia = txf_venda(TXF_BIZ_ALHEIA);

    $this->actingAs(txf_usuario())
        ->put('/oficina-auto/ordens-servico/' . $os->id, [
            'vehicle_id'     => $veiculo->id,
            'status'         => 'aberta',
            'notes'          => 'depois',
            'transaction_id' => $vendaAlheia,
        ])
        ->assertSessionHasNoErrors();

    $depois = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($depois->notes)->toBe('depois');
    expect($depois->transaction_id)->toBeNull();
})->after(fn () => txf_limpar());

it('UC-OED-10 · editar OS já faturada enviando venda de OUTRA empresa não troca a venda', function () {
    $veiculo = txf_veiculo();
    $vendaPropria = txf_venda(TXF_BIZ);
    $os = txf_os($veiculo, $vendaPropria);
    $vendaAlheia = txf_venda(TXF_BIZ_ALHEIA);

    $this->actingAs(txf_usuario())
        ->put('/oficina-auto/ordens-servico/' . $os->id, [
            'vehicle_id'     => $veiculo->id,
            'status'         => 'aberta',
            'notes'          => 'depois',
            'transaction_id' => $vendaAlheia,
        ])
        ->assertSessionHasNoErrors();

    $depois = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($depois->notes)->toBe('depois');
    expect($depois->transaction_id)->toBe($vendaPropria);
})->after(fn () => txf_limpar());

it('UC-OED-10 · formulário aberto antes do faturamento (campo vazio) não apaga o vínculo', function () {
    $veiculo = txf_veiculo();
    $vendaPropria = txf_venda(TXF_BIZ);
    $os = txf_os($veiculo, $vendaPropria);

    $this->actingAs(txf_usuario())
        ->put('/oficina-auto/ordens-servico/' . $os->id, [
            'vehicle_id'     => $veiculo->id,
            'status'         => 'aberta',
            'notes'          => 'depois',
            'transaction_id' => '',
        ])
        ->assertSessionHasNoErrors();

    $depois = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($depois->notes)->toBe('depois');
    expect($depois->transaction_id)->toBe($vendaPropria);
})->after(fn () => txf_limpar());
