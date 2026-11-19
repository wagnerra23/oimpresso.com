<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\AplicarCustoDoRecebimento;
use Modules\Compras\Services\PrecoPelaPolitica;
use Tests\Support\WithSeededTenant;

/**
 * CU-PROD-21 — o recebimento da compra segue a política de preço do produto (UC-PCAD-23 · UC-PEDIT-20).
 *
 * Âncora: SDD-tela-cadastro-produto-v1.0.md §CU-PROD-21 (decisão do [F] em 2026-11-16: a compra segue
 * a regra do administrador em Fluxos, `regraEm`, sem migrar os produtos existentes) e a conta do
 * protótipo do [W] (`fsm-regras.jsx`, `calcular`, `up10`). Liga por chave por empresa
 * (`compraSeguePoliticaPreco`), desligada por padrão: com ela desligada nada muda.
 *
 * REGRA MESTRE de valor: cada número vem de uma conta à mão (abaixo) e é refeito por um segundo
 * caminho em PHP (politicaEsperada), sem passar pelo serviço.
 *
 * Arranjo igual ao do AplicarCustoDoRecebimentoTest — variação V do tenant 98, imposto de 10%:
 *   antes: custo 10,0000 sem imposto / 11,0000 com · preço 15,0000 / 16,5000 · margem 50%
 *   A 2026-03-01 recebida 10 × 11,00 · B 2026-03-10 recebida 10 × 13,20 (a que está sendo recebida)
 *   custo médio novo = 12,1000 com imposto · 11,0000 sem
 *
 *   fixo       → preço igual (16,5000); margem (15 − 11) / 11 = 36,3636; sem proposta
 *   acompanha  → 16,5 × 12,1 ÷ 11 = 18,15 → up10 = 18,2000 com imposto
 *                sem imposto = 18,2 × 100 / 110 = 16,5455 · margem (16,5455 − 11) / 11 = 50,4136
 *   margem 40  → 11 ÷ 0,6 = 18,3333... × 1,1 = 20,1666... → up10 = 20,2000 com imposto
 *   chave desligada (ou regraEm ≠ produto) → como hoje: aplicar = 11 × 1,5 × 1,1 = 18,1500
 *
 * Tenant canônico 98 (ADR 0358). NUNCA biz=4.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode no MySQL real (lane compras-pest ou CT 100).');
    }

    $this->user = User::factory()->create(['business_id' => $this->biz->id, 'username' => 'politica_receb_'.uniqid()]);
    $agora = Carbon::now();
    $sku = 'POLREC-'.uniqid();

    $unitId = (int) (DB::table('units')->where('business_id', $this->biz->id)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $this->biz->id, 'actual_name' => 'Unidade '.$sku, 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
    $taxId = DB::table('tax_rates')->insertGetId([
        'business_id' => $this->biz->id, 'name' => 'Imposto 10 '.$sku, 'amount' => 10, 'is_tax_group' => 0,
        'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $this->productId = DB::table('products')->insertGetId([
        'business_id' => $this->biz->id, 'name' => 'Lona '.$sku, 'type' => 'single', 'unit_id' => $unitId,
        'tax' => $taxId, 'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
        'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $this->productId, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $this->variationId = DB::table('variations')->insertGetId([
        'default_purchase_price' => 10, 'dpp_inc_tax' => 11, 'profit_percent' => 50, 'default_sell_price' => 15, 'sell_price_inc_tax' => 16.5,
        'product_id' => $this->productId, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $this->compra = function (string $data, float $qtd, float $preco): Transaction {
        $tid = DB::table('transactions')->insertGetId([
            'business_id' => $this->biz->id, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
            'transaction_date' => $data, 'ref_no' => 'POLREC-'.uniqid(), 'final_total' => $qtd * $preco,
            'total_before_tax' => $qtd * $preco, 'tax_amount' => 0, 'discount_amount' => 0,
            'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $tid, 'product_id' => $this->productId, 'variation_id' => $this->variationId,
            'quantity' => $qtd, 'quantity_returned' => 0, 'pp_without_discount' => $preco,
            'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Transaction::withoutGlobalScopes()->findOrFail($tid); // SUPERADMIN: fixture de teste lida por id
    };
    ($this->compra)('2026-03-01 09:00:00', 10, 11.00);

    $this->regra = function (string $preco, string $regraEm = 'produto'): void {
        DB::table('fsm_modelos_empresa')->where('business_id', $this->biz->id)->delete();
        DB::table('fsm_modelos_empresa')->insert([
            'business_id' => $this->biz->id, 'modelo' => 'legado', 'created_at' => now(), 'updated_at' => now(),
            'regras_compra' => json_encode(['insumo.custo' => ['ativo' => true, 'params' => [
                'etapa' => 'recebido', 'metodo' => 'medio', 'preco' => $preco, 'regraEm' => $regraEm,
            ]]]),
        ]);
    };
    $this->politica = fn (string $p, ?float $alvo = null) => DB::table('products')->where('id', $this->productId)
        ->update(['politica_preco' => $p, 'margem_alvo_pct' => $alvo]);
    $this->chave = fn (bool $ligada) => config(['feature-flags.forced_on' => $ligada ? AplicarCustoDoRecebimento::CHAVE_POLITICA : '']);
    $this->receber = fn () => app(AplicarCustoDoRecebimento::class)->aoReceber(($this->compra)('2026-03-10 09:00:00', 10, 13.20));
    $this->variacao = fn () => (array) DB::table('variations')->where('id', $this->variationId)
        ->first(['sell_price_inc_tax', 'default_sell_price', 'profit_percent', 'dpp_inc_tax']);
    $this->propostas = fn () => DB::table('compras_propostas_preco')->where('variation_id', $this->variationId)->pluck('preco_proposto')->map(fn ($p) => (string) $p)->all();
});

/** Segundo caminho: a conta do protótipo refeita aqui, sem o serviço. Custo 11 → 12,1 com imposto de 10%. */
function politicaEsperada(string $politica, ?float $alvo = null): array
{
    $cA = 11.0; $cN = 12.1; $P = 16.5; $cNexc = 11.0;
    $novo = match ($politica) {
        'acompanha' => ceil($P * $cN / $cA * 10 - 1e-9) / 10,
        'margem' => ceil($cNexc / (1 - $alvo / 100) * 1.1 * 10 - 1e-9) / 10,
        default => $P,
    };
    $exc = round($novo / 1.1, 4);
    $f = fn (float $n) => number_format($n, 4, '.', '');

    return ['sell_price_inc_tax' => $f($novo), 'default_sell_price' => $f($exc), 'profit_percent' => $f(round(($exc - $cNexc) / $cNexc * 100, 4))];
}

