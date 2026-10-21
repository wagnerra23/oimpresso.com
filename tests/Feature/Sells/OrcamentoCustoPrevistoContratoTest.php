<?php
// @covers-us US-SELL-067

declare(strict_types=1);

use App\TransactionSellLine;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CustoDoInsumoService;
use Tests\Support\WithSeededTenant;

/**
 * US-SELL-067 · UC-FLX-33 — a linha do orçamento de Vendas guarda o custo do dia.
 *
 * Âncora: memory/requisitos/Sells/SPEC.md US-SELL-067 + catálogo da tela de fluxos
 * (UC-FLX-33). Números feitos à mão sobre o arranjo e conferidos pelo serviço da entrega 1
 * (CustoDoInsumoService, já coberto por Modules/Compras/Tests/Feature/CustoDoInsumoServiceTest).
 *
 * Arranjo, variação V do tenant 98 (método médio, o padrão sem regra gravada):
 *   compra recebida 01/03 · 10 un × 12,00
 *   compra recebida 10/03 · 20 un × 18,00
 *   médio em 15/03 = (120 + 360) / 30 = 16,0000
 *   compra recebida 20/03 · 30 un × 40,00  →  médio em 25/03 = (480 + 1200) / 60 = 28,0000
 *
 * O orçamento é de 15/03. A compra de 20/03 chega depois e NÃO pode mudar o custo guardado.
 */
