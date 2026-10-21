<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Jobs\RequisicaoEstoqueMinimoJob;
use Modules\Compras\Services\RequisicaoEstoqueMinimoService;
use Tests\Support\WithSeededTenant;

/**
 * US-COM-027 · UC-FLX-19 — estoque mínimo vira requisição de compra.
 * Contrato: resources/js/Pages/PurchaseRequisition/Index.casos.md (UC-REQMIN-01..08).
 *
 * Tenant 98 (ADR 0358) e, como business B, o cliente fictício 99 (nunca o 4).
 *
 * Regra mestre de ESTOQUE (proibicoes.md): as quantidades sugeridas são provadas por dois
 * caminhos — (1) o número escrito à mão no arranjo abaixo e (2) `reqMinEsperado()`, que refaz
 * teto(mínimo − saldo) em PHP sobre o mesmo arranjo, sem o serviço. E nenhum saldo muda.
 *
 * Arranjo do tenant 98:
 *   Local L1 · A mínimo 10, saldo 3,5   → 7  (6,5 arredonda para cima)
 *   Local L1 · B mínimo 20, saldo 0     → 20
 *   Local L1 · C mínimo 5,  saldo 12    → nada (acima)
 *   Local L1 · D mínimo 8,  saldo 8     → nada (igual não é abaixo)
 *   Local L2 · A mínimo 10, saldo 9,99  → 1
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->bizA = (int) $this->seededTenant()->id;
        $this->bizB = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode no MySQL real (lane compras-pest ou CT 100).');
    }

    $this->servico = app(RequisicaoEstoqueMinimoService::class);
    $this->servico->definir($this->bizA, true);
    $this->servico->definir($this->bizB, true);
});

function reqMinLocal(int $biz): int
{
    // Esquema e layout próprios: o local de referência do seed nasce com FK desligada (id 0) e copiá-lo
    // falha com a FK ligada no teste (SQLSTATE 23000).
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => $biz, 'name' => 'Esq reqmin '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => $biz, 'name' => 'Lay reqmin '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $biz, 'name' => 'Local reqmin '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Gravatal', 'zip_code' => '8873500', 'invoice_scheme_id' => $esquema,
        'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

/** @return array{product: int, variation: int, grupo: int} */
function reqMinProduto(int $biz, float $minimo): array
{
    $agora = Carbon::now();
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $sku = 'REQMIN-'.uniqid();
    $unit = (int) (DB::table('units')->where('business_id', $biz)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade '.$sku, 'short_name' => 'un',
            'allow_decimal' => 1, 'created_by' => $owner, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
    $product = DB::table('products')->insertGetId([
        'business_id' => $biz, 'name' => 'Insumo '.$sku, 'type' => 'single', 'unit_id' => $unit,
        'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => $minimo, 'tax_type' => 'exclusive',
        'barcode_type' => 'C128', 'created_by' => $owner, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $product, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $variation = DB::table('variations')->insertGetId([
        'product_id' => $product, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'default_purchase_price' => 1, 'dpp_inc_tax' => 1, 'profit_percent' => 0,
        'default_sell_price' => 2, 'sell_price_inc_tax' => 2, 'created_at' => $agora, 'updated_at' => $agora,
    ]);

    return ['product' => $product, 'variation' => $variation, 'grupo' => $grupo];
}

function reqMinSaldo(array $p, int $local, float $saldo): void
{
    DB::table('variation_location_details')->insert([
        'product_id' => $p['product'], 'product_variation_id' => $p['grupo'], 'variation_id' => $p['variation'],
        'location_id' => $local, 'qty_available' => $saldo, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Segundo caminho: teto(mínimo − saldo) refeito sem o serviço; null = não pede. */
function reqMinEsperado(float $minimo, float $saldo): ?int
{
    $falta = $minimo * 10000 - $saldo * 10000;

    return $falta > 0 ? intdiv((int) round($falta) + 9999, 10000) : null;
}

/** @return \Illuminate\Support\Collection<int, object> requisições automáticas do local */
function reqMinRequisicoes(int $biz, int $local): \Illuminate\Support\Collection
{
    return DB::table('transactions')->where('business_id', $biz)->where('location_id', $local)
        ->where('type', 'purchase_requisition')->get();
}

function reqMinQtd(int $transactionId, int $variation): ?float
{
    $q = DB::table('purchase_lines')->where('transaction_id', $transactionId)->where('variation_id', $variation)->value('quantity');

    return $q === null ? null : (float) $q;
}

it('UC-REQMIN-01 · US-COM-027 · abaixo do mínimo nasce uma requisição por local com teto(mínimo − saldo)', function () {
    $l1 = reqMinLocal($this->bizA);
    $l2 = reqMinLocal($this->bizA);
    $a = reqMinProduto($this->bizA, 10);
    $b = reqMinProduto($this->bizA, 20);
    $c = reqMinProduto($this->bizA, 5);
    $d = reqMinProduto($this->bizA, 8);
    reqMinSaldo($a, $l1, 3.5);
    reqMinSaldo($b, $l1, 0);
    reqMinSaldo($c, $l1, 12);
    reqMinSaldo($d, $l1, 8);
    reqMinSaldo($a, $l2, 9.99);

    (new RequisicaoEstoqueMinimoJob($this->bizA))->handle($this->servico);

    $r1 = reqMinRequisicoes($this->bizA, $l1);
    $r2 = reqMinRequisicoes($this->bizA, $l2);
    expect($r1)->toHaveCount(1);
    expect($r2)->toHaveCount(1);
    expect($r1[0]->status)->toBe('ordered');
    expect($r1[0]->additional_notes)->toBe(RequisicaoEstoqueMinimoService::MARCADOR);

    // caminho 1: à mão · caminho 2: reqMinEsperado
    expect(reqMinQtd($r1[0]->id, $a['variation']))->toBe(7.0)->toBe((float) reqMinEsperado(10, 3.5));
    expect(reqMinQtd($r1[0]->id, $b['variation']))->toBe(20.0)->toBe((float) reqMinEsperado(20, 0));
    expect(reqMinQtd($r1[0]->id, $c['variation']))->toBeNull()->and(reqMinEsperado(5, 12))->toBeNull();
    expect(reqMinQtd($r1[0]->id, $d['variation']))->toBeNull()->and(reqMinEsperado(8, 8))->toBeNull();
    expect(reqMinQtd($r2[0]->id, $a['variation']))->toBe(1.0)->toBe((float) reqMinEsperado(10, 9.99));
    expect(DB::table('purchase_lines')->where('transaction_id', $r1[0]->id)->count())->toBe(2);
});

it('UC-REQMIN-02 · US-COM-027 · rodar de novo não duplica e o item novo entra na mesma requisição aberta', function () {
    $l1 = reqMinLocal($this->bizA);
    $a = reqMinProduto($this->bizA, 10);
    reqMinSaldo($a, $l1, 4);

    $this->servico->gerar($this->bizA);
    $this->servico->gerar($this->bizA);

    $r = reqMinRequisicoes($this->bizA, $l1);
    expect($r)->toHaveCount(1);
    expect(DB::table('purchase_lines')->where('transaction_id', $r[0]->id)->where('variation_id', $a['variation'])->count())->toBe(1);
    expect(reqMinQtd($r[0]->id, $a['variation']))->toBe(6.0)->toBe((float) reqMinEsperado(10, 4));

    $e = reqMinProduto($this->bizA, 3);
    reqMinSaldo($e, $l1, 0.2);
    $this->servico->gerar($this->bizA);

    $r = reqMinRequisicoes($this->bizA, $l1);
    expect($r)->toHaveCount(1);
    expect(reqMinQtd($r[0]->id, $e['variation']))->toBe(3.0)->toBe((float) reqMinEsperado(3, 0.2));
});

it('UC-REQMIN-03 · US-COM-027 · item já numa requisição aberta do local não é pedido de novo', function () {
    $l1 = reqMinLocal($this->bizA);
    $a = reqMinProduto($this->bizA, 10);
    reqMinSaldo($a, $l1, 1);
    $owner = (int) DB::table('business')->where('id', $this->bizA)->value('owner_id');
    $manual = DB::table('transactions')->insertGetId([
        'business_id' => $this->bizA, 'location_id' => $l1, 'type' => 'purchase_requisition', 'status' => 'partial',
        'transaction_date' => now(), 'created_by' => $owner, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $manual, 'product_id' => $a['product'], 'variation_id' => $a['variation'],
        'quantity' => 2, 'purchase_price' => 0, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $resumo = $this->servico->gerar($this->bizA);

    expect($resumo)->toBe(['requisicoes_criadas' => 0, 'linhas' => 0]);
    expect(reqMinRequisicoes($this->bizA, $l1))->toHaveCount(1);
});

it('UC-REQMIN-04 · US-COM-027 · regra desligada (padrão) não cria requisição', function () {
    DB::table('business')->where('id', $this->bizA)->update(['common_settings' => json_encode([])]);
    expect($this->servico->ativo($this->bizA))->toBeFalse();
    $l1 = reqMinLocal($this->bizA);
    $a = reqMinProduto($this->bizA, 10);
    reqMinSaldo($a, $l1, 0);

    (new RequisicaoEstoqueMinimoJob($this->bizA))->handle($this->servico);

    expect(reqMinRequisicoes($this->bizA, $l1))->toHaveCount(0);
});

it('UC-REQMIN-05 · US-COM-027 · nenhum estoque muda e nenhum pedido de compra nasce', function () {
    $l1 = reqMinLocal($this->bizA);
    $a = reqMinProduto($this->bizA, 10);
    reqMinSaldo($a, $l1, 3.5);
    $saldoAntes = DB::table('variation_location_details')->where('variation_id', $a['variation'])->value('qty_available');
    $pedidosAntes = DB::table('transactions')->where('business_id', $this->bizA)->whereIn('type', ['purchase', 'purchase_order'])->count();

    $this->servico->gerar($this->bizA);

    expect(reqMinRequisicoes($this->bizA, $l1))->toHaveCount(1);
    expect((float) DB::table('variation_location_details')->where('variation_id', $a['variation'])->value('qty_available'))
        ->toBe((float) $saldoAntes)->toBe(3.5);
    expect(DB::table('transactions')->where('business_id', $this->bizA)->whereIn('type', ['purchase', 'purchase_order'])->count())
        ->toBe($pedidosAntes);
});

it('UC-REQMIN-06 · US-COM-027 · Tier 0: o job de um business não cria requisição no outro', function () {
    $la = reqMinLocal($this->bizA);
    $lb = reqMinLocal($this->bizB);
    $a = reqMinProduto($this->bizA, 10);
    $b = reqMinProduto($this->bizB, 10);
    reqMinSaldo($a, $la, 0);
    reqMinSaldo($b, $lb, 0);
    // saldo de produto do 99 num local do 98: nunca pode virar requisição em nenhum dos dois
    reqMinSaldo($b, $la, 0);

    (new RequisicaoEstoqueMinimoJob($this->bizA))->handle($this->servico);

    expect(reqMinRequisicoes($this->bizA, $la))->toHaveCount(1);
    expect(reqMinQtd(reqMinRequisicoes($this->bizA, $la)[0]->id, $b['variation']))->toBeNull();
    expect(reqMinRequisicoes($this->bizB, $lb))->toHaveCount(0);
});

function reqMinFornecedor(int $biz, string $nome): int
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'supplier', 'name' => $nome, 'supplier_business_name' => $nome.' LTDA',
        'contact_id' => 'CT-REQMIN-'.uniqid(), 'created_by' => $owner, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Compra do item (`purchase`) com um fornecedor; devolve o id da linha. */
function reqMinCompra(int $biz, array $p, ?int $fornecedor, string $status, string $data, float $preco, float $comImposto, float $devolvido = 0): int
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $tid = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'purchase', 'status' => $status, 'payment_status' => 'due',
        'contact_id' => $fornecedor, 'transaction_date' => $data, 'ref_no' => 'REQMIN-C-'.uniqid(),
        'final_total' => 0, 'total_before_tax' => 0, 'tax_amount' => 0, 'discount_amount' => 0,
        'created_by' => $owner, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $tid, 'product_id' => $p['product'], 'variation_id' => $p['variation'],
        'quantity' => 5, 'quantity_returned' => $devolvido, 'pp_without_discount' => $preco,
        'purchase_price' => $preco, 'purchase_price_inc_tax' => $comImposto, 'item_tax' => round($comImposto - $preco, 4),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return object{contact_id: ?int, purchase_price: string, purchase_price_inc_tax: string, item_tax: string} */
function reqMinLinha(int $biz, int $local, int $variation): ?object
{
    return DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
        ->where('t.business_id', $biz)->where('t.location_id', $local)->where('t.type', 'purchase_requisition')
        ->where('pl.variation_id', $variation)
        ->first(['t.id', 't.contact_id', 'pl.purchase_price', 'pl.purchase_price_inc_tax', 'pl.item_tax']);
}

it('UC-REQMIN-07 · US-COM-027 · a requisição sai com o fornecedor e o preço da última compra recebida do item', function () {
    $l1 = reqMinLocal($this->bizA);
    $a = reqMinProduto($this->bizA, 10);
    $b = reqMinProduto($this->bizA, 10);
    $novo = reqMinProduto($this->bizA, 10);
    reqMinSaldo($a, $l1, 2);
    reqMinSaldo($b, $l1, 2);
    reqMinSaldo($novo, $l1, 2);
    $velho = reqMinFornecedor($this->bizA, 'Fornecedor Velho');
    $atual = reqMinFornecedor($this->bizA, 'Fornecedor Atual');
    $outro = reqMinFornecedor($this->bizA, 'Fornecedor do B');

    // A: a última RECEBIDA é a do Atual (12,50 / 13,75). A posterior pendente e a posterior
    // devolvida por inteiro não contam.
    reqMinCompra($this->bizA, $a, $velho, 'received', '2026-01-10 09:00:00', 9.00, 9.90);
    $ultimaA = reqMinCompra($this->bizA, $a, $atual, 'received', '2026-02-10 09:00:00', 12.50, 13.75);
    reqMinCompra($this->bizA, $a, $velho, 'pending', '2026-03-10 09:00:00', 99.00, 99.00);
    reqMinCompra($this->bizA, $a, $velho, 'received', '2026-03-11 09:00:00', 88.00, 88.00, 5);
    $ultimaB = reqMinCompra($this->bizA, $b, $outro, 'received', '2026-02-01 09:00:00', 4.20, 4.20);

    $this->servico->gerar($this->bizA);

    $la = reqMinLinha($this->bizA, $l1, $a['variation']);
    $lb = reqMinLinha($this->bizA, $l1, $b['variation']);
    $ln = reqMinLinha($this->bizA, $l1, $novo['variation']);

    // fornecedor no cabeçalho: um por requisição — A e B em requisições diferentes
    expect((int) $la->contact_id)->toBe($atual);
    expect((int) $lb->contact_id)->toBe($outro);
    expect($ln->contact_id)->toBeNull();
    expect($la->id)->not->toBe($lb->id)->and($la->id)->not->toBe($ln->id);
    expect(reqMinRequisicoes($this->bizA, $l1))->toHaveCount(3);

    // VALOR — caminho 1: o número à mão · caminho 2: a linha da compra lida direto no banco
    $compraA = DB::table('purchase_lines')->where('id', $ultimaA)->first();
    expect((float) $la->purchase_price)->toBe(12.5)->toBe((float) $compraA->purchase_price);
    expect((float) $la->purchase_price_inc_tax)->toBe(13.75)->toBe((float) $compraA->purchase_price_inc_tax);
    expect((float) $la->item_tax)->toBe(1.25)->toBe((float) $compraA->item_tax);
    expect((float) $lb->purchase_price)->toBe(4.2)->toBe((float) DB::table('purchase_lines')->where('id', $ultimaB)->value('purchase_price'));
    expect((float) $ln->purchase_price)->toBe(0.0);
    expect((float) $ln->purchase_price_inc_tax)->toBe(0.0);

    // rodar de novo com item novo do mesmo fornecedor entra na requisição dele
    $c = reqMinProduto($this->bizA, 3);
    reqMinSaldo($c, $l1, 0);
    reqMinCompra($this->bizA, $c, $atual, 'received', '2026-02-20 09:00:00', 1.00, 1.00);
    $this->servico->gerar($this->bizA);
    expect(reqMinLinha($this->bizA, $l1, $c['variation'])->id)->toBe($la->id);
    expect(reqMinRequisicoes($this->bizA, $l1))->toHaveCount(3);
});

it('UC-REQMIN-08 · US-COM-027 · Tier 0: compra de outro business não vira fornecedor nem preço', function () {
    $la = reqMinLocal($this->bizA);
    $lb = reqMinLocal($this->bizB);
    $a = reqMinProduto($this->bizA, 10);
    reqMinSaldo($a, $la, 0);
    // compra do 99 com a variação do 98 (dado sujo): nunca pode ser lida pelo 98
    $alheio = reqMinFornecedor($this->bizB, 'Fornecedor do 99');
    reqMinCompra($this->bizB, $a, $alheio, 'received', '2026-02-10 09:00:00', 50.00, 50.00);

    $this->servico->gerar($this->bizA);

    $linha = reqMinLinha($this->bizA, $la, $a['variation']);
    expect($linha)->not->toBeNull();
    expect($linha->contact_id)->toBeNull();
    expect((float) $linha->purchase_price)->toBe(0.0);
    expect($this->servico->ultimaCompra($this->bizA, $a['variation']))->toBeNull();
    expect($this->servico->ultimaCompra($this->bizB, $a['variation']))->not->toBeNull();
    expect(reqMinRequisicoes($this->bizB, $lb))->toHaveCount(0);
});
