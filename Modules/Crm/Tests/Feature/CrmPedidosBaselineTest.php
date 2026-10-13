<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Baseline da Blade `/crm/order-request` — thread Crm/17, MWART F2 (ADR 0104).
 *
 * Trava o comportamento de HOJE antes da Page (F3): a Blade abre (sem gate próprio), e os dados
 * que ela pede ao `GET /sells?sale_type=sales_order&crm_is_order_request=1` são só os pedidos de
 * cliente do negócio da sessão, e só os meus com `so.view_own`.
 * Plano em memory/requisitos/Crm/RUNBOOK-pedidos.md.
 *
 * Tenant 98 (seededTenant, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/OrderRequestController::listOrderRequests()
 * @see app/Http/Controllers/SellController::index()
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: transactions requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasColumn('transactions', 'crm_is_order_request')) {
        $this->markTestSkipped('Coluna transactions.crm_is_order_request ausente — migrations do Crm não rodaram.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PED17-'.uniqid());
});

function pedbPedido(int $biz, int $local, int $criador, string $marca, int $pedidoDeCliente = 1): string
{
    $numero = 'PEDB-'.$marca.'-'.uniqid();
    $linha = [
        'business_id' => $biz, 'location_id' => $local, 'type' => 'sales_order', 'status' => 'ordered',
        'payment_status' => 'due', 'crm_is_order_request' => $pedidoDeCliente, 'invoice_no' => $numero,
        'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10,
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ];
    // NOT NULL sem default no schema do CI (mesmo cuidado do MesasContratoTest).
    if (Schema::hasColumn('transactions', 'essentials_duration')) {
        $linha['essentials_duration'] = 0;
    }
    DB::table('transactions')->insert($linha);

    return $numero;
}

/** O que a Blade pede ao DataTables do core, serializado para casar o número do pedido. */
function pedbAjax($teste, $user): string
{
    $teste->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
    $r = $teste->get('/sells?sale_type=sales_order&crm_is_order_request=1', ['X-Requested-With' => 'XMLHttpRequest']);
    $r->assertOk();

    return (string) json_encode($r->json('data'));
}

it('a Blade da equipe abre, e não tem gate próprio de permissão', function () {
    $semNada = $this->usuarioComPermissoes([], $this->business);
    $this->actingAs($semNada)->get('/crm/order-request')->assertOk()->assertViewIs('crm::order_request.all_list');
});

it('o DataTables lista só pedidos de cliente do meu negócio [T0]', function () {
    $user = $this->usuarioComPermissoes(['so.view_all', 'access_all_locations'], $this->business);
    $outro = $this->seededSupportClientTenant();
    $localOutro = EstoqueFixture::locationId($outro->id, '-PED17-'.uniqid());

    $meu = pedbPedido($this->business->id, $this->local, $user->id, 'meu');
    $interno = pedbPedido($this->business->id, $this->local, $user->id, 'interno', 0);
    $vizinho = pedbPedido($outro->id, $localOutro, $user->id, 'vizinho');

    $json = pedbAjax($this, $user);

    expect($json)->toContain($meu);
    $this->assertStringNotContainsString($interno, $json, 'pedido de venda da equipe não é pedido de cliente');
    $this->assertStringNotContainsString($vizinho, $json, 'vazou pedido de outro negócio');
});

it('com so.view_own vê só os pedidos que criou', function () {
    $proprio = $this->usuarioComPermissoes(['so.view_own', 'access_all_locations'], $this->business);
    $colega = $this->usuarioComPermissoes(['so.view_all', 'access_all_locations'], $this->business);

    $meu = pedbPedido($this->business->id, $this->local, $proprio->id, 'proprio');
    $dele = pedbPedido($this->business->id, $this->local, $colega->id, 'colega');

    $json = pedbAjax($this, $proprio);

    expect($json)->toContain($meu);
    $this->assertStringNotContainsString($dele, $json, 'quem só vê os próprios viu o do colega');
});
