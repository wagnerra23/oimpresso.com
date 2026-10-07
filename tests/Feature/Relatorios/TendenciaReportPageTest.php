<?php

declare(strict_types=1);
// Cobre UC-RTE-01, UC-RTE-02 (resources/js/Pages/Relatorios/Tendencia/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Ranking por três caminhos: o gráfico que a Blade recebe × props da Page × soma direta em
// transaction_sell_lines, mais a conta à mão. Tenant 98 × 99 (ADR 0358). Local próprio do teste,
// pra o ranking não misturar venda que o seed já tenha.

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['trending_product_report.view', 'access_all_locations'], $this->business);
    $this->local = EstoqueFixture::locationId($this->business->id, '-RTE');
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function rteVenda(int $businessId, int $local, int $criador, $produto, float $qtd, float $devolvido = 0, string $status = 'final'): void
{
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => $status,
        'payment_status' => 'paid', 'transaction_date' => '2099-07-10 12:00:00', 'final_total' => $qtd * 10,
        'total_before_tax' => $qtd * 10, 'created_by' => $criador, 'essentials_duration' => 0,
        'invoice_no' => 'RTE-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $tx, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => $qtd, 'quantity_returned' => $devolvido, 'unit_price' => 10, 'unit_price_inc_tax' => 10,
        'unit_price_before_discount' => 10, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{0: string, 1: string} SKUs do produto A (mais vendido) e do B */
function rteCenario(int $businessId, int $local, int $criador): array
{
    $a = EstoqueFixture::singleProduct($businessId);
    $b = EstoqueFixture::singleProduct($businessId);
    rteVenda($businessId, $local, $criador, $a, 10, 2);   // 10 − 2 devolvidos
    rteVenda($businessId, $local, $criador, $a, 3);
    rteVenda($businessId, $local, $criador, $b, 5);
    rteVenda($businessId, $local, $criador, $b, 100, 0, 'draft'); // controle: rascunho não é venda

    return [DB::table('products')->where('id', $a->productId)->value('sku'), DB::table('products')->where('id', $b->productId)->value('sku')];
}

/** Ranking da Page: lista ordenada de [sku, vendido]. */
function rtePage($teste, int $local): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/trending-products?'.http_build_query(['tela' => 'nova', 'location_id' => $local, 'limit' => 50]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Tendencia/Index');

    return ['ranking' => collect($r->json('props.linhas'))->map(fn ($l) => [$l['sku'], (float) $l['vendido']])->all(), 'props' => $r->json('props')];
}

test('UC-RTE-01 — ranking da Page = gráfico da Blade = soma direta', function () {
    [$a, $b] = rteCenario($this->business->id, $this->local, $this->user->id);

    // Caminho 1: o gráfico que a Blade recebe (rótulo "produto - sku (unidade)", valor = quantidade).
    $blade = $this->withHeaders([])->get('/reports/trending-products?'.http_build_query(['location_id' => $this->local, 'limit' => 50]));
    $blade->assertOk();
    $grafico = $blade->viewData('chart');
    $valoresBlade = array_map('floatval', $grafico->datasets[0]->values);

    // Caminho 2: a Page.
    $page = rtePage($this, $this->local);
    expect(array_column($page['ranking'], 1))->toEqual($valoresBlade);
    expect(count($grafico->labels))->toBe(count($page['ranking']));
    foreach ($page['ranking'] as $i => [$sku]) {
        expect($grafico->labels[$i])->toContain($sku);
    }

    // Caminho 3: soma direta no local do teste (quantidade − devolvida, só venda final).
    $direto = DB::table('transaction_sell_lines as tsl')->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
        ->join('products as p', 'p.id', '=', 'tsl.product_id')
        ->where('t.business_id', $this->business->id)->where('t.location_id', $this->local)
        ->where('t.type', 'sell')->where('t.status', 'final')->whereNull('tsl.parent_sell_line_id')
        ->groupBy('tsl.product_id', 'p.sku')->orderByDesc('vendido')
        ->select('p.sku', DB::raw('SUM(tsl.quantity) - COALESCE(SUM(tsl.quantity_returned), 0) as vendido'))->get()
        ->map(fn ($r) => [$r->sku, (float) $r->vendido])->all();
    expect($page['ranking'])->toEqual($direto);

    // Conta à mão: A = 10 − 2 + 3 = 11 na frente; B = 5 (os 100 em rascunho ficam fora).
    expect($page['ranking'])->toEqual([[$a, 11.0], [$b, 5.0]]);
});

test('UC-RTE-02 Tier 0 — o negócio 99 não entra no ranking nem nos locais; permissão da Blade', function () {
    rteCenario($this->business->id, $this->local, $this->user->id);
    $antes = rtePage($this, $this->local);

    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $localAlheio = EstoqueFixture::locationId($alheio->id, '-RTE');
    [$skuAlheio] = rteCenario($alheio->id, $localAlheio, $donoAlheio);

    // Mesmo pedindo o local do 99, o 98 não vê venda dele.
    expect(rtePage($this, $this->local)['ranking'])->toEqual($antes['ranking']);
    $pedindoAlheio = rtePage($this, $localAlheio);
    expect(collect($pedindoAlheio['ranking'])->pluck(0))->not->toContain($skuAlheio);
    expect(collect($pedindoAlheio['props']['locais'])->pluck('id'))->not->toContain($localAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/trending-products?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/trending-products')->assertForbidden();
});
