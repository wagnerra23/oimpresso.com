<?php

declare(strict_types=1);
// Cobre UC-RPV-01, UC-RPV-02, UC-RPV-03 (resources/js/Pages/Relatorios/PagamentosVenda/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por três caminhos: JSON do DataTable da Blade × props da Page × soma direta em
// transaction_payments. Cliente e janela (2099) próprios do teste. Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

const RPV_INICIO = '2099-08-01';
const RPV_FIM = '2099-08-31';

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['purchase_n_sell_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

/**
 * Uma venda do cliente com N pagamentos em minutos distintos; o pagamento de índice $trocoEm é troco.
 *
 * @return int id do cliente
 */
function rpvCenario(int $businessId, int $criador, int $n, ?int $trocoEm = null): int
{
    $cliente = DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => 'RPV '.uniqid(), 'mobile' => '0',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'contact_id' => $cliente, 'transaction_date' => '2099-08-01 09:00:00',
        'final_total' => 1000, 'total_before_tax' => 1000, 'created_by' => $criador, 'essentials_duration' => 0,
        'invoice_no' => 'RPV-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    for ($i = 0; $i < $n; $i++) {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $venda, 'business_id' => $businessId, 'amount' => 10 + $i, 'method' => 'cash',
            'is_return' => $i === $trocoEm ? 1 : 0, 'paid_on' => sprintf('2099-08-02 10:%02d:00', $i),
            'payment_ref_no' => 'RPV-'.$i, 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $cliente;
}

function rpvPage($teste, int $cliente, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/sell-payment-report?'.http_build_query([
        'tela' => 'nova', 'supplier_id' => $cliente, 'start_date' => RPV_INICIO, 'end_date' => RPV_FIM, 'page' => $pagina,
    ]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/PagamentosVenda/Index');

    return $r->json('props');
}

test('UC-RPV-01 valor — valores por pagamento (troco negativo) = JSON do DataTable da Blade = soma direta', function () {
    $cliente = rpvCenario($this->business->id, $this->user->id, 3, 1); // 10, 11 (troco), 12

    // Caminho 1: o JSON que o DataTable da Blade pede (valor com sinal no data-orig-value), com as
    // mesmas colunas e a mesma ordem que o report.js manda — sem elas o yajra quebra.
    $colunas = [];
    foreach (['', 'payment_ref_no', 'paid_on', 'amount', 'customer', 'customer_group', 'method', 'invoice_no', 'action'] as $i => $c) {
        $colunas[$i] = ['data' => $c, 'name' => $c, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/sell-payment-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1,
            'columns' => $colunas, 'order' => [['column' => 2, 'dir' => 'desc']], 'search' => ['value' => '', 'regex' => 'false'],
            'supplier_id' => $cliente, 'start_date' => RPV_INICIO, 'end_date' => RPV_FIM]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    $blade = collect($dt->json('data'))->mapWithKeys(function ($l) {
        preg_match('/data-orig-value="([^"]+)"/', (string) $l['amount'], $m);

        return [(int) $l['DT_RowId'] => (float) ($m[1] ?? 'NaN')];
    })->sortKeys()->all();

    // Caminho 2: a Page.
    $props = rpvPage($this, $cliente);
    $page = collect($props['linhas'])->mapWithKeys(fn ($l) => [(int) $l['id'] => (float) $l['valor']])->sortKeys()->all();
    expect($page)->toEqual($blade);

    // Caminho 3: soma direta, sem a consulta do controller.
    $direto = DB::table('transaction_payments as tp')->join('transactions as t', 't.id', '=', 'tp.transaction_id')
        ->where('tp.business_id', $this->business->id)->where('t.contact_id', $cliente)
        ->get(['tp.id', 'tp.amount', 'tp.is_return'])
        ->mapWithKeys(fn ($r) => [(int) $r->id => (float) ($r->is_return ? -1 * $r->amount : $r->amount)])->sortKeys()->all();
    expect($page)->toEqual($direto);

    // Conta à mão: 10 − 11 + 12 = 11, e o total da página é a soma das linhas exibidas.
    expect(array_values($page))->toEqualCanonicalizing([10.0, -11.0, 12.0]);
    expect((float) $props['total_pagina'])->toEqual(11.0);
});

test('UC-RPV-02 — 25 por página, mais recente primeiro; total só da página', function () {
    $cliente = rpvCenario($this->business->id, $this->user->id, 26); // valores 10..35, o 35 é o mais recente

    $p1 = rpvPage($this, $cliente, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect((float) $p1['linhas'][0]['valor'])->toEqual(35.0);
    expect((float) $p1['total_pagina'])->toEqual((float) array_sum(range(11, 35))); // sem o 10, que cai na página 2

    $p2 = rpvPage($this, $cliente, 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect((float) $p2['linhas'][0]['valor'])->toEqual(10.0);
});

test('UC-RPV-03 Tier 0 — pagamento do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $clienteAlheio = rpvCenario($alheio->id, $donoAlheio, 3);

    // O 98 pedindo o cliente do 99: nada.
    $props = rpvPage($this, $clienteAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['clientes'])->pluck('id'))->not->toContain($clienteAlheio);
    expect(collect($props['locais'])->pluck('id'))->not->toContain(EstoqueFixture::locationId($alheio->id));

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/sell-payment-report?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/sell-payment-report')->assertForbidden();
});
