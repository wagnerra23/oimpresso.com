<?php
// @covers-us US-VEST-031

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\CurvaDeCompra;
use Tests\Support\WithSeededTenant;

/**
 * US-VEST-031 — sugestão de compra por tamanho pela curva de venda (UC-VCUR-01..03).
 *
 * Âncora: decisão [W] 2026-10-26 registrada no SPEC — "na compra, sugerir a quantidade por tamanho
 * pela proporção vendida nos últimos 90 dias do produto, ou da categoria quando o produto é novo;
 * quem compra pode mudar; nada entra no estoque nem no pedido sem confirmação".
 * Tenant 98 contra 99 (ADR 0358). NUNCA biz=4 (ROTA LIVRE, cliente real que usa o Vestuário).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('transaction_sell_lines')) {
        $this->markTestSkipped('Requer MySQL com o schema do UltimatePOS.');
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->hoje = Carbon::parse('2026-10-21 12:00:00');
    $this->dono = fn (int $biz) => (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $this->categoria = fn (int $biz) => (int) DB::table('categories')->insertGetId([
        'name' => 'Camisetas ' . uniqid(), 'business_id' => $biz, 'short_code' => 'CAM', 'parent_id' => 0,
        'created_by' => ($this->dono)($biz), 'category_type' => 'product', 'created_at' => now(), 'updated_at' => now(),
    ]);
    // produto com grade: devolve [product_id, [nome => variation_id]]
    $this->produto = function (int $biz, ?int $categoria, array $grade): array {
        $suf = random_int(100000, 999999);
        $unit = DB::table('units')->insertGetId(['business_id' => $biz, 'actual_name' => 'Peça', 'short_name' => 'pc', 'allow_decimal' => 0,
            'created_by' => ($this->dono)($biz), 'created_at' => now(), 'updated_at' => now()]);
        $prod = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => 'Camiseta ' . $suf, 'type' => 'variable', 'unit_id' => $unit,
            'category_id' => $categoria, 'tax_type' => 'exclusive', 'sku' => 'CUR-' . $suf, 'barcode_type' => 'C128', 'enable_stock' => 1,
            'alert_quantity' => 0, 'created_by' => ($this->dono)($biz), 'created_at' => now(), 'updated_at' => now()]);
        $pv = DB::table('product_variations')->insertGetId(['product_id' => $prod, 'name' => 'Tamanho', 'is_dummy' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $ids = [];
        foreach ($grade as $i => $nome) {
            $ids[$nome] = DB::table('variations')->insertGetId(['name' => $nome, 'product_id' => $prod, 'product_variation_id' => $pv,
                'sub_sku' => 'CUR-' . $suf . '-' . $i, 'default_purchase_price' => 10, 'dpp_inc_tax' => 10, 'profit_percent' => 0,
                'default_sell_price' => 20, 'sell_price_inc_tax' => 20, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$prod, $ids];
    };
    // venda com uma linha
    $this->venda = function (int $biz, int $prod, int $var, float $qtd, string $quando, string $status = 'final', float $devolvida = 0): void {
        $tx = DB::table('transactions')->insertGetId(['business_id' => $biz, 'type' => 'sell', 'status' => $status, 'payment_status' => 'paid',
            'invoice_no' => 'CUR-' . uniqid(), 'transaction_date' => $quando, 'total_before_tax' => 20 * $qtd, 'final_total' => 20 * $qtd,
            'created_by' => ($this->dono)($biz), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('transaction_sell_lines')->insert(['transaction_id' => $tx, 'product_id' => $prod, 'variation_id' => $var,
            'quantity' => $qtd, 'quantity_returned' => $devolvida, 'unit_price' => 20, 'unit_price_inc_tax' => 20,
            'created_at' => now(), 'updated_at' => now()]);
    };

    $this->cat = ($this->categoria)($this->biz);
    [$this->a, $va] = ($this->produto)($this->biz, $this->cat, ['P', 'M', 'G']);
    $this->va = $va;
    ($this->venda)($this->biz, $this->a, $va['P'], 2, '2026-10-10 10:00:00');
    ($this->venda)($this->biz, $this->a, $va['M'], 6, '2026-09-30 10:00:00');
    ($this->venda)($this->biz, $this->a, $va['G'], 2, '2026-08-01 10:00:00');
    // não contam: devolvida inteira, fora dos 90 dias, rascunho
    ($this->venda)($this->biz, $this->a, $va['M'], 1, '2026-10-15 10:00:00', 'final', 1);
    ($this->venda)($this->biz, $this->a, $va['G'], 10, '2026-07-01 10:00:00');
    ($this->venda)($this->biz, $this->a, $va['P'], 30, '2026-10-12 10:00:00', 'draft');
});

/** Segundo caminho: maior resto, sem banco (empate: mais vendida, depois a ordem). */
function curvaEsperada(array $vendidas, int $total): array
{
    $soma = array_sum($vendidas);
    $base = array_map(fn ($q) => (int) floor($total * $q / $soma), $vendidas);
    $restos = array_map(fn ($q) => $total * $q / $soma - floor($total * $q / $soma), $vendidas);
    $ordem = array_keys($vendidas);
    usort($ordem, fn ($x, $y) => [$restos[$y], $vendidas[$y], array_search($x, array_keys($vendidas))] <=> [$restos[$x], $vendidas[$x], array_search($y, array_keys($vendidas))]);
    foreach (array_slice($ordem, 0, $total - array_sum($base)) as $k) {
        $base[$k]++;
    }

    return $base;
}

