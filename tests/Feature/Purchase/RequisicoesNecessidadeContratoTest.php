<?php

declare(strict_types=1);

// Cobre UC-REQC-07 (PurchaseRequisition/Index.casos.md) · US-COM-030 (MRP).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * A tela de requisições mostra a necessidade de compra (MRP) na prop deferida `necessidade`.
 * O cálculo é do `NecessidadeDeCompra` (provado com números em NecessidadeDeCompraTest); aqui se prova
 * o que a tela recebe: o insumo abaixo do mínimo aparece com o quanto comprar e a data já formatada, e o
 * filtro de local da tela também filtra a necessidade.
 *
 * Insumo: mínimo 5, saldo 1, nada reservado nem a caminho → comprar 4, pedir hoje.
 * Tenant de teste 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('compras_prazo_fornecedor')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-MRPT');
    $this->outroLocal = EstoqueFixture::locationId($this->business->id, '-MRPT2');
    $this->user = $this->usuarioComPermissoes(['purchase_requisition.view_all', 'access_all_locations'], $this->business);
    config(['feature-flags.forced_on' => 'useV2ComprasRequisicoes']); // a tela React fica atrás desta flag
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session([
        'user.id' => $this->user->id, 'user.business_id' => $this->business->id,
        'business' => ['id' => $this->business->id, 'quantity_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    $this->insumo = EstoqueFixture::singleProduct($this->business->id);
    DB::table('products')->where('id', $this->insumo->productId)->update(['alert_quantity' => 5, 'name' => 'Lona MRP tela']);
    EstoqueFixture::setStock($this->insumo, 0, $this->local, 1);
});

function mrpTelaNecessidade($teste, string $query = ''): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'PurchaseRequisition/Index', 'X-Inertia-Partial-Data' => 'necessidade',
    ])->get('/purchase-requisition'.($query !== '' ? '?'.$query : ''));
    $r->assertOk();

    return collect($r->json('props.necessidade') ?? [])
        ->firstWhere('chave', $teste->local.':'.$teste->insumo->variationId()) ?? [];
}

it('UC-REQC-07 · o insumo abaixo do mínimo aparece com o quanto comprar e a data formatada', function () {
    $l = mrpTelaNecessidade($this);

    expect($l)->not->toBe([], 'A necessidade do insumo não chegou na tela.');
    expect($l['insumo'])->toBe('Lona MRP tela');
    expect((float) $l['necessidade'])->toBe(4.0);
    expect((float) $l['saldo'])->toBe(1.0);
    expect((float) $l['minimo'])->toBe(5.0);
    expect($l['pedir_ate'])->toBe(now()->format('d/m/Y'));
    expect($l['atrasado'])->toBeFalse();
});

it('UC-REQC-07 · o filtro de local da tela também filtra a necessidade', function () {
    expect(mrpTelaNecessidade($this, 'location_id='.$this->outroLocal))->toBe([]);
    expect(mrpTelaNecessidade($this, 'location_id='.$this->local))->not->toBe([]);
});
