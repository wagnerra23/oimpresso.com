<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Baseline F2 (MWART, ADR 0104) de /purchase-return/add/{id} → POST /purchase-return:
 * a devolução de compra feita a partir de uma compra, como a Blade grava HOJE.
 *
 * Trava `PurchaseReturnController::store` antes de o formulário virar React, para a F3
 * provar que não mudou estoque nem valor. Sem mudança de código de produção.
 *
 * ESTOQUE E VALOR (regra mestre, proibicoes.md). Cada número é provado por DOIS caminhos:
 * a conta à mão sobre o fixture e o que ficou gravado no banco.
 *   Fixture: compra de 10 un a 12,50 (com imposto) no local, saldo inicial 50.
 *   1ª gravação, devolve 3 com 1,25 de imposto:
 *     linha quantity_returned 0 → 3 · saldo 50 → 47
 *     devolução total_before_tax 37,50 (3 × 12,50) · final_total 38,75 (37,50 + 1,25)
 *   2ª gravação, devolve 5 sem imposto (a devolução da compra é UMA só; ela é reescrita):
 *     linha 3 → 5 · saldo 47 → 45 (baixa só a diferença) · final_total 62,50
 *
 * Achados que o baseline PRESERVA (não conserta — a F3 decide com o [W]):
 *   - o imposto vem do request (`tax_amount`), não é recalculado no servidor;
 *   - `add/{id}` de compra de outro negócio não devolve 404: o `find` volta nulo e a view
 *     quebra. Não vaza dado (o `where business_id` fica antes), mas a resposta é erro.
 *
 * Tenant do usuário = 98 (ADR 0358); outro negócio = 99. NUNCA biz=4.
 * Prefixo `dcStore` nas funções (o escopo global do Pest é compartilhado).
 *
 * @see app/Http/Controllers/PurchaseReturnController.php::add
 * @see app/Http/Controllers/PurchaseReturnController.php::store
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('purchase_lines') || ! Schema::hasTable('variation_location_details')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-DCST');
    $this->user = $this->usuarioComPermissoes(['purchase.update', 'purchase.view', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    session([
        'user.id' => $this->user->id, 'user.business_id' => $this->business->id,
        'business' => [
            'id' => $this->business->id, 'quantity_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24,
            'ref_no_prefixes' => ['purchase_return' => 'DCB'],
        ],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    $this->produto = EstoqueFixture::singleProduct($this->business->id);
    EstoqueFixture::setStock($this->produto, 0, $this->local, 50);
    [$this->compra, $this->linha] = dcStoreCompra($this->business->id, $this->local, $this->user->id, $this->produto);
});

/** Compra recebida, com fornecedor, e 1 linha de 10 un a 12,50. @return array{0:int,1:int} */
function dcStoreCompra(int $businessId, int $localId, int $userId, $produto): array
{
    $fornecedor = DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'supplier', 'name' => 'Fornecedor DCST', 'mobile' => '',
        'contact_id' => 'DCST-'.uniqid(), 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $localId, 'created_by' => $userId, 'contact_id' => $fornecedor,
        'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
        'ref_no' => 'CP-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 125,
        'final_total' => 125, 'tax_amount' => 0, 'discount_amount' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $compra, 'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'], 'quantity' => 10,
        'pp_without_discount' => '12.5000', 'purchase_price' => '12.5000', 'purchase_price_inc_tax' => '12.5000',
        'item_tax' => 0, 'quantity_returned' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$compra, $linha];
}

function dcStoreDevolucao(int $compraId)
{
    return DB::table('transactions')->where('type', 'purchase_return')->where('return_parent_id', $compraId)->get();
}

test('baseline: o formulário add abre com a compra e as linhas do negócio', function () {
    $r = $this->get('/purchase-return/add/'.$this->compra);

    $r->assertOk();
    $r->assertViewIs('purchase_return.add');
    expect($r->viewData('purchase')->id)->toBe($this->compra);
    expect($r->viewData('purchase')->purchase_lines->pluck('id')->all())->toBe([$this->linha]);
});

test('baseline estoque+valor: devolver 3 baixa o saldo e grava a devolução — por dois caminhos', function () {
    $this->post('/purchase-return', ['transaction_id' => $this->compra, 'returns' => [$this->linha => '3'], 'tax_amount' => '1.25'])
        ->assertRedirect('purchase-return');

    // Caminho 1: à mão. 3 × 12,50 = 37,50; + 1,25 de imposto = 38,75; saldo 50 − 3 = 47.
    expect(round(3 * 12.50, 2))->toBe(37.5)
        ->and(round(3 * 12.50 + 1.25, 2))->toBe(38.75);

    // Caminho 2: o banco.
    expect((float) DB::table('purchase_lines')->where('id', $this->linha)->value('quantity_returned'))->toBe(3.0);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->local))->toBe(47.0);
    $dev = dcStoreDevolucao($this->compra);
    expect($dev)->toHaveCount(1);
    expect(round((float) $dev[0]->total_before_tax, 2))->toBe(37.5);
    expect(round((float) $dev[0]->final_total, 2))->toBe(38.75);
    expect((int) $dev[0]->business_id)->toBe($this->business->id);
    expect((int) $dev[0]->location_id)->toBe($this->local);
    expect((string) $dev[0]->ref_no)->toStartWith('DCB');
});

