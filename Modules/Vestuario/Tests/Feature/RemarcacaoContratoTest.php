<?php
// @covers-us US-VEST-032

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\Remarcacao;
use Tests\Support\WithSeededTenant;

/**
 * US-VEST-032 — remarcação por coleção (UC-VREM-01..04).
 *
 * Âncora: decisão [W] 2026-10-27 registrada no SPEC — "seleciona a coleção ou um filtro, aplica
 * percentual ou valor, vê a prévia por produto e grava; depois pode reverter". Os valores esperados
 * são contas feitas à mão (2º caminho da regra mestre de valor), não saída do serviço.
 * Tenant 98 contra 99 (ADR 0358). NUNCA biz=4 (ROTA LIVRE).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('vestuario_remarcacoes')) {
        $this->markTestSkipped('Requer MySQL com o schema do UltimatePOS.');
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->dono = fn (int $biz) => (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $this->tag = 'Verao' . random_int(100000, 999999);
    // produto com variações [nome => preço de etiqueta]; devolve [nome => variation_id]
    $this->produto = function (int $biz, array $precos, ?float $taxa = null): array {
        $tax = $taxa === null ? null : DB::table('tax_rates')->insertGetId(['business_id' => $biz, 'name' => 'T' . $taxa,
            'amount' => $taxa, 'created_by' => ($this->dono)($biz), 'created_at' => now(), 'updated_at' => now()]);
        $unit = DB::table('units')->insertGetId(['business_id' => $biz, 'actual_name' => 'Peça', 'short_name' => 'pc', 'allow_decimal' => 0,
            'created_by' => ($this->dono)($biz), 'created_at' => now(), 'updated_at' => now()]);
        $suf = random_int(100000, 999999);
        $prod = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $this->tag . ' Camiseta ' . $suf, 'type' => 'variable',
            'unit_id' => $unit, 'tax' => $tax, 'tax_type' => 'inclusive', 'sku' => 'REM-' . $suf, 'barcode_type' => 'C128', 'enable_stock' => 1,
            'alert_quantity' => 0, 'created_by' => ($this->dono)($biz), 'created_at' => now(), 'updated_at' => now()]);
        $pv = DB::table('product_variations')->insertGetId(['product_id' => $prod, 'name' => 'Tamanho', 'is_dummy' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $ids = [];
        foreach ($precos as $nome => $preco) {
            $ids[$nome] = DB::table('variations')->insertGetId(['name' => $nome, 'product_id' => $prod, 'product_variation_id' => $pv,
                'sub_sku' => 'REM-' . $suf . '-' . $nome, 'default_purchase_price' => 40, 'dpp_inc_tax' => 40, 'profit_percent' => 0,
                'default_sell_price' => $preco, 'sell_price_inc_tax' => $preco, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $ids;
    };
    $this->preco = fn (int $var) => DB::table('variations')->where('id', $var)->first(['sell_price_inc_tax', 'default_sell_price', 'profit_percent']);
    $this->svc = app(Remarcacao::class);
    $this->va = ($this->produto)($this->biz, ['P' => 100.00, 'M' => 59.90]);
});

it('UC-VREM-01 — a prévia mostra antes → depois de cada variação e não grava nada', function () {
    $p = $this->svc->previa($this->biz, ['nome' => $this->tag], 'percentual', -30);

    // à mão: 100,00 × 0,70 = 70,00 · 59,90 × 0,70 = 41,93
    expect(collect($p['linhas'])->pluck('preco_depois', 'variation_id')->all())
        ->toEqual([$this->va['P'] => 70.00, $this->va['M'] => 41.93]);
    expect((float) ($this->preco)($this->va['P'])->sell_price_inc_tax)->toBe(100.0);
    expect(DB::table('vestuario_remarcacoes')->where('business_id', $this->biz)->count())->toBe(0);
    // valor fixo: todas vão para o preço digitado
    expect(collect($this->svc->previa($this->biz, ['nome' => $this->tag], 'valor', 49.9)['linhas'])->pluck('preco_depois')->unique()->all())->toEqual([49.9]);
});

it('UC-VREM-02 — gravar exige a prévia em dia, guarda o antes e deriva preço sem imposto e margem', function () {
    $vt = ($this->produto)($this->biz, ['U' => 110.00], 10.0);
    $p = $this->svc->previa($this->biz, ['nome' => $this->tag], 'percentual', -10);

    // a prévia envelheceu: outra pessoa mexeu num preço → nada é gravado
    DB::table('variations')->where('id', $this->va['M'])->update(['sell_price_inc_tax' => 60.00]);
    expect(fn () => $this->svc->aplicar($this->biz, 1, 'Liquidação', ['nome' => $this->tag], 'percentual', -10, $p['assinatura']))
        ->toThrow(RuntimeException::class);
    expect(DB::table('vestuario_remarcacoes')->where('business_id', $this->biz)->count())->toBe(0);

    $p = $this->svc->previa($this->biz, ['nome' => $this->tag], 'percentual', -10);
    $lote = $this->svc->aplicar($this->biz, 1, 'Liquidação', ['nome' => $this->tag], 'percentual', -10, $p['assinatura']);

    // à mão: 100 → 90 (margem (90-40)/40 = 125%) · 110 com 10% → 99 inc, 99×100/110 = 90 sem imposto
    $p100 = ($this->preco)($this->va['P']);
    expect([(float) $p100->sell_price_inc_tax, (float) $p100->default_sell_price, (float) $p100->profit_percent])->toEqual([90.0, 90.0, 125.0]);
    $pt = ($this->preco)($vt['U']);
    expect([(float) $pt->sell_price_inc_tax, (float) $pt->default_sell_price])->toEqual([99.0, 90.0]);
    expect((float) DB::table('vestuario_remarcacao_itens')->where('remarcacao_id', $lote)->where('variation_id', $this->va['P'])->value('preco_antes'))->toBe(100.0);
    expect(DB::table('vestuario_remarcacoes')->where('id', $lote)->value('qtd_itens'))->toEqual(3);
});

it('UC-VREM-03 — reverter devolve o preço de antes e não pisa em preço mexido depois', function () {
    $p = $this->svc->previa($this->biz, ['nome' => $this->tag], 'percentual', -30);
    $lote = $this->svc->aplicar($this->biz, 1, 'Liquidação', ['nome' => $this->tag], 'percentual', -30, $p['assinatura']);
    DB::table('variations')->where('id', $this->va['M'])->update(['sell_price_inc_tax' => 45.00]);

    expect($this->svc->reverter($this->biz, 1, $lote))->toBe(['revertidos' => 1, 'conflitos' => 1]);
    expect((float) ($this->preco)($this->va['P'])->sell_price_inc_tax)->toBe(100.0);
    expect((float) ($this->preco)($this->va['P'])->profit_percent)->toBe(0.0);
    expect((float) ($this->preco)($this->va['M'])->sell_price_inc_tax)->toBe(45.0);
    expect(fn () => $this->svc->reverter($this->biz, 1, $lote))->toThrow(RuntimeException::class);
});

it('UC-VREM-04 — outra empresa não entra na remarcação nem reverte o lote [T0]', function () {
    $vo = ($this->produto)($this->outra, ['P' => 100.00]);
    $p = $this->svc->previa($this->biz, ['nome' => $this->tag], 'percentual', -30);
    expect(collect($p['linhas'])->pluck('variation_id')->all())->not->toContain($vo['P']);

    $lote = $this->svc->aplicar($this->biz, 1, 'Liquidação', ['nome' => $this->tag], 'percentual', -30, $p['assinatura']);
    expect((float) ($this->preco)($vo['P'])->sell_price_inc_tax)->toBe(100.0);
    expect(fn () => $this->svc->reverter($this->outra, 1, $lote))->toThrow(RuntimeException::class);
    expect((float) ($this->preco)($this->va['P'])->sell_price_inc_tax)->toBe(70.0);
    // sem filtro nenhum, recusa (não remarca a loja inteira por engano)
    expect(fn () => $this->svc->previa($this->biz, [], 'percentual', -30))->toThrow(RuntimeException::class);
});
