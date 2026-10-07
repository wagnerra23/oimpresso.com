<?php

declare(strict_types=1);
// Cobre UC-COM-03, UC-COM-04 (resources/js/Pages/Report/SalesRepresentative/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor por dois caminhos: endpoint × conta refeita aqui. Tenant 98 × fictício (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'cmmsn_percent')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['sales_representative.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function srVendaComissionada(int $businessId, int $agenteId, int $criador, float $qtd, float $preco): void
{
    $location = EstoqueFixture::locationId($businessId);
    $produto = EstoqueFixture::singleProduct($businessId);
    $contato = DB::table('contacts')->where('business_id', $businessId)->value('id');
    expect($contato)->not->toBeNull();

    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $location, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'contact_id' => $contato, 'transaction_date' => now(),
        'final_total' => $qtd * $preco, 'total_before_tax' => $qtd * $preco, 'created_by' => $criador,
        'commission_agent' => $agenteId, 'invoice_no' => 'SR-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $tx, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => $qtd, 'unit_price' => $preco, 'unit_price_inc_tax' => $preco, 'unit_price_before_discount' => $preco, 'quantity_returned' => 0,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('UC-COM-03 valor — a comissão do endpoint bate com a conta refeita, e muda com o vendedor', function () {
    $agente = \App\User::factory()->create(['business_id' => $this->business->id, 'is_cmmsn_agnt' => 1, 'cmmsn_percent' => 5]);
    $outro = \App\User::factory()->create(['business_id' => $this->business->id, 'is_cmmsn_agnt' => 1, 'cmmsn_percent' => 5]);
    srVendaComissionada($this->business->id, $agente->id, $this->user->id, 2, 150);

    $filtro = ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()];
    $h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    $r = $this->withHeaders($h)->get('/reports/sales-representative-total-commission?'.http_build_query($filtro + ['commission_agent' => $agente->id]));
    $r->assertOk();
    // Caminho 1: endpoint. Caminho 2: 5 × (2 × 150) / 100, refeito aqui.
    expect((float) $r->json('total_sales_with_commission'))->toBe(300.0);
    expect((float) $r->json('total_commission'))->toBe(5 * (2 * 150) / 100);

    // Controle positivo: outro vendedor, mesmo filtro, outro número.
    $r2 = $this->withHeaders($h)->get('/reports/sales-representative-total-commission?'.http_build_query($filtro + ['commission_agent' => $outro->id]));
    expect((float) $r2->json('total_commission'))->toBe(0.0);

    // A tela recebe só filtros e moeda, nenhum total: os números vêm do mesmo endpoint.
    $p = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/reports/sales-representative-report?tela=nova');
    $p->assertOk();
    expect($p->json('component'))->toBe('Report/SalesRepresentative/Index');
    expect(array_keys($p->json('props')))->not->toContain('total_commission');
    expect($p->json('props.base_comissao'))->toBeIn(['invoice_value', 'payment_received']);
});

test('UC-COM-04 Tier 0 — mesma permissão da Blade e vendedores só do negócio da sessão', function () {
    $alheio = \App\User::factory()->create(['business_id' => $this->seededSupportClientTenant()->id]);
    $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $ids = collect($this->withHeaders($inertia)->get('/reports/sales-representative-report?tela=nova')->json('props.vendedores'))->pluck('id');
    expect($ids)->toContain($this->user->id);
    expect($ids)->not->toContain($alheio->id);

    // Sem a permissão: 403 na tela nova, como na Blade.
    $sem = $this->usuarioComPermissoes([], $this->business);
    $this->actingAs($sem);
    $this->withHeaders($inertia)->get('/reports/sales-representative-report?tela=nova')->assertForbidden();
    $this->get('/reports/sales-representative-report')->assertForbidden();
});