// O TestCase já vem do tests/Pest.php (`uses(TestCase::class)->in('Feature')`).
uses(DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    DB::table('fsm_modelos_empresa')->where('business_id', $this->biz->id)->delete();

    $agora = Carbon::now();
    $sku = 'ORCCUSTO-'.uniqid();
    $this->user = User::factory()->create(['business_id' => $this->biz->id, 'username' => 'orc_custo_'.uniqid()]);
    $unitId = (int) (DB::table('units')->where('business_id', $this->biz->id)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $this->biz->id, 'actual_name' => 'Unidade '.$sku, 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
    $novoProduto = function (string $nome) use ($agora, $unitId, $sku) {
        $pid = DB::table('products')->insertGetId([
            'business_id' => $this->biz->id, 'name' => $nome.' '.$sku, 'type' => 'single', 'unit_id' => $unitId,
            'sku' => $sku.'-'.$nome, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
            'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $grupo = DB::table('product_variations')->insertGetId([
            'product_id' => $pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $vid = DB::table('variations')->insertGetId([
            'product_id' => $pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-'.$nome.'-1',
            'default_purchase_price' => 99, 'dpp_inc_tax' => 99, 'profit_percent' => 0,
            'default_sell_price' => 150, 'sell_price_inc_tax' => 150, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        return [$pid, $vid];
    };
    [$this->productId, $this->variationId] = $novoProduto('Lona');
    [$this->outroProductId, $this->outraVariationId] = $novoProduto('Vinil');

    $this->transacao = function (string $tipo, string $status, string $data, float $total = 0) use ($agora): int {
        return DB::table('transactions')->insertGetId([
            'business_id' => $this->biz->id, 'type' => $tipo, 'status' => $status, 'payment_status' => 'due',
            'transaction_date' => $data, 'ref_no' => 'ORCCUSTO-'.uniqid(), 'final_total' => $total,
            'total_before_tax' => $total, 'tax_amount' => 0, 'discount_amount' => 0,
            'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
    };
    $this->compra = function (int $variationId, int $productId, string $data, int $qtd, float $preco) use ($agora) {
        $tid = ($this->transacao)('purchase', 'received', $data, $qtd * $preco);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $tid, 'product_id' => $productId, 'variation_id' => $variationId,
            'quantity' => $qtd, 'quantity_returned' => 0, 'pp_without_discount' => $preco,
            'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
    };
    ($this->compra)($this->variationId, $this->productId, '2026-03-01 09:00:00', 10, 12);
    ($this->compra)($this->variationId, $this->productId, '2026-03-10 09:00:00', 20, 18);
    ($this->compra)($this->outraVariationId, $this->outroProductId, '2026-03-02 09:00:00', 4, 7.5);

    $this->linha = function (int $transactionId, int $variationId, int $productId): TransactionSellLine {
        return TransactionSellLine::create([
            'transaction_id' => $transactionId, 'product_id' => $productId, 'variation_id' => $variationId,
            'quantity' => 2, 'unit_price_before_discount' => 150, 'unit_price' => 150,
            'unit_price_inc_tax' => 150, 'item_tax' => 0, 'line_discount_amount' => 0,
        ]);
    };
});

it('US-SELL-067 · a linha do orçamento guarda o custo médio do dia, com data e método', function () {
    $orc = ($this->transacao)('sell', 'draft', '2026-03-15 10:00:00', 300);

    $linha = ($this->linha)($orc, $this->variationId, $this->productId)->fresh();

    // Caminho 1: conta à mão. Caminho 2: o serviço da entrega 1 na mesma data.
    expect($linha->custo_previsto)->toBe('16.0000')
        ->and(app(CustoDoInsumoService::class)->naData($this->biz->id, $this->variationId, '2026-03-15')['custo'])->toBe('16.0000')
        ->and(Carbon::parse($linha->custo_previsto_em)->toDateString())->toBe('2026-03-15')
        ->and($linha->custo_previsto_metodo)->toBe('medio');
});

it('US-SELL-067 · compra depois do orçamento não muda o custo guardado', function () {
    $orc = ($this->transacao)('sell', 'draft', '2026-03-15 10:00:00', 300);
    $linha = ($this->linha)($orc, $this->variationId, $this->productId);

    ($this->compra)($this->variationId, $this->productId, '2026-03-20 09:00:00', 30, 40);
    $linha->quantity = 3; // edição comum, sem trocar o produto
    $linha->save();

    // Sem o congelamento, o custo seria o médio de hoje: (480 + 1200) / 60 = 28,0000.
    expect(app(CustoDoInsumoService::class)->naData($this->biz->id, $this->variationId, '2026-03-25')['custo'])->toBe('28.0000')
        ->and($linha->fresh()->custo_previsto)->toBe('16.0000');
});

it('US-SELL-067 · trocar o produto da linha recalcula com o custo do novo produto', function () {
    $orc = ($this->transacao)('sell', 'draft', '2026-03-15 10:00:00', 300);
    $linha = ($this->linha)($orc, $this->variationId, $this->productId);

    $linha->variation_id = $this->outraVariationId;
    $linha->product_id = $this->outroProductId;
    $linha->save();

    expect($linha->fresh()->custo_previsto)->toBe('7.5000');
});

it('US-SELL-067 · venda finalizada não grava custo previsto (o real vem do lote)', function () {
    $venda = ($this->transacao)('sell', 'final', '2026-03-15 10:00:00', 300);

    expect(($this->linha)($venda, $this->variationId, $this->productId)->fresh()->custo_previsto)->toBeNull();
});

it('US-SELL-067 · converter o orçamento em venda mantém o custo do dia', function () {
    $orc = ($this->transacao)('sell', 'draft', '2026-03-15 10:00:00', 300);
    $linha = ($this->linha)($orc, $this->variationId, $this->productId);

    ($this->compra)($this->variationId, $this->productId, '2026-03-20 09:00:00', 30, 40);
    DB::table('transactions')->where('id', $orc)->update(['status' => 'final', 'transaction_date' => '2026-03-25 10:00:00']);
    $linha->quantity = 2.5;
    $linha->save();

    expect($linha->fresh()->custo_previsto)->toBe('16.0000');
});

it('US-SELL-067 · sem compra até a data a linha fica sem custo e o orçamento é gravado', function () {
    $orc = ($this->transacao)('sell', 'draft', '2026-02-01 10:00:00', 300);

    $linha = ($this->linha)($orc, $this->variationId, $this->productId)->fresh();

    expect($linha->exists)->toBeTrue()->and($linha->custo_previsto)->toBeNull();
});

it('US-SELL-067 · falha no cálculo não derruba a gravação da linha', function () {
    $this->app->bind(CustoDoInsumoService::class, fn () => throw new RuntimeException('falha simulada'));
    $orc = ($this->transacao)('sell', 'draft', '2026-03-15 10:00:00', 300);

    $linha = ($this->linha)($orc, $this->variationId, $this->productId)->fresh();

    expect($linha->exists)->toBeTrue()->and($linha->custo_previsto)->toBeNull();
});
