<?php

declare(strict_types=1);
// Cobre UC-CRMPED-01, UC-CRMPED-02, UC-CRMPED-03, UC-CRMPED-04, UC-CRMPED-05 (Crm/Pedidos/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato de `/crm/order-request` em Inertia — thread Crm/17, MWART F3, atrás da flag
 * useV2CrmPedidos (ligada aqui pelo override `feature-flags.forced_on`, inerte em produção).
 * A Blade de hoje está travada em CrmPedidosBaselineTest.
 *
 * Valor (regra mestre): a tela só MOSTRA `final_total` e a soma; UC-CRMPED-04 prova a soma por
 * dois caminhos — os totais semeados (conhecidos) e a soma das linhas devolvidas.
 *
 * Tenant 98 (seededTenant, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/OrderRequestController::listOrderRequests()
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: transactions requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasColumn('transactions', 'crm_is_order_request')) {
        $this->markTestSkipped('Coluna transactions.crm_is_order_request ausente — migrations do Crm não rodaram.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PED17C-'.uniqid());
    config(['feature-flags.forced_on' => 'useV2CrmPedidos']);
});

function pedcPedido(int $biz, int $local, int $criador, string $marca, string $total = '10.00', string $status = 'ordered', int $pedidoDeCliente = 1): array
{
    $numero = 'PEDC-'.$marca.'-'.uniqid();
    $linha = [
        'business_id' => $biz, 'location_id' => $local, 'type' => 'sales_order', 'status' => $status,
        'payment_status' => 'due', 'crm_is_order_request' => $pedidoDeCliente, 'invoice_no' => $numero,
        'transaction_date' => now(), 'total_before_tax' => $total, 'final_total' => $total,
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ];
    if (Schema::hasColumn('transactions', 'essentials_duration')) {
        $linha['essentials_duration'] = 0;
    }

    return ['id' => (int) DB::table('transactions')->insertGetId($linha), 'numero' => $numero];
}

/** A prop deferida `pedidos`, pedida como o browser pede (X-Inertia + X-Requested-With). */
function pedcProps($teste, $user, string $query = ''): array
{
    $teste->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->get('/crm/order-request'.$query, [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'pedidos', 'X-Inertia-Partial-Component' => 'Crm/Pedidos/Index',
    ]);
    $r->assertOk();

    return (array) $r->json('props.pedidos');
}

function pedcNumeros(array $props): array
{
    return array_column((array) ($props['lista']['data'] ?? []), 'numero');
}

it('UC-CRMPED-01 · com a flag a lista é Inertia; sem ela, ou com ?classico=1, é a Blade', function () {
    $user = $this->usuarioComPermissoes(['so.view_all', 'access_all_locations'], $this->business);

    $this->actingAs($user)->get('/crm/order-request')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Pedidos/Index', false)->has('filtros'));

    $this->actingAs($user)->get('/crm/order-request?classico=1')->assertOk()->assertViewIs('crm::order_request.all_list');

    config(['feature-flags.forced_on' => '']);
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2CrmPedidos' ? false : parent::isOn($flag, $attrs);
        }
    });
    $this->actingAs($user)->get('/crm/order-request')->assertOk()->assertViewIs('crm::order_request.all_list');
});

it('UC-CRMPED-02 · só pedidos de cliente do meu negócio [T0]', function () {
    $user = $this->usuarioComPermissoes(['so.view_all', 'access_all_locations'], $this->business);
    $outro = $this->seededSupportClientTenant();
    $localOutro = EstoqueFixture::locationId($outro->id, '-PED17C-'.uniqid());

    $meu = pedcPedido($this->business->id, $this->local, $user->id, 'meu');
    $interno = pedcPedido($this->business->id, $this->local, $user->id, 'interno', '10.00', 'ordered', 0);
    $vizinho = pedcPedido($outro->id, $localOutro, $user->id, 'vizinho');

    $numeros = pedcNumeros(pedcProps($this, $user));

    expect($numeros)->toContain($meu['numero']);
    $this->assertNotContains($interno['numero'], $numeros, 'pedido de venda interno não é pedido de cliente');
    $this->assertNotContains($vizinho['numero'], $numeros, 'vazou pedido de outro negócio');
});

