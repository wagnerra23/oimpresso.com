<?php

declare(strict_types=1);
// Cobre UC-PIDX-49 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Services\Estoque\ConsultaDeLotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * A aba Lotes do painel do produto (CV3): os lotes com saldo, pela mesma consulta do relatório de Lotes, e o
 * link para o relatório filtrado no produto. Numa comunicação visual, cada rolo é um lote.
 *
 * O saldo é provado por três caminhos: a conta à mão, a tela do relatório de Lotes filtrada no produto
 * (UC-RLT-07) e o painel. Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
});

function plpEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24]);
}

/**
 * Um produto com um lote por item de $lotes: [numero, comprado, vendido, validade]. Uma compra só, num local
 * próprio do teste.
 */
function plpCenario(int $biz, int $criador, array $lotes): int
{
    $produto = EstoqueFixture::singleProduct($biz);
    $local = EstoqueFixture::locationId($biz, '-PLP');
    DB::table('product_locations')->insert(['product_id' => $produto->productId, 'location_id' => $local]);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $local, 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => '2099-12-01 10:00:00', 'final_total' => 0, 'total_before_tax' => 0,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'PLP-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($lotes as [$numero, $comprado, $vendido, $validade]) {
        $pl = DB::table('purchase_lines')->insertGetId([
            'transaction_id' => $compra, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
            'quantity' => $comprado, 'purchase_price' => 1, 'item_tax' => 0, 'lot_number' => $numero, 'exp_date' => $validade,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($vendido > 0) {
            DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 999999999, 'purchase_line_id' => $pl, 'quantity' => $vendido, 'qty_returned' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    return $produto->productId;
}

function plpPainel($teste, int $productId): ?array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versao, 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Produto/Index', 'X-Inertia-Partial-Data' => 'detalhe',
    ])->get('/products?ver='.$productId);
    $r->assertOk();
    expect($r->json('props.detalhe.id'))->toBe($productId);

    return $r->json('props.detalhe.lotes');
}

it('UC-PIDX-49 · o painel mostra os lotes com saldo, os mesmos do relatório de Lotes, e o link para ele filtrado', function () {
    plpEntrar($this, ['product.view', 'stock_report.view', 'access_all_locations']);
    // Conta à mão: ROLO-A 50 − 12 = 38; ROLO-B 30 − 30 = 0 (sai do painel, fica no relatório); ROLO-C 25, vencido.
    $produto = plpCenario($this->business->id, $this->user->id, [
        ['PLP-ROLO-A', 50, 12, '2099-12-31'], ['PLP-ROLO-B', 30, 30, '2099-12-31'], ['PLP-ROLO-C', 25, 0, '2020-01-01'],
    ]);

    $lotes = plpPainel($this, $produto);

    // Caminho 1: a conta à mão.
    expect(array_column($lotes['lotes'], 'estoque', 'lote'))->toEqual(['PLP-ROLO-A' => 38.0, 'PLP-ROLO-C' => 25.0]);
    expect(array_column($lotes['lotes'], 'vencido', 'lote'))->toBe(['PLP-ROLO-A' => false, 'PLP-ROLO-C' => true]);
    expect($lotes['lotes'][0]['validade'])->toBe('31/12/2099');
    expect($lotes['lotes'][0]['variacao'])->toBeNull();
    expect($lotes['mais'])->toBeFalse();
    expect($lotes['link'])->toBe('/reports/lot-report?tela=nova&product_id='.$produto);

    // Caminho 2: o relatório de Lotes no link do painel. Os mesmos saldos; lá o lote zerado também aparece.
    $relatorio = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versao, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get($lotes['link'])->assertOk()->json('props.linhas');
    expect(array_column($relatorio, 'estoque', 'lote'))->toEqual(['PLP-ROLO-A' => 38.0, 'PLP-ROLO-B' => 0.0, 'PLP-ROLO-C' => 25.0]);
});

it('UC-PIDX-49 · sem ver o relatório de estoque não vêm lotes; com mais de 50 lotes de saldo, vêm 50 e o aviso', function () {
    $lotes = [];
    for ($n = 1; $n <= 51; $n++) {
        $lotes[] = [sprintf('PLP-MUITOS-%02d', $n), 1, 0, '2099-12-31'];
    }
    plpEntrar($this, ['product.view', 'access_all_locations']);
    $produto = plpCenario($this->business->id, $this->user->id, $lotes);
    expect(plpPainel($this, $produto))->toBeNull();

    plpEntrar($this, ['product.view', 'stock_report.view', 'access_all_locations']);
    $muitos = plpPainel($this, $produto);
    expect($muitos['lotes'])->toHaveCount(50);
    expect($muitos['mais'])->toBeTrue();
});

it('UC-PIDX-49 · os lotes saem só para a empresa dona do produto', function () {
    plpEntrar($this, ['product.view', 'stock_report.view', 'access_all_locations']);
    $produto = plpCenario($this->business->id, $this->user->id, [['PLP-TIER0', 10, 0, '2099-12-31']]);

    $consulta = fn (int $biz) => app(ConsultaDeLotes::class)->consulta($biz, ['product_id' => $produto])->toBase()->get()->pluck('lot_number')->all();
    expect($consulta($this->business->id))->toBe(['PLP-TIER0']);
    // Tier 0: o mesmo produto pedido por outra empresa não tem lote.
    $outra = $this->seededSupportClientTenant();
    expect($consulta($outra->id))->toBe([]);
});
