<?php

declare(strict_types=1);
// Mede o achado do caminho antigo de estoque inicial registrado no UC-PIDX-15
// (resources/js/Pages/Produto/Index.casos.md): zerar uma linha existente baixa o saldo do local?

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Estoque inicial pelo formulário Blade (`POST /opening-stock/save`, `OpeningStockController@save`).
 *
 * Pela leitura do `save()`: numa linha existente, quando a quantidade nova é 0 o
 * `updateProductQuantity` não é chamado (`if ($qty_remaining != 0)`), mas a linha de compra é
 * gravada com quantidade 0. Se isso for verdade, o saldo do local fica com a quantidade antiga.
 *
 * Este arquivo MEDE. Não conserta e não escolhe remédio — mexe em estoque, então o conserto segue a
 * regra mestre (duas provas + antes→depois) e é decisão [W]/[F].
 *
 * Duas medidas independentes do mesmo saldo:
 *   1. `variation_location_details.qty_available` (o saldo que a venda e os relatórios leem);
 *   2. a soma das linhas de estoque inicial do local (o que o próprio formulário mostra).
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PEZ-' . uniqid());

    $this->user = $this->usuarioComPermissoes(['product.opening_stock', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
        'business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'business.enable_lot_number' => 1, 'business.enable_product_expiry' => 1,
        // O `save()` lê o início do ano fiscal da sessão; em produção quem põe é o SetSessionData.
        'financial_year' => (new \App\Utils\BusinessUtil)->getCurrentFinancialYear($this->business->id),
    ]);

    $this->produto = EstoqueFixture::singleProduct($this->business->id);
    DB::table('product_locations')->insert(['product_id' => $this->produto->productId, 'location_id' => $this->local]);
});

function pezSalvar($teste, array $linhas): void
{
    $teste->post('/opening-stock/save', [
        'product_id' => $teste->produto->productId,
        'stocks' => [$teste->local => [$teste->produto->variationId(0) => $linhas]],
    ]);
}

function pezSaldo($teste): ?float
{
    $r = DB::table('variation_location_details')
        ->where('variation_id', $teste->produto->variationId(0))->where('location_id', $teste->local)->first();

    return $r ? (float) $r->qty_available : null;
}

function pezLinhas($teste): array
{
    return DB::table('purchase_lines as pl')
        ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
        ->where('t.type', 'opening_stock')->where('t.opening_stock_product_id', $teste->produto->productId)
        ->where('t.location_id', $teste->local)
        ->orderBy('pl.id')
        ->get(['pl.id', 'pl.quantity', 't.final_total'])
        ->all();
}

it('UC-PIDX-15 · medida: zerar a única linha de estoque inicial deixa o saldo do local em 0', function () {
    pezSalvar($this, [['quantity' => '10,00', 'purchase_price' => '5,00']]);
    // Pré-condição: o lançamento entrou (sem isto o resto mediria o vazio).
    expect(pezSaldo($this))->toBe(10.0);
    $linhas = pezLinhas($this);
    expect($linhas)->toHaveCount(1);

    pezSalvar($this, [['quantity' => '0,00', 'purchase_price' => '5,00', 'purchase_line_id' => $linhas[0]->id]]);

    $depois = pezLinhas($this);
    $somaDasLinhas = array_sum(array_map(fn ($l) => (float) $l->quantity, $depois));
    // Medida 2: o formulário passa a mostrar 0 neste local.
    expect($somaDasLinhas)->toBe(0.0);
    // Medida 1: o saldo que a venda lê tem de bater com o formulário.
    expect(pezSaldo($this))->toBe($somaDasLinhas);
});

it('UC-PIDX-15 · controle: baixar a linha de 10 para 1 desconta o saldo (o mesmo caminho, sem o zero)', function () {
    // Controle positivo: prova que o cenário e as medidas funcionam quando a quantidade nova não é 0.
    pezSalvar($this, [['quantity' => '10,00', 'purchase_price' => '5,00']]);
    $linhas = pezLinhas($this);

    pezSalvar($this, [['quantity' => '1,00', 'purchase_price' => '5,00', 'purchase_line_id' => $linhas[0]->id]]);

    expect(pezSaldo($this))->toBe(1.0);
    expect((float) pezLinhas($this)[0]->quantity)->toBe(1.0);
});
