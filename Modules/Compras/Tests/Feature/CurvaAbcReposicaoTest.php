<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CurvaAbcReposicao;
use Tests\Support\EstoqueFixture;

/**
 * US-ESTOQUE-003 — curva ABC por faturamento e por margem, e sugestão de reposição pelo giro.
 *
 * Contrato: memory/requisitos/Estoque/SPEC.md US-ESTOQUE-003 + docblock de CurvaAbcReposicao.
 * Só leitura. Esperados escritos À MÃO (caminho A); o serviço lê das tabelas (caminho B); a
 * sugestão é refeita pela fórmula pura `CurvaAbcReposicao::sugestao` (caminho C).
 *
 * Cenário, período de 90 dias até 2026-11-02 (giro = vendido / 90):
 *   Lona    vendeu 90 × 8,00 = 720,00 · custo do lote 5,00 → 450,00 · margem 270,00
 *   Vinil   vendeu 30 × 6,00 = 180,00 · custo do lote 4,00 → 120,00 · margem  60,00
 *   Ilhós   vendeu 50 × 2,00 = 100,00 · sem lote amarrado → margem nula
 *   total 1.000,00 → Lona antes 0% (A) · Vinil antes 72% (A) · Ilhós antes 90% (B)
 *   margem: Lona 270 (A, antes 0%) · Vinil 60 (B, antes 81,8%) · Ilhós nula (C)
 *   Venda fora do período (120 dias antes) e venda de outra empresa não entram.
 *
 * Reposição da Lona: giro 1/dia · prazo do fornecedor 10 dias · cobertura 30 · estoque 25 · compra
 *   pedida a caminho 5 → 1 × (10 + 30) − 25 − 5 = 10.
 * Vinil: giro 0,3333/dia · sem prazo (0) · estoque 20 → 0,3333 × 30 − 20 < 0 → 0.
 *
 * Tenant 98 (ADR 0358); adversário = segundo business do seed, nunca 1 nem 4.
 *
 * @covers-us US-ESTOQUE-003
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('compras_prazo_fornecedor')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->ate = Carbon::parse('2026-11-02');
    $this->loc = EstoqueFixture::locationId($this->biz, '-ABC');
    $uid = EstoqueFixture::userId($this->biz);
    $this->fornecedor = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz, 'type' => 'supplier', 'name' => 'Fornecedor ABC',
        'contact_id' => 'CT-ABC-'.uniqid(), 'created_by' => $uid, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->lona = EstoqueFixture::singleProduct($this->biz);
    $this->vinil = EstoqueFixture::singleProduct($this->biz);
    $this->ilhos = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($this->lona, 0, $this->loc, 25);
    EstoqueFixture::setStock($this->vinil, 0, $this->loc, 20);

    $tx = fn (string $type, string $status, string $data, ?int $biz = null) => DB::table('transactions')->insertGetId([
        'business_id' => $biz ?? $this->biz, 'location_id' => $this->loc, 'contact_id' => $this->fornecedor,
        'type' => $type, 'status' => $status, 'payment_status' => 'due', 'transaction_date' => $data,
        'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $uid, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $compra = function ($p, string $status, float $qtd, float $preco, string $data) use ($tx): int {
        $id = $tx('purchase', $status, $data);

        return (int) DB::table('purchase_lines')->insertGetId([
            'transaction_id' => $id, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
            'quantity' => $qtd, 'quantity_sold' => 0, 'quantity_returned' => 0, 'pp_without_discount' => $preco,
            'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $venda = function ($p, float $qtd, float $preco, string $data, ?int $lote = null, ?int $biz = null) use ($tx): void {
        $id = $tx('sell', 'final', $data, $biz);
        $linha = DB::table('transaction_sell_lines')->insertGetId([
            'transaction_id' => $id, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
            'quantity' => $qtd, 'quantity_returned' => 0, 'unit_price' => $preco, 'unit_price_inc_tax' => $preco,
            'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($lote !== null) {
            DB::table('transaction_sell_lines_purchase_lines')->insert([
                'sell_line_id' => $linha, 'purchase_line_id' => $lote, 'quantity' => $qtd, 'qty_returned' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    };

    $loteLona = $compra($this->lona, 'received', 200, 5.00, '2026-07-01 08:00:00');
    $loteVinil = $compra($this->vinil, 'received', 100, 4.00, '2026-07-01 08:00:00');
    $compra($this->lona, 'ordered', 5, 5.00, '2026-11-01 08:00:00');
    DB::table('compras_prazo_fornecedor')->insert([
        'business_id' => $this->biz, 'product_id' => $this->lona->productId, 'contact_id' => $this->fornecedor,
        'dias' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $venda($this->lona, 90, 8.00, '2026-10-20 10:00:00', $loteLona);
    $venda($this->vinil, 30, 6.00, '2026-10-21 10:00:00', $loteVinil);
    $venda($this->ilhos, 50, 2.00, '2026-10-22 10:00:00');
    $venda($this->lona, 999, 8.00, '2026-07-05 10:00:00', $loteLona); // fora do período

    $this->resultado = fn () => app(CurvaAbcReposicao::class)->calcular($this->biz, null, $this->ate, 30);
    $this->item = fn (array $r, $p) => collect($r['itens'])->firstWhere('variation_id', $p->variations[0]['variation_id']);
});

it('UC-ABC-01 · classifica por faturamento: A até 80% acumulado antes do item, B até 95%, C o resto', function () {
    $r = ($this->resultado)();

    expect($r['dias'])->toBe(90)
        ->and(($this->item)($r, $this->lona))->toMatchArray(['vendido' => 90.0, 'faturamento' => 720.0, 'curva' => 'A'])
        ->and(($this->item)($r, $this->vinil))->toMatchArray(['faturamento' => 180.0, 'curva' => 'A'])
        ->and(($this->item)($r, $this->ilhos))->toMatchArray(['faturamento' => 100.0, 'curva' => 'B'])
        ->and($r['totais']['faturamento'])->toBe(1000.0)
        ->and(collect($r['itens'])->pluck('variation_id')->all())->toBe([
            $this->lona->variations[0]['variation_id'], $this->vinil->variations[0]['variation_id'], $this->ilhos->variations[0]['variation_id'],
        ]);
});

it('UC-ABC-02 · margem pelo custo do lote; venda sem lote fica com margem nula e C na curva de margem', function () {
    $r = ($this->resultado)();

    expect(($this->item)($r, $this->lona))->toMatchArray(['custo' => 450.0, 'margem' => 270.0, 'margem_pct' => 37.5, 'curva_margem' => 'A'])
        ->and(($this->item)($r, $this->vinil))->toMatchArray(['custo' => 120.0, 'margem' => 60.0, 'curva_margem' => 'B'])
        ->and(($this->item)($r, $this->ilhos))->toMatchArray(['custo' => null, 'custo_completo' => false, 'margem' => null, 'curva_margem' => 'C']);
});

it('UC-ABC-03 · sugere comprar pelo giro, prazo do fornecedor e o que já está a caminho', function () {
    $r = ($this->resultado)();
    $lona = ($this->item)($r, $this->lona);

    expect(CurvaAbcReposicao::sugestao(1.0, 10, 30, 25, 5))->toBe(10.0)
        ->and($lona)->toMatchArray(['giro_dia' => 1.0, 'estoque' => 25.0, 'a_caminho' => 5.0, 'prazo_dias' => 10, 'cobertura_dias' => 25.0, 'sugestao' => 10.0])
        ->and(($this->item)($r, $this->vinil))->toMatchArray(['prazo_dias' => null, 'sugestao' => 0.0])
        ->and($r['totais']['a_repor'])->toBeGreaterThanOrEqual(1);
});

it('UC-ABC-04 · Tier 0: venda e estoque de outra empresa não entram', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null) {
        $this->markTestSkipped('Seed sem segundo business.');
    }
    $id = DB::table('transactions')->insertGetId([
        'business_id' => $outra, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'transaction_date' => '2026-10-25 10:00:00', 'final_total' => 0, 'total_before_tax' => 0,
        'created_by' => EstoqueFixture::userId($this->biz), 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $id, 'product_id' => $this->lona->productId, 'variation_id' => $this->lona->variations[0]['variation_id'],
        'quantity' => 500, 'quantity_returned' => 0, 'unit_price' => 8, 'unit_price_inc_tax' => 8, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = ($this->resultado)();

    expect(($this->item)($r, $this->lona)['vendido'])->toBe(90.0)
        ->and(collect(app(CurvaAbcReposicao::class)->calcular($outra, null, $this->ate)['itens'])
            ->where('variation_id', $this->lona->variations[0]['variation_id'])->count())->toBe(0);
});

it('UC-ABC-05 · só lê: nada muda em estoque, custo ou preço', function () {
    $antes = [DB::table('variation_location_details')->sum('qty_available'), DB::table('variations')->sum('dpp_inc_tax')];
    ($this->resultado)();

    expect([DB::table('variation_location_details')->sum('qty_available'), DB::table('variations')->sum('dpp_inc_tax')])->toBe($antes);
});