it('UC-CRMPED-03 · só os meus com so.view_own; nenhum sem so.view_*', function () {
    $proprio = $this->usuarioComPermissoes(['so.view_own', 'access_all_locations'], $this->business);
    $colega = $this->usuarioComPermissoes(['so.view_all', 'access_all_locations'], $this->business);
    $meu = pedcPedido($this->business->id, $this->local, $proprio->id, 'proprio');
    $dele = pedcPedido($this->business->id, $this->local, $colega->id, 'colega');

    $numeros = pedcNumeros(pedcProps($this, $proprio));
    expect($numeros)->toContain($meu['numero']);
    $this->assertNotContains($dele['numero'], $numeros, 'quem só vê os próprios viu o do colega');

    $semNada = pedcProps($this, $this->usuarioComPermissoes(['access_all_locations'], $this->business));
    $this->assertFalse($semNada['pode_ver'], 'sem so.view_all/so.view_own a tela não pode listar');
    $this->assertNull($semNada['lista'], 'sem so.view_all/so.view_own não pode vir linha nenhuma');
});

it('UC-CRMPED-04 · o rodapé soma os pedidos filtrados, sem contar em dobro pelas linhas de venda', function () {
    $user = $this->usuarioComPermissoes(['so.view_all', 'access_all_locations'], $this->business);
    $marca = 'soma'.uniqid();
    $a = pedcPedido($this->business->id, $this->local, $user->id, $marca, '10.50');
    $b = pedcPedido($this->business->id, $this->local, $user->id, $marca, '20.25');

    // Duas linhas de venda no pedido A: um SUM sobre o join cru contaria 10.50 duas vezes.
    $produto = EstoqueFixture::singleProduct($this->business->id);
    foreach ([1, 2] as $_) {
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $a['id'], 'product_id' => $produto->productId,
            'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => 1, 'quantity_returned' => 0,
            'unit_price' => 5.25, 'unit_price_inc_tax' => 5.25, 'item_tax' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $props = pedcProps($this, $user, '?q='.$marca);
    $linhas = collect((array) $props['lista']['data'])->keyBy('numero');

    $this->assertCount(2, $linhas, 'pré-condição: a busca tem de devolver exatamente os 2 pedidos semeados');
    // Caminho 1: cada linha traz o total gravado no pedido.
    expect($linhas[$a['numero']]['total'])->toBe('10.50');
    expect($linhas[$b['numero']]['total'])->toBe('20.25');
    // Caminho 2: o rodapé = a soma conhecida (10.50 + 20.25) = a soma das linhas devolvidas.
    expect($props['total'])->toBe('30.75');
    expect(number_format($linhas->sum(fn ($l) => (float) $l['total']), 2, '.', ''))->toBe($props['total']);
});

it('UC-CRMPED-05 · o filtro por status e a busca por número restringem a lista', function () {
    $user = $this->usuarioComPermissoes(['so.view_all', 'access_all_locations'], $this->business);
    $aberto = pedcPedido($this->business->id, $this->local, $user->id, 'aberto');
    $concluido = pedcPedido($this->business->id, $this->local, $user->id, 'concluido', '10.00', 'completed');

    $porStatus = pedcNumeros(pedcProps($this, $user, '?status=completed'));
    expect($porStatus)->toContain($concluido['numero']);
    $this->assertNotContains($aberto['numero'], $porStatus, 'o filtro de status não restringiu');

    $porBusca = pedcNumeros(pedcProps($this, $user, '?q='.$aberto['numero']));
    expect($porBusca)->toContain($aberto['numero']);
    $this->assertNotContains($concluido['numero'], $porBusca, 'a busca por número não restringiu');
});
