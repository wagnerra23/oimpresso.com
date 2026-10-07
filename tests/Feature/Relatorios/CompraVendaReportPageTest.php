<?php

declare(strict_types=1);
// Cobre UC-RCV-01, UC-RCV-02 (resources/js/Pages/Relatorios/CompraVenda/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por dois caminhos: JSON do endpoint × soma direta em transactions/transaction_payments,
// conferida ainda contra a conta à mão. Tenant 98 × 99 (ADR 0358). Janela em 2099 para não somar
// movimento que o seed já tenha.

uses(DatabaseTransactions::class);

const RCV_INICIO = '2099-03-01';
const RCV_FIM = '2099-03-31';

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['purchase_n_sell_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

/** Uma transação no dia 10 da janela, com pagamento opcional. */
function rcvTransacao(int $businessId, int $criador, string $tipo, string $status, float $final, float $semImposto, float $pago = 0): void
{
    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'both', 'name' => 'RCV '.uniqid(), 'mobile' => '0',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => $tipo,
        'status' => $status, 'payment_status' => 'partial', 'contact_id' => $contato,
        'transaction_date' => '2099-03-10 12:00:00', 'final_total' => $final, 'total_before_tax' => $semImposto,
        'tax_amount' => $final - $semImposto, 'created_by' => $criador, 'essentials_duration' => 0,
        'invoice_no' => 'RCV-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($pago > 0) {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $tx, 'business_id' => $businessId, 'amount' => $pago, 'method' => 'cash',
            'paid_on' => '2099-03-10 12:00:00', 'created_by' => $criador, 'is_return' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function rcvCenario(int $businessId, int $criador): void
{
    rcvTransacao($businessId, $criador, 'purchase', 'received', 1000, 900, 400);
    rcvTransacao($businessId, $criador, 'purchase_return', 'final', 100, 90);
    rcvTransacao($businessId, $criador, 'sell', 'final', 1500, 1350, 300);
    rcvTransacao($businessId, $criador, 'sell_return', 'final', 200, 180);
    rcvTransacao($businessId, $criador, 'sell', 'draft', 777, 777); // controle: rascunho não é venda
}

function rcvJson($teste): array
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/purchase-sell?'.http_build_query(['start_date' => RCV_INICIO, 'end_date' => RCV_FIM, 'location_id' => '']));
    $r->assertOk();

    return [
        'compra_sem_imposto' => (float) $r->json('purchase.total_purchase_exc_tax'),
        'compra_com_imposto' => (float) $r->json('purchase.total_purchase_inc_tax'),
        'compra_a_pagar' => (float) $r->json('purchase.purchase_due'),
        'devolucao_compra' => (float) $r->json('total_purchase_return'),
        'venda_sem_imposto' => (float) $r->json('sell.total_sell_exc_tax'),
        'venda_com_imposto' => (float) $r->json('sell.total_sell_inc_tax'),
        'venda_a_receber' => (float) $r->json('sell.invoice_due'),
        'devolucao_venda' => (float) $r->json('total_sell_return'),
        'diferenca' => (float) $r->json('difference.total'),
        'diferenca_devida' => (float) $r->json('difference.due'),
    ];
}

test('UC-RCV-01 valor — os 10 totais do endpoint batem com a soma direta e com a conta à mão', function () {
    rcvCenario($this->business->id, $this->user->id);
    $endpoint = rcvJson($this);

    // Caminho 2: soma direta, sem TransactionUtil.
    $base = fn (string $tipo) => DB::table('transactions')->where('business_id', $this->business->id)
        ->where('type', $tipo)->whereBetween(DB::raw('DATE(transaction_date)'), [RCV_INICIO, RCV_FIM]);
    $pago = fn (string $tipo, ?string $status = null) => (float) DB::table('transaction_payments as tp')
        ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
        ->where('t.business_id', $this->business->id)->where('t.type', $tipo)
        ->when($status, fn ($q) => $q->where('t.status', $status))
        ->whereBetween(DB::raw('DATE(t.transaction_date)'), [RCV_INICIO, RCV_FIM])->sum('tp.amount');
    $vendaFinal = fn () => $base('sell')->where('status', 'final');

    $compraCom = (float) $base('purchase')->sum('final_total');
    $vendaCom = (float) $vendaFinal()->sum('final_total');
    $devCompra = (float) $base('purchase_return')->sum('final_total');
    $devVenda = (float) $base('sell_return')->sum('final_total');
    $soma = [
        'compra_sem_imposto' => (float) $base('purchase')->sum('total_before_tax'),
        'compra_com_imposto' => $compraCom,
        'compra_a_pagar' => $compraCom - $pago('purchase'),
        'devolucao_compra' => $devCompra,
        'venda_sem_imposto' => (float) $vendaFinal()->sum('total_before_tax'),
        'venda_com_imposto' => $vendaCom,
        'venda_a_receber' => $vendaCom - $pago('sell', 'final'),
        'devolucao_venda' => $devVenda,
        'diferenca' => $vendaCom - $devVenda - ($compraCom - $devCompra),
        'diferenca_devida' => ($vendaCom - $pago('sell', 'final')) - ($compraCom - $pago('purchase')),
    ];
    expect($endpoint)->toEqual($soma);

    // Caminho 3: a conta à mão do cenário (o rascunho de 777 fica fora).
    expect($endpoint)->toEqual([
        'compra_sem_imposto' => 900.0, 'compra_com_imposto' => 1000.0, 'compra_a_pagar' => 600.0,
        'devolucao_compra' => 100.0, 'venda_sem_imposto' => 1350.0, 'venda_com_imposto' => 1500.0,
        'venda_a_receber' => 1200.0, 'devolucao_venda' => 200.0, 'diferenca' => 400.0, 'diferenca_devida' => 600.0,
    ]);

    // A Page não recebe total nenhum: os números vêm do mesmo endpoint da Blade.
    $p = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/reports/purchase-sell?tela=nova');
    $p->assertOk();
    expect($p->json('component'))->toBe('Relatorios/CompraVenda/Index');
    expect(array_keys($p->json('props')))->not->toContain('purchase');
    expect(array_keys($p->json('props')))->not->toContain('difference');
});

test('UC-RCV-02 Tier 0 — o negócio 99 não entra nos totais nem nos locais, e a permissão é a da Blade', function () {
    rcvCenario($this->business->id, $this->user->id);
    $antes = rcvJson($this);

    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = (int) DB::table('users')->where('business_id', $alheio->id)->value('id');
    rcvCenario($alheio->id, $donoAlheio);
    $localAlheio = EstoqueFixture::locationId($alheio->id);

    expect(rcvJson($this))->toEqual($antes);

    $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    $ids = collect($this->withHeaders($inertia)->get('/reports/purchase-sell?tela=nova')->json('props.locais'))->pluck('id');
    expect($ids)->toContain(EstoqueFixture::locationId($this->business->id));
    expect($ids)->not->toContain($localAlheio);

    // Sem a permissão: 403 na tela nova, no JSON e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($inertia)->get('/reports/purchase-sell?tela=nova')->assertForbidden();
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/reports/purchase-sell')->assertForbidden();
    $this->get('/reports/purchase-sell')->assertForbidden();
});
