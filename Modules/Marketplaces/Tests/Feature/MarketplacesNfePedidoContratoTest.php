<?php

declare(strict_types=1);
// @covers-us US-MKT-009

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Jobs\AvaliarNfeDoPedidoJob;
use Modules\Marketplaces\Services\Fiscal\CfopDoPedido;
use Modules\Marketplaces\SideEffects\AvaliarNfeAoEnviar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da NF-e do pedido de marketplace — parte que não depende do contador (US-MKT-009).
 *
 * Com a flag desligada (padrão até [W] conferir o CFOP), marcar como enviado não põe nada na
 * fila. Ligada, a nota só "pode sair" com destinatário completo e fora do Full; o CFOP sai do
 * mapeamento provisório do SPEC §5. Nenhum caminho daqui chama a SEFAZ.
 * Tenant 98 contra 99 (ADR 0358). SKIP em SQLite.
 */
it('US-MKT-009 · CFOP: mesma UF 5102, outra UF 6102, Full e UF ausente sem CFOP', function () {
    config(['marketplaces.nfe.cfop_mesma_uf' => '5102', 'marketplaces.nfe.cfop_outra_uf' => '6102']);

    expect(CfopDoPedido::resolver('SC', 'sc', 'me2'))->toBe('5102')
        ->and(CfopDoPedido::resolver('SC', 'SP', 'me2'))->toBe('6102')
        ->and(CfopDoPedido::resolver('SC', 'SP', 'fulfillment'))->toBeNull()
        ->and(CfopDoPedido::resolver('SC', null, 'me2'))->toBeNull()
        ->and(CfopDoPedido::resolver('', 'SP', 'me2'))->toBeNull();
});

it('US-MKT-009 · sem CFOP configurado pelo contador, ou CFOP fora de 1xxx-7xxx, não há CFOP', function () {
    config(['marketplaces.nfe.cfop_mesma_uf' => null, 'marketplaces.nfe.cfop_outra_uf' => '']);
    expect(CfopDoPedido::resolver('SC', 'SC', 'me2'))->toBeNull()
        ->and(CfopDoPedido::resolver('SC', 'SP', 'me2'))->toBeNull();

    config(['marketplaces.nfe.cfop_mesma_uf' => '9102', 'marketplaces.nfe.cfop_outra_uf' => '61']);
    expect(CfopDoPedido::resolver('SC', 'SC', 'me2'))->toBeNull()
        ->and(CfopDoPedido::resolver('SC', 'SP', 'me2'))->toBeNull();
});

it('US-MKT-009 · flag desligada: marcar como enviado não põe nada na fila', function () {
    Queue::fake();
    config(['marketplaces.nfe.automatica' => false]);

    (new AvaliarNfeAoEnviar())->execute((new Transaction())->forceFill(['id' => 1, 'business_id' => 98]));

    Queue::assertNothingPushed();
});

it('US-MKT-009 · flag ligada: a nota só pode sair com destinatário completo e fora do Full', function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mkt_orders')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas mkt_* (ADR 0358).');
    }
    config(['marketplaces.nfe.automatica' => true, 'marketplaces.nfe.cfop_mesma_uf' => '5102', 'marketplaces.nfe.cfop_outra_uf' => '6102']);
    $biz = (int) $this->seededTenant()->id;
    $b99 = (int) $this->seededSupportClientTenant()->id;
    $loc = (int) DB::table('business_locations')->where('business_id', $biz)->value('id');
    if ($loc === 0) {
        $this->markTestSkipped('Tenant 98 sem local de venda no seed.');
    }
    DB::table('business_locations')->where('id', $loc)->update(['state' => 'SC']);
    $user = (int) DB::table('users')->where('business_id', $biz)->value('id');
    $tx = (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $loc, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10, 'created_by' => $user,
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ml = Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->firstOrFail();
    // SUPERADMIN: fixture da conta e do pedido da 98.
    $conta = ContaVendedor::withoutGlobalScopes()->create(['business_id' => $biz, 'marketplace_id' => $ml->id, 'seller_id_external' => 'nfe-1']);
    $pedido = PedidoMarketplace::withoutGlobalScopes()->create([
        'business_id' => $biz, 'account_id' => $conta->id, 'marketplace_id' => $ml->id, 'order_id_external' => 'NFE-'.uniqid(),
        'transaction_id' => $tx, 'status_external' => 'paid', 'shipping_type' => 'me2', 'buyer_external' => ['id' => 1],
    ]);

    expect(AvaliarNfeDoPedidoJob::avaliar($biz, $tx))->toMatchArray(['pode' => false, 'motivo' => 'destinatário incompleto (documento e UF vêm do billing_info do ML)']);

    $pedido->forceFill(['buyer_external' => ['id' => 1, 'documento' => '00000000000', 'uf' => 'SP']])->save();
    expect(AvaliarNfeDoPedidoJob::avaliar($biz, $tx))->toBe(['pode' => true, 'motivo' => null, 'cfop' => '6102']);

    config(['marketplaces.nfe.cfop_outra_uf' => null]);
    expect(AvaliarNfeDoPedidoJob::avaliar($biz, $tx))->toMatchArray(['pode' => false, 'motivo' => 'CFOP do marketplace não configurado (depende do contador)']);
    config(['marketplaces.nfe.cfop_outra_uf' => '6102']);

    $pedido->forceFill(['shipping_type' => 'fulfillment'])->save();
    expect(AvaliarNfeDoPedidoJob::avaliar($biz, $tx)['pode'])->toBeFalse();

    // Outra empresa olhando a venda da 98: nunca "pode".
    expect(AvaliarNfeDoPedidoJob::avaliar($b99, $tx))->toMatchArray(['pode' => false, 'motivo' => 'venda sem pedido de marketplace']);
});
