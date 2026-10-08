<?php

declare(strict_types=1);
// Regra: id que vem da request NUNCA entra cru em SQL (ReportController, 6 funções que montavam
// "AND x.location_id=$location_id" / "AND t.contact_id=$supplier_id" em DB::raw/whereRaw).
// Prova por sintaxe: o payload "1)" quebrava a consulta (500, ou 200 com {"error"} no DataTables);
// com o id convertido em inteiro a consulta roda. Tenant 98 (ADR 0358).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['stock_report.view', 'purchase_n_sell_report.view', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

dataset('id cru no SQL', [
    'getStockDetails · location_id' => ['/reports/stock-details', ['product_id' => 1, 'location_id' => '1)']],
    'getLotReport · location_id' => ['/reports/lot-report', ['location_id' => '1)']],
    'purchasePaymentReport · supplier_id' => ['/reports/purchase-payment-report', ['supplier_id' => '1)']],
    'sellPaymentReport · supplier_id' => ['/reports/sell-payment-report', ['supplier_id' => '1)']],
    'getproductSellGroupedReport · location_id' => ['/reports/product-sell-grouped-report', ['location_id' => '1)']],
    'productSellReportBy · location_id' => ['/reports/product-sell-grouped-by', ['location_id' => '1)', 'group_by' => 'category']],
]);

test('Tier 0 — id da request não entra cru no SQL do relatório', function (string $rota, array $params) {
    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get($rota.'?'.http_build_query($params + ['draw' => 1, 'start' => 0, 'length' => 10]));

    expect($r->status())->toBe(200);
    // O DataTables (yajra) engole a exceção e responde 200 com {"error": "..."} — status sozinho
    // não prova nada. A prova é a consulta ter rodado: sem erro SQL no corpo nem na chave error.
    expect((string) $r->getContent())->not->toContain('SQLSTATE');
    expect($r->headers->get('Content-Type') !== null && str_contains((string) $r->headers->get('Content-Type'), 'json') ? $r->json('error') : null)->toBeNull();
})->with('id cru no SQL');