test('baseline estoque+valor: gravar de novo reescreve a mesma devolução e baixa só a diferença', function () {
    $this->post('/purchase-return', ['transaction_id' => $this->compra, 'returns' => [$this->linha => '3'], 'tax_amount' => '1.25']);
    $this->post('/purchase-return', ['transaction_id' => $this->compra, 'returns' => [$this->linha => '5'], 'tax_amount' => '0']);

    // À mão: 5 × 12,50 = 62,50; saldo 47 − (5 − 3) = 45.
    expect(round(5 * 12.50, 2))->toBe(62.5);

    expect((float) DB::table('purchase_lines')->where('id', $this->linha)->value('quantity_returned'))->toBe(5.0);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->local))->toBe(45.0);
    $dev = dcStoreDevolucao($this->compra);
    expect($dev)->toHaveCount(1);
    expect(round((float) $dev[0]->final_total, 2))->toBe(62.5);
});

test('baseline Tier 0: gravar devolução de compra de outro negócio não escreve nada', function () {
    $outro = $this->seededSupportClientTenant();
    $outroLocal = EstoqueFixture::locationId($outro->id, '-DCST');
    $produtoAlheio = EstoqueFixture::singleProduct($outro->id);
    EstoqueFixture::setStock($produtoAlheio, 0, $outroLocal, 50);
    [$compraAlheia, $linhaAlheia] = dcStoreCompra($outro->id, $outroLocal, $this->user->id, $produtoAlheio);

    // Controle positivo: a do próprio negócio grava.
    $this->post('/purchase-return', ['transaction_id' => $this->compra, 'returns' => [$this->linha => '1'], 'tax_amount' => '0']);
    expect(dcStoreDevolucao($this->compra))->toHaveCount(1);

    // Prova pelas ESCRITAS da requisição, não pelo banco depois dela: o `findOrFail` lança antes
    // do `DB::beginTransaction()` e o `DB::rollBack()` do catch desfaz a transação do PRÓPRIO
    // teste (DatabaseTransactions) — o fixture inteiro some e qualquer leitura posterior volta
    // vazia, passando por vácuo. Em produção não há transação externa e esse rollBack é no-op.
    $escritas = [];
    DB::listen(function ($q) use (&$escritas) {
        if (preg_match('/^\s*(insert|update|delete)/i', $q->sql)) {
            $escritas[] = $q->sql;
        }
    });

    $r = $this->post('/purchase-return', ['transaction_id' => $compraAlheia, 'returns' => [$linhaAlheia => '4'], 'tax_amount' => '0']);

    $r->assertRedirect('purchase-return');
    expect(session('status.success'))->toBe(0);
    $tocadas = collect($escritas)->filter(fn ($sql) => preg_match('/`?(purchase_lines|variation_location_details|transactions|reference_counts)`?/i', $sql));
    expect($tocadas->all())->toBe([]);
});

test('baseline Tier 0: o formulário add de compra de outro negócio não mostra a compra', function () {
    $outro = $this->seededSupportClientTenant();
    $outroLocal = EstoqueFixture::locationId($outro->id, '-DCST');
    [$compraAlheia] = dcStoreCompra($outro->id, $outroLocal, $this->user->id, EstoqueFixture::singleProduct($outro->id));
    $refAlheia = DB::table('transactions')->where('id', $compraAlheia)->value('ref_no');

    $r = $this->get('/purchase-return/add/'.$compraAlheia);

    // Hoje é erro, não 404: o `find` com `where business_id` volta NULO e o controller quebra ao
    // ler as linhas. É isso que prova que a compra alheia não foi carregada. (O corpo da resposta
    // não serve de prova: com APP_DEBUG a página de erro lista as queries do próprio fixture.)
    expect($r->status())->toBe(500);
    expect($r->exception)->not->toBeNull();
    expect($r->exception->getMessage())->toContain('"purchase_lines" on null');
    expect($refAlheia)->not->toBeEmpty();
});

test('baseline: sem purchase.update, add e store devolvem 403', function () {
    $semPermissao = $this->usuarioComPermissoes(['purchase.view'], $this->business);
    $this->actingAs($semPermissao);

    $this->get('/purchase-return/add/'.$this->compra)->assertForbidden();
    $this->post('/purchase-return', ['transaction_id' => $this->compra, 'returns' => [$this->linha => '2'], 'tax_amount' => '0'])
        ->assertForbidden();
    expect(dcStoreDevolucao($this->compra))->toHaveCount(0);
});
