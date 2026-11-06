<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CotacaoDeCompra;
use Modules\Compras\Services\CurvaAbcReposicao;
use Tests\Support\EstoqueFixture;

/**
 * Isolamento por empresa de `compras_prazo_fornecedor` e `compras_cotacao_fornecedores`
 * (ADR 0093 Tier 0). Achado D9 da auditoria de Compras 2026-11-05: nenhum teste exercitava
 * essas duas tabelas com uma empresa adversária.
 *
 * Contrato:
 *  - prazo do fornecedor é da empresa que o cadastrou (SPEC Estoque US-ESTOQUE-003; docblock de
 *    CurvaAbcReposicao: "prazo = dias do fornecedor da última compra; sem cadastro, 0"). Um prazo
 *    gravado por OUTRA empresa para o mesmo produto e fornecedor não entra na sugestão.
 *  - o convite da cotação pertence à empresa da cotação (SPEC Compras US-COM-031). Outra empresa
 *    não responde, nem com um fornecedor convidado, e nada é gravado.
 *
 * Tenant 98 (ADR 0358); adversário o cliente fictício 99, criado aqui quando falta. Sem ele o
 * teste falha; não pula.
 *
 * @covers-us US-ESTOQUE-003
 * @covers-us US-COM-031
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()
        || ! \Schema::hasTable('compras_prazo_fornecedor')
        || ! \Schema::hasTable('compras_cotacao_fornecedores')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->outra = (int) $this->seededSupportClientTenant()->id;
    expect($this->outra)->toBe(99, 'adversário fictício 99 não foi criado');
    expect($this->biz)->not->toBe($this->outra);

    $this->uid = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, '-ISO');
    $this->fornecedor = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz, 'type' => 'supplier', 'name' => 'Fornecedor ISO',
        'contact_id' => 'CT-ISO-'.uniqid(), 'created_by' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->lona = EstoqueFixture::singleProduct($this->biz);
});

/** Prazo cadastrado (dias) para a lona e o fornecedor do tenant 98, lido pela Curva ABC. */
function isoPrazoDaLona($t): ?int
{
    $r = app(CurvaAbcReposicao::class)->calcular($t->biz, null, Carbon::parse('2026-11-02'), 30);
    $item = collect($r['itens'])->firstWhere('variation_id', $t->lona->variationId());
    expect($item)->not->toBeNull('a lona vendida no período deveria aparecer na curva');

    return $item['prazo_dias'];
}

function isoPrazo(int $biz, $t, int $dias): void
{
    DB::table('compras_prazo_fornecedor')->insert([
        'business_id' => $biz, 'product_id' => $t->lona->productId, 'contact_id' => $t->fornecedor,
        'dias' => $dias, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('prazo de fornecedor gravado por outra empresa não entra na sugestão; o da própria empresa entra', function () {
    // Última compra recebida da lona veio do fornecedor (é ele que o serviço procura no prazo).
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'contact_id' => $this->fornecedor,
        'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due', 'transaction_date' => '2026-07-01 08:00:00',
        'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $this->uid, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $this->lona->productId, 'variation_id' => $this->lona->variationId(),
        'quantity' => 100, 'quantity_sold' => 0, 'quantity_returned' => 0, 'pp_without_discount' => 5,
        'purchase_price' => 5, 'purchase_price_inc_tax' => 5, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'contact_id' => $this->fornecedor, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => '2026-10-20 10:00:00', 'final_total' => 0, 'total_before_tax' => 0,
        'created_by' => $this->uid, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $venda, 'product_id' => $this->lona->productId, 'variation_id' => $this->lona->variationId(),
        'quantity' => 9, 'quantity_returned' => 0, 'unit_price' => 8, 'unit_price_inc_tax' => 8, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Só a outra empresa tem prazo para este produto + fornecedor: o tenant 98 não o vê.
    isoPrazo($this->outra, $this, 45);
    expect(isoPrazoDaLona($this))->toBeNull();

    // Controle positivo: com o prazo da própria empresa, é ele (7) que vale — nunca o 45 alheio.
    isoPrazo($this->biz, $this, 7);
    expect(isoPrazoDaLona($this))->toBe(7);

    // A linha da outra empresa continua lá, intacta.
    expect((int) DB::table('compras_prazo_fornecedor')->where('business_id', $this->outra)
        ->where('product_id', $this->lona->productId)->value('dias'))->toBe(45);
});

it('convite da cotação fica na empresa dela; outra empresa não responde nem com fornecedor convidado', function () {
    $svc = app(CotacaoDeCompra::class);
    $id = $svc->criar($this->biz, $this->loc, [['variation_id' => $this->lona->variationId(), 'quantidade' => '10']], [$this->fornecedor], $this->uid);

    // Efeito no banco: o convite foi gravado com a empresa da cotação, e só com ela.
    $convites = DB::table('compras_cotacao_fornecedores')->where('cotacao_id', $id)->get(['business_id', 'contact_id']);
    expect($convites)->toHaveCount(1);
    expect((int) $convites[0]->business_id)->toBe($this->biz);
    expect((int) $convites[0]->contact_id)->toBe((int) $this->fornecedor);

    $item = (int) DB::table('compras_cotacao_itens')->where('cotacao_id', $id)->value('id');

    // A outra empresa tenta responder pela cotação do tenant 98, usando o fornecedor convidado.
    expect(fn () => $svc->responder($this->outra, $id, $this->fornecedor, [$item => ['preco_unitario' => '1.00', 'prazo_dias' => 1]]))
        ->toThrow(InvalidArgumentException::class, 'Cotação inválida para este negócio.');
    expect(fn () => $svc->comparar($this->outra, $id))
        ->toThrow(InvalidArgumentException::class, 'Cotação inválida para este negócio.');

    // Nada foi gravado: nenhuma resposta na cotação e nenhum convite em nome da outra empresa.
    expect(DB::table('compras_cotacao_respostas')->where('cotacao_id', $id)->count())->toBe(0);
    expect(DB::table('compras_cotacao_fornecedores')->where('business_id', $this->outra)->where('cotacao_id', $id)->count())->toBe(0);

    // Controle positivo: a própria empresa responde, e a resposta sai com o business dela.
    $svc->responder($this->biz, $id, $this->fornecedor, [$item => ['preco_unitario' => '9.50', 'prazo_dias' => 4]]);
    $resp = DB::table('compras_cotacao_respostas')->where('cotacao_id', $id)->first(['business_id', 'preco_unitario']);
    expect((int) $resp->business_id)->toBe($this->biz);
    expect((float) $resp->preco_unitario)->toBe(9.5);
});