it('UC-VCUR-01 · divide o total pela proporção vendida do produto nos últimos 90 dias', function () {
    $r = app(CurvaDeCompra::class)->sugerir($this->biz, $this->a, 20, $this->hoje);

    // vendidas no período: P 2 · M 6 · G 2 (a devolvida, a de 112 dias e o rascunho não contam)
    expect($r['base'])->toBe('produto')
        ->and(array_column($r['linhas'], 'vendidas', 'nome'))->toBe(['P' => 2.0, 'M' => 6.0, 'G' => 2.0])
        ->and(array_column($r['linhas'], 'proporcao_pct', 'nome'))->toBe(['P' => 20.0, 'M' => 60.0, 'G' => 20.0])
        ->and(array_column($r['linhas'], 'sugerida', 'nome'))->toBe(['P' => 4, 'M' => 12, 'G' => 4]);
});

it('UC-VCUR-01 · a soma sempre bate com o total (maior resto)', function () {
    $r = app(CurvaDeCompra::class)->sugerir($this->biz, $this->a, 7, $this->hoje);

    // conta à mão: 1,4 · 4,2 · 1,4 → 1 · 4 · 1 = 6; a peça que falta vai para P (empate com G, P vem antes)
    expect(curvaEsperada(['P' => 2, 'M' => 6, 'G' => 2], 7))->toBe(['P' => 2, 'M' => 4, 'G' => 1]);
    expect(array_column($r['linhas'], 'sugerida', 'nome'))->toBe(['P' => 2, 'M' => 4, 'G' => 1])
        ->and(array_sum(array_column($r['linhas'], 'sugerida')))->toBe(7);
});

it('UC-VCUR-02 · produto novo usa a curva da categoria, casando a variação pelo nome', function () {
    [$novo] = ($this->produto)($this->biz, $this->cat, ['P', 'M', 'G', 'GG']);
    $r = app(CurvaDeCompra::class)->sugerir($this->biz, $novo, 10, $this->hoje);

    expect($r['base'])->toBe('categoria')
        ->and(array_column($r['linhas'], 'vendidas', 'nome'))->toBe(['P' => 2.0, 'M' => 6.0, 'G' => 2.0, 'GG' => 0.0])
        ->and(array_column($r['linhas'], 'sugerida', 'nome'))->toBe(['P' => 2, 'M' => 6, 'G' => 2, 'GG' => 0]);
});

it('UC-VCUR-03 · sem venda no produto nem na categoria não sugere, e diz por quê', function () {
    [$semCategoria] = ($this->produto)($this->biz, null, ['P', 'M']);
    [$categoriaVazia] = ($this->produto)($this->biz, ($this->categoria)($this->biz), ['P', 'M']);

    $r1 = app(CurvaDeCompra::class)->sugerir($this->biz, $semCategoria, 10, $this->hoje);
    $r2 = app(CurvaDeCompra::class)->sugerir($this->biz, $categoriaVazia, 10, $this->hoje);
    expect($r1['motivo'])->toBe('sem_venda_no_produto')->and($r1['linhas'])->toBe([])
        ->and($r2['motivo'])->toBe('sem_venda_no_produto_nem_na_categoria')->and($r2['linhas'])->toBe([]);
});

it('UC-VCUR-03 · produto e vendas de outra empresa não entram [T0]', function () {
    // a 99 vende muito M do produto da 98 (linha forjada) e tem produto próprio
    $tx = DB::table('transactions')->insertGetId(['business_id' => $this->outra, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'invoice_no' => 'CUR-' . uniqid(), 'transaction_date' => '2026-10-10 10:00:00', 'total_before_tax' => 1000, 'final_total' => 1000,
        'created_by' => ($this->dono)($this->outra), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('transaction_sell_lines')->insert(['transaction_id' => $tx, 'product_id' => $this->a, 'variation_id' => $this->va['M'],
        'quantity' => 50, 'quantity_returned' => 0, 'unit_price' => 20, 'unit_price_inc_tax' => 20, 'created_at' => now(), 'updated_at' => now()]);
    [$daOutra] = ($this->produto)($this->outra, null, ['P']);

    expect(array_column(app(CurvaDeCompra::class)->sugerir($this->biz, $this->a, 20, $this->hoje)['linhas'], 'sugerida', 'nome'))
        ->toBe(['P' => 4, 'M' => 12, 'G' => 4]);
    expect(app(CurvaDeCompra::class)->sugerir($this->biz, $daOutra, 20, $this->hoje)['motivo'])->toBe('produto_nao_encontrado');
});

it('UC-VCUR-03 · sugerir não grava nada (pedido e estoque ficam como estavam)', function () {
    $antes = [DB::table('transactions')->count(), DB::table('purchase_lines')->count(), DB::table('variation_location_details')->count()];
    app(CurvaDeCompra::class)->sugerir($this->biz, $this->a, 20, $this->hoje);

    expect([DB::table('transactions')->count(), DB::table('purchase_lines')->count(), DB::table('variation_location_details')->count()])->toBe($antes);
});
