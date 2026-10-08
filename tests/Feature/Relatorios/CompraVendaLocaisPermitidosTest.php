<?php

declare(strict_types=1);
// Regra: Compra e venda só dos locais que o usuário pode ver (permitted_locations, Tier 0 — mesmo desenho do #8986).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// getPurchaseSell chamava getPurchaseTotals / getSellTotals / getTransactionTotals sem a lista de locais permitidos,
// e os três só filtram quando a recebem: quem só tem o local A via os totais de todos os locais. A tela nova de
// Compra e venda lê o mesmo endpoint, então herda a regra. Janela própria em 2099-04. Tenant 98 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $sufixo = uniqid();
    $this->localA = EstoqueFixture::locationId($this->business->id, '-RCV-A-'.$sufixo);
    $this->localB = EstoqueFixture::locationId($this->business->id, '-RCV-B-'.$sufixo);
    $criador = \App\User::factory()->create(['business_id' => $this->business->id])->id;

    // [local, tipo, status, final_total, pago]
    foreach ([
        [$this->localA, 'purchase', 'received', 1000, 400],
        [$this->localA, 'sell', 'final', 1500, 300],
        [$this->localA, 'sell_return', 'final', 200, 0],
        [$this->localB, 'purchase', 'received', 500, 0],
        [$this->localB, 'sell', 'final', 800, 800],
        [$this->localB, 'purchase_return', 'final', 100, 0],
    ] as [$local, $tipo, $status, $total, $pago]) {
        $contato = DB::table('contacts')->insertGetId([
            'business_id' => $this->business->id, 'type' => 'both', 'name' => 'RCVL '.uniqid(), 'mobile' => '0',
            'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tx = DB::table('transactions')->insertGetId([
            'business_id' => $this->business->id, 'location_id' => $local, 'type' => $tipo, 'status' => $status,
            'payment_status' => 'partial', 'contact_id' => $contato, 'transaction_date' => '2099-04-10 12:00:00',
            'final_total' => $total, 'total_before_tax' => $total, 'tax_amount' => 0, 'created_by' => $criador,
            'essentials_duration' => 0, 'invoice_no' => 'RCVL-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($pago > 0) {
            DB::table('transaction_payments')->insert([
                'transaction_id' => $tx, 'business_id' => $this->business->id, 'amount' => $pago, 'method' => 'cash',
                'paid_on' => '2099-04-10 12:00:00', 'created_by' => $criador, 'is_return' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
});

/** Entra com as permissões e locais dados e devolve os totais do endpoint de Compra e venda na janela. */
function rcvlVisto($teste, array $permissoes, array $locais = []): array
{
    $u = $teste->usuarioComPermissoes(array_merge(['purchase_n_sell_report.view'], $permissoes), $teste->business);
    // Local é permissão DIRETA no usuário — é só $user->permissions que o User::permitted_locations lê.
    foreach ($locais as $local) {
        $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('location.'.$local, 'web'));
    }
    $teste->actingAs($u);
    session(['user.business_id' => $teste->business->id, 'user.id' => $u->id, 'business.id' => $teste->business->id]);

    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/purchase-sell?'.http_build_query(['start_date' => '2099-04-01', 'end_date' => '2099-04-30', 'location_id' => '']));
    $r->assertOk();

    return [
        'compra' => (float) $r->json('purchase.total_purchase_inc_tax'),
        'compra_a_pagar' => (float) $r->json('purchase.purchase_due'),
        'devolucao_compra' => (float) $r->json('total_purchase_return'),
        'venda' => (float) $r->json('sell.total_sell_inc_tax'),
        'venda_a_receber' => (float) $r->json('sell.invoice_due'),
        'devolucao_venda' => (float) $r->json('total_sell_return'),
        'diferenca' => (float) $r->json('difference.total'),
        'diferenca_devida' => (float) $r->json('difference.due'),
    ];
}

test('Tier 0 — todos os locais: os mesmos totais de antes (os dois locais)', function () {
    // Conta à mão, A + B: compra 1000 + 500; a pagar 600 + 500; devolução de compra 100 (B); venda 1500 + 800;
    // a receber 1200 + 0; devolução de venda 200 (A); diferença 2300 − 200 − (1500 − 100) = 700; devida 1200 − 1100 = 100.
    expect(rcvlVisto($this, ['access_all_locations']))->toEqual([
        'compra' => 1500.0, 'compra_a_pagar' => 1100.0, 'devolucao_compra' => 100.0, 'venda' => 2300.0,
        'venda_a_receber' => 1200.0, 'devolucao_venda' => 200.0, 'diferenca' => 700.0, 'diferenca_devida' => 100.0,
    ]);
});

test('Tier 0 — só o local A: só os totais do A', function () {
    // Conta à mão, só A: diferença 1500 − 200 − (1000 − 0) = 300; devida 1200 − 600 = 600.
    expect(rcvlVisto($this, [], [$this->localA]))->toEqual([
        'compra' => 1000.0, 'compra_a_pagar' => 600.0, 'devolucao_compra' => 0.0, 'venda' => 1500.0,
        'venda_a_receber' => 1200.0, 'devolucao_venda' => 200.0, 'diferenca' => 300.0, 'diferenca_devida' => 600.0,
    ]);
});

test('Tier 0 — nenhum local: nada (lista vazia não vira "todos")', function () {
    expect(array_sum(array_map('abs', rcvlVisto($this, []))))->toEqual(0.0);
});
