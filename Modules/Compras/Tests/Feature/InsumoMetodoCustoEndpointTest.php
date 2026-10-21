<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CustoDoInsumoService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\WithSeededTenant;

/**
 * US-COM-024 · UC-FLX-38 — gravar o método de custo do insumo pela gaveta de
 * /manufacturing/insumos (`PUT /compras/insumos/{variation}/metodo-custo`).
 * Cobre UC-INSMET-06..09 (resources/js/Pages/Manufacturing/Insumos.casos.md).
 *
 * Âncora de contrato: memory/requisitos/Compras/SPEC.md US-COM-024. VALOR (regra mestre,
 * proibicoes.md): o custo esperado vem da conta à mão sobre o arranjo e é conferido de novo
 * pelo serviço depois da gravação — dois caminhos com números concretos.
 *
 * Arranjo — variação V do tenant 98, compras recebidas:
 *   B  2026-03-01  10 un × 12,00
 *   C  2026-03-10  30 un × 16,00, 10 devolvidas → líquido 20
 * Em 31/03: médio = (10×12 + 20×16) / 30 = 440 / 30 = 14,6667 · último = 16,0000 (C).
 * A variação W é de um produto do tenant 99.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode no MySQL real (lane compras-pest ou CT 100).');
    }

    $agora = Carbon::now();
    $this->user = User::factory()->create([
        'business_id' => $this->biz->id, 'username' => 'metodo_custo_'.uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'purchase.update', 'guard_name' => 'web']));
    $this->sessao = ['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]];

    $novaVariacao = function (int $biz, string $sku) use ($agora): array {
        $unitId = (int) (DB::table('units')->where('business_id', $biz)->value('id')
            ?? DB::table('units')->insertGetId([
                'business_id' => $biz, 'actual_name' => 'Unidade '.$sku, 'short_name' => 'un',
                'allow_decimal' => 0, 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
            ]));
        $productId = DB::table('products')->insertGetId([
            'business_id' => $biz, 'name' => 'Lona '.$sku, 'type' => 'single', 'unit_id' => $unitId,
            'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
            'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $grupo = DB::table('product_variations')->insertGetId([
            'product_id' => $productId, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $variationId = DB::table('variations')->insertGetId([
            'product_id' => $productId, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
            'default_purchase_price' => 99, 'dpp_inc_tax' => 99, 'profit_percent' => 0,
            'default_sell_price' => 150, 'sell_price_inc_tax' => 150, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        return [$productId, $variationId];
    };

    [$productId, $this->variationId] = $novaVariacao($this->biz->id, 'METCUSTO-'.uniqid());
    [, $this->variacaoDaOutra] = $novaVariacao($this->outra->id, 'METCUSTO99-'.uniqid());

    foreach ([['2026-03-01 09:00:00', 10, 0, 12], ['2026-03-10 09:00:00', 30, 10, 16]] as [$data, $qtd, $dev, $preco]) {
        $tid = DB::table('transactions')->insertGetId([
            'business_id' => $this->biz->id, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
            'transaction_date' => $data, 'ref_no' => 'METCUSTO-'.uniqid(), 'final_total' => $qtd * $preco,
            'total_before_tax' => $qtd * $preco, 'tax_amount' => 0, 'discount_amount' => 0,
            'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $tid, 'product_id' => $productId, 'variation_id' => $this->variationId,
            'quantity' => $qtd, 'quantity_returned' => $dev, 'pp_without_discount' => $preco,
            'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
    }
});

/** As linhas da tabela-ponte de uma empresa, só das duas variações do arranjo. */
function linhasMetodoCusto(int $biz, array $variacoes): array
{
    return DB::table('compras_insumo_metodo_custo')->where('business_id', $biz)
        ->whereIn('variation_id', $variacoes)->pluck('metodo', 'variation_id')->all();
}

it('UC-INSMET-06 · UC-FLX-38 · gravar o método pela gaveta muda o custo por insumo, e trocar não duplica a linha', function () {
    $servico = app(CustoDoInsumoService::class);
    $variacoes = [$this->variationId, $this->variacaoDaOutra];
    // Antes: sem linha, o custo por insumo é nulo com o motivo.
    expect($servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'por_insumo')['custo'])->toBeNull();

    $origem = '/manufacturing/insumos?insumo='.$this->variationId;
    $this->actingAs($this->user)->withSession($this->sessao)->from($origem)
        ->put("/compras/insumos/{$this->variationId}/metodo-custo", ['metodo' => 'ultimo'])
        ->assertRedirect($origem)
        ->assertSessionHasNoErrors();

    // À mão: último = compra C, 16,00.
    expect(linhasMetodoCusto($this->biz->id, $variacoes))->toBe([$this->variationId => 'ultimo'])
        ->and($servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'por_insumo')['custo'])->toBe('16.0000');

    $this->actingAs($this->user)->withSession($this->sessao)->from($origem)
        ->put("/compras/insumos/{$this->variationId}/metodo-custo", ['metodo' => 'medio'])
        ->assertSessionHasNoErrors();

    // À mão: (10×12 + 20×16) / 30 = 14,6667. Uma linha só (unique empresa + variação).
    expect(linhasMetodoCusto($this->biz->id, $variacoes))->toBe([$this->variationId => 'medio'])
        ->and($servico->naData($this->biz->id, $this->variationId, '2026-03-31', 'por_insumo')['custo'])->toBe('14.6667');
});

it('UC-INSMET-07 · UC-FLX-38 · Tier 0: o insumo de outra empresa responde 404 e nada é gravado', function () {
    $variacoes = [$this->variationId, $this->variacaoDaOutra];

    $this->actingAs($this->user)->withSession($this->sessao)
        ->put("/compras/insumos/{$this->variacaoDaOutra}/metodo-custo", ['metodo' => 'ultimo'])
        ->assertNotFound();

    expect(linhasMetodoCusto($this->biz->id, $variacoes))->toBe([])
        ->and(linhasMetodoCusto($this->outra->id, $variacoes))->toBe([]);
});

it('UC-INSMET-08 · UC-FLX-38 · sem purchase.update a gravação é recusada com 403 e nada é gravado', function () {
    $this->user->revokePermissionTo('purchase.update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user->fresh())->withSession($this->sessao)
        ->put("/compras/insumos/{$this->variationId}/metodo-custo", ['metodo' => 'ultimo'])
        ->assertForbidden();

    expect(linhasMetodoCusto($this->biz->id, [$this->variationId]))->toBe([]);
});

it('UC-INSMET-09 · UC-FLX-38 · método fora de médio/último é recusado no campo metodo e nada é gravado', function () {
    $this->actingAs($this->user)->withSession($this->sessao)
        ->from('/manufacturing/insumos')
        ->put("/compras/insumos/{$this->variationId}/metodo-custo", ['metodo' => 'por_insumo'])
        ->assertSessionHasErrors('metodo');

    expect(linhasMetodoCusto($this->biz->id, [$this->variationId]))->toBe([]);
});
