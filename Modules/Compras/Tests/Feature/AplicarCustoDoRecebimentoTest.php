<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\AplicarCustoDoRecebimento;
use Tests\Support\WithSeededTenant;

/**
 * US-COM-026 · UC-FLX-17 — compra recebida atualiza o custo e trata o preço pela regra da empresa.
 *
 * Âncora: memory/requisitos/Compras/SPEC.md US-COM-026 + resources/js/Pages/Purchase/Create.casos.md
 * (UC-PURCUS-01..06). Os números esperados vêm da conta à mão abaixo; cada caso refaz a conta por um
 * segundo caminho em PHP (custoRecebimentoEsperado, sem SQL) antes de comparar. Regra mestre de
 * valor (proibicoes.md): dois caminhos, números concretos.
 *
 * Arranjo — variação V do tenant 98, produto com imposto de 10%:
 *   antes: custo 10,0000 sem imposto / 11,0000 com · preço 15,0000 / 16,5000 · margem 50%
 *   A  2026-03-01  compra recebida  10 un × 11,00 (com imposto)
 *   B  2026-03-10  compra recebida  10 un × 13,20  ← a que está sendo recebida
 *
 * médio em 10/03 = (10×11,00 + 10×13,20) / 20 = 242 / 20 = 12,1000 com imposto
 *                  sem imposto = 12,1 × 100 / 110 = 11,0000
 * so_custo  → preço igual (16,5000), margem = (15 − 11) / 11 × 100 = 36,3636
 * aplicar   → preço sem imposto = 11 × 1,5 = 16,5000 · com imposto = 16,5 × 1,1 = 18,1500 · margem 50
 * propor    → proposta 11,0000→12,1000 de custo e 16,5000→18,1500 de preço; preço igual
 * último    → 13,2000 com imposto, 12,0000 sem · aplicar: 12 × 1,5 = 18,0000 → 19,8000
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode no MySQL real (lane compras-pest ou CT 100).');
    }

    $this->user = User::factory()->create(['business_id' => $this->biz->id, 'username' => 'custo_receb_'.uniqid()]);
    $agora = Carbon::now();
    $sku = 'CUSTOREC-'.uniqid();

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
        'business_id' => $this->biz->id, 'name' => 'Vinil '.$sku, 'type' => 'single', 'unit_id' => $unitId,
        'tax' => $taxId, 'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
        'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $this->productId, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $this->antes = ['default_purchase_price' => 10, 'dpp_inc_tax' => 11, 'profit_percent' => 50, 'default_sell_price' => 15, 'sell_price_inc_tax' => 16.5];
    $this->variationId = DB::table('variations')->insertGetId($this->antes + [
        'product_id' => $this->productId, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $this->compra = function (int $biz, string $status, string $data, float $qtd, float $preco): Transaction {
        $tid = DB::table('transactions')->insertGetId([
            'business_id' => $biz, 'type' => 'purchase', 'status' => $status, 'payment_status' => 'due',
            'transaction_date' => $data, 'ref_no' => 'CUSTOREC-'.uniqid(), 'final_total' => $qtd * $preco,
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
    ($this->compra)($this->biz->id, 'received', '2026-03-01 09:00:00', 10, 11.00);

    $this->regra = function (int $biz, ?array $params, bool $ativo = true): void {
        DB::table('fsm_modelos_empresa')->where('business_id', $biz)->delete();
        DB::table('fsm_modelos_empresa')->insert([
            'business_id' => $biz, 'modelo' => 'legado', 'created_at' => now(), 'updated_at' => now(),
            'regras_compra' => $params === null ? null : json_encode(['insumo.custo' => ['ativo' => $ativo, 'params' => $params]]),
        ]);
    };
    $this->variacao = fn () => (array) DB::table('variations')->where('id', $this->variationId)
        ->first(['default_purchase_price', 'dpp_inc_tax', 'profit_percent', 'default_sell_price', 'sell_price_inc_tax']);
    $this->servico = app(AplicarCustoDoRecebimento::class);
});

/** Segundo caminho: a fórmula da US-COM-026 refeita em PHP, sem SQL, a partir das compras do arranjo. */
function custoRecebimentoEsperado(array $compras, string $metodo, string $preco): array
{
    $custoInc = $metodo === 'ultimo'
        ? end($compras)[1]
        : array_sum(array_map(fn ($c) => $c[0] * $c[1], $compras)) / array_sum(array_column($compras, 0));
    $custoExc = round($custoInc / 1.10, 4);
    $vendaInc = round(round($custoExc * 1.5, 4) * 1.10, 4);
    $margem = round((15 - $custoExc) / $custoExc * 100, 4);
    $f = fn (float $n) => number_format($n, 4, '.', '');

    return [
        'dpp_inc_tax' => $f(round($custoInc, 4)),
        'default_purchase_price' => $f($custoExc),
        'sell_price_inc_tax' => $f($preco === 'aplicar' ? $vendaInc : 16.5),
        'profit_percent' => $f($preco === 'aplicar' ? 50 : $margem),
        'preco_proposto' => $f($vendaInc),
    ];
}