it('CU-PROD-21 · a conta da política, isolada: up10 e os casos em que o preço não muda', function () {
    expect(PrecoPelaPolitica::up10(18.15))->toBe(18.2)
        ->and(PrecoPelaPolitica::up10(12.3))->toBe(12.3)
        ->and(PrecoPelaPolitica::up10(0.1 + 0.2))->toBe(0.3)
        ->and(PrecoPelaPolitica::novo('acompanha', 16.5, 11.0, 12.1, 11.0, 10.0, null))->toBe(18.2)
        ->and(PrecoPelaPolitica::novo('margem', 16.5, 11.0, 12.1, 11.0, 10.0, 40.0))->toBe(20.2)
        ->and(PrecoPelaPolitica::novo('fixo', 16.5, 11.0, 12.1, 11.0, 10.0, null))->toBeNull()
        ->and(PrecoPelaPolitica::novo('acompanha', 16.5, 0.0, 12.1, 11.0, 10.0, null))->toBeNull()
        ->and(PrecoPelaPolitica::novo('margem', 16.5, 11.0, 12.1, 11.0, 10.0, 100.0))->toBeNull()
        ->and(PrecoPelaPolitica::novo('margem', 16.5, 11.0, 12.1, 11.0, 10.0, null))->toBeNull();
});

it('CU-PROD-21 · chave desligada: nada muda, o recebimento aplica como hoje mesmo com política acompanha', function () {
    ($this->regra)('aplicar');
    ($this->politica)('acompanha');
    ($this->chave)(false);

    ($this->receber)();

    expect(($this->variacao)())->toMatchArray(['sell_price_inc_tax' => '18.1500', 'default_sell_price' => '16.5000', 'profit_percent' => '50.0000']);
});

it('CU-PROD-21 · chave ligada e preço fixo: o preço não muda, só a margem, e não há proposta', function () {
    ($this->regra)('aplicar');
    ($this->politica)('fixo');
    ($this->chave)(true);

    ($this->receber)();

    $esperado = politicaEsperada('fixo');
    expect($esperado['sell_price_inc_tax'])->toBe('16.5000');
    expect(($this->variacao)())->toMatchArray(['sell_price_inc_tax' => '16.5000', 'default_sell_price' => '15.0000', 'profit_percent' => '36.3636', 'dpp_inc_tax' => '12.1000']);
    expect(($this->propostas)())->toBe([]);
});

it('CU-PROD-21 · chave ligada e acompanha o custo: aplicar troca o preço por up10(P × cN ÷ cA)', function () {
    ($this->regra)('aplicar');
    ($this->politica)('acompanha');
    ($this->chave)(true);

    ($this->receber)();

    $esperado = politicaEsperada('acompanha');
    expect($esperado)->toBe(['sell_price_inc_tax' => '18.2000', 'default_sell_price' => '16.5455', 'profit_percent' => '50.4136']);
    expect(($this->variacao)())->toMatchArray($esperado);
});

it('CU-PROD-21 · chave ligada e margem alvo 40%: propor guarda up10(custo ÷ 0,6, com imposto) e o preço fica', function () {
    ($this->regra)('propor');
    ($this->politica)('margem', 40.0);
    ($this->chave)(true);

    ($this->receber)();

    expect(politicaEsperada('margem', 40.0)['sell_price_inc_tax'])->toBe('20.2000');
    expect(($this->propostas)())->toBe(['20.2000']);
    expect(($this->variacao)())->toMatchArray(['sell_price_inc_tax' => '16.5000', 'default_sell_price' => '15.0000']);
});

it('CU-PROD-21 · chave ligada mas a regra segue outra coisa (regraEm = categoria): nada muda', function () {
    ($this->regra)('aplicar', 'categoria');
    ($this->politica)('fixo');
    ($this->chave)(true);

    ($this->receber)();

    expect(($this->variacao)())->toMatchArray(['sell_price_inc_tax' => '18.1500', 'default_sell_price' => '16.5000', 'profit_percent' => '50.0000']);
});
