<?php

declare(strict_types=1);
// Regra: /reports/stock-details exige stock_report.view, como o relatório de estoque de que é detalhe.
// Antes não conferia permissão nenhuma. Tenant 98 (ADR 0358).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
});

function stockDetailsComo($teste, array $permissoes)
{
    $u = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($u);
    session(['user.business_id' => $teste->business->id, 'user.id' => $u->id, 'business.id' => $teste->business->id]);

    return $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/reports/stock-details?product_id=1');
}

test('Tier 0 — detalhe de estoque exige stock_report.view', function () {
    expect(stockDetailsComo($this, ['access_all_locations'])->status())->toBe(403);
    expect(stockDetailsComo($this, ['stock_report.view', 'access_all_locations'])->status())->toBe(200);
});