it('UC-PURCUS-01 · so_custo grava o custo médio e não muda o preço', function () {
    ($this->regra)($this->biz->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo']);
    $esperado = custoRecebimentoEsperado([[10, 11.00], [10, 13.20]], 'medio', 'so_custo');
    expect($esperado['dpp_inc_tax'])->toBe('12.1000')->and($esperado['profit_percent'])->toBe('36.3636');

    $this->servico->aoReceber(($this->compra)($this->biz->id, 'received', '2026-03-10 09:00:00', 10, 13.20));

    expect(($this->variacao)())->toMatchArray([
        'dpp_inc_tax' => '12.1000', 'default_purchase_price' => '11.0000',
        'sell_price_inc_tax' => '16.5000', 'default_sell_price' => '15.0000', 'profit_percent' => '36.3636',
    ])->and(DB::table('compras_propostas_preco')->where('variation_id', $this->variationId)->count())->toBe(0);
});

it('UC-PURCUS-02 · aplicar recalcula o preço mantendo a margem da variação', function () {
    ($this->regra)($this->biz->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar']);
    $esperado = custoRecebimentoEsperado([[10, 11.00], [10, 13.20]], 'medio', 'aplicar');
    expect($esperado['sell_price_inc_tax'])->toBe('18.1500');

    $this->servico->aoReceber(($this->compra)($this->biz->id, 'received', '2026-03-10 09:00:00', 10, 13.20));

    expect(($this->variacao)())->toMatchArray([
        'dpp_inc_tax' => '12.1000', 'default_purchase_price' => '11.0000',
        'default_sell_price' => '16.5000', 'sell_price_inc_tax' => '18.1500', 'profit_percent' => '50.0000',
    ]);
});

it('UC-PURCUS-02 · aplicar pelo último preço usa a compra mais recente', function () {
    ($this->regra)($this->biz->id, ['etapa' => 'recebido', 'metodo' => 'ultimo', 'preco' => 'aplicar']);
    $esperado = custoRecebimentoEsperado([[10, 11.00], [10, 13.20]], 'ultimo', 'aplicar');
    expect($esperado['default_purchase_price'])->toBe('12.0000')->and($esperado['sell_price_inc_tax'])->toBe('19.8000');

    $this->servico->aoReceber(($this->compra)($this->biz->id, 'received', '2026-03-10 09:00:00', 10, 13.20));

    expect(($this->variacao)())->toMatchArray([
        'dpp_inc_tax' => '13.2000', 'default_purchase_price' => '12.0000',
        'default_sell_price' => '18.0000', 'sell_price_inc_tax' => '19.8000', 'profit_percent' => '50.0000',
    ]);
});

it('UC-PURCUS-03 · propor grava a proposta pendente e não muda o preço', function () {
    ($this->regra)($this->biz->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'propor']);
    $esperado = custoRecebimentoEsperado([[10, 11.00], [10, 13.20]], 'medio', 'propor');
    expect($esperado['preco_proposto'])->toBe('18.1500');

    $compra = ($this->compra)($this->biz->id, 'received', '2026-03-10 09:00:00', 10, 13.20);
    $this->servico->aoReceber($compra);

    expect(($this->variacao)())->toMatchArray(['dpp_inc_tax' => '12.1000', 'sell_price_inc_tax' => '16.5000', 'profit_percent' => '36.3636']);
    $p = DB::table('compras_propostas_preco')->where('variation_id', $this->variationId)->get();
    expect($p)->toHaveCount(1)
        ->and((array) $p[0])->toMatchArray([
            'business_id' => $this->biz->id, 'transaction_id' => $compra->id, 'status' => 'pendente',
            'custo_antes' => '11.0000', 'custo_depois' => '12.1000', 'preco_antes' => '16.5000', 'preco_proposto' => '18.1500',
            'decided_by' => null, 'decided_at' => null,
        ]);
});

it('UC-PURCUS-04 · com a regra, compra pendente não muda custo nem preço', function () {
    ($this->regra)($this->biz->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar']);

    $feito = $this->servico->aoReceber(($this->compra)($this->biz->id, 'pending', '2026-03-10 09:00:00', 10, 13.20));

    expect($feito)->toBe([])
        ->and(($this->variacao)())->toMatchArray(['dpp_inc_tax' => '11.0000', 'sell_price_inc_tax' => '16.5000'])
        // e o core não grava o último preço por cima: com a regra, a edição de produto pela compra desliga.
        ->and($this->servico->assume($this->biz->id))->toBeTrue();
});

it('UC-PURCUS-05 · empresa sem a regra gravada segue o comportamento de antes', function () {
    ($this->regra)($this->biz->id, null);
    $controller = app(\App\Http\Controllers\PurchaseController::class);
    $helper = (fn (int $b) => $this->custoDoRecebimento($b))->call($controller, $this->biz->id);

    $feito = $this->servico->aoReceber(($this->compra)($this->biz->id, 'received', '2026-03-10 09:00:00', 10, 13.20));

    expect($helper)->toBeNull()
        ->and($this->servico->assume($this->biz->id))->toBeFalse()
        ->and($feito)->toBe([])
        ->and(($this->variacao)())->toMatchArray(['dpp_inc_tax' => '11.0000', 'default_purchase_price' => '10.0000', 'sell_price_inc_tax' => '16.5000']);

    // Regra gravada mas desligada também devolve ao core.
    ($this->regra)($this->biz->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar'], false);
    expect($this->servico->assume($this->biz->id))->toBeFalse();

    // Gravada e ligada: o controller passa a usar a regra.
    ($this->regra)($this->biz->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar']);
    expect((fn (int $b) => $this->custoDoRecebimento($b))->call($controller, $this->biz->id))->not->toBeNull();
});

it('UC-PURCUS-06 · Tier 0: compra de outra empresa não muda a variação desta', function () {
    ($this->regra)($this->outra->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar']);

    $feito = $this->servico->aoReceber(($this->compra)($this->outra->id, 'received', '2026-03-10 09:00:00', 10, 13.20));

    expect($feito)->toBe([])
        ->and(($this->variacao)())->toMatchArray(['dpp_inc_tax' => '11.0000', 'sell_price_inc_tax' => '16.5000'])
        ->and(DB::table('compras_propostas_preco')->where('business_id', $this->outra->id)->where('variation_id', $this->variationId)->count())->toBe(0);
});
