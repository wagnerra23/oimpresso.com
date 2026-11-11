<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Gravar e editar a ordem de produção só com produto e local da PRÓPRIA empresa. Tier 0, ADR 0093 ·
 * US-MANU-008 H1 · handoff Fabricação §9.
 *
 * O `OrdemReceitaEmpresaTest` cobre o produto no POST. Este cobre o que sobrou: o `location_id` no
 * POST e no PUT, e o `variation_id` no PUT. Com `finalize=1` a ordem movimenta estoque, então os
 * casos alheios conferem também que `variation_location_details` da outra empresa não mudou.
 *
 * Âncora positiva: a mesma chamada na própria empresa grava. O caso alheio exige 404, e não 403:
 * o usuário TEM a permissão, quem recusa é a empresa do dado.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4.
 * Tudo roda dentro de transação desfeita no fim (§5 2026-09-18).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::store()
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::update()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a cadeia de empresa depende do schema MySQL UltimatePOS (products/variations).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'variation_location_details'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgGuardaUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_local_empresa_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgGuardaLocal(int $biz): int
{
    mfgEmpNegocio($biz);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => $biz, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => $biz, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $biz, 'name' => 'Local guarda '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

/** A data vai no formato da empresa, porque store e update a leem com `uf_date(..., true)`. */
function mfgGuardaPayload(int $variationId, int $localId, array $ingredientes, array $extra = []): array
{
    $empresa = DB::table('business')->where('id', MFG_EMP_BIZ)->first(['date_format', 'time_format']);
    $formato = $empresa->date_format.((string) $empresa->time_format === '12' ? ' h:i A' : ' H:i');

    return array_merge([
        'transaction_date' => now()->format($formato),
        'location_id' => $localId,
        'final_total' => '15',
        'variation_id' => $variationId,
        'quantity' => '1',
        'ingredients' => $ingredientes,
    ], $extra);
}

function mfgGuardaOrdens(): int
{
    return DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->count();
}

/** Faz o produto da variação controlar estoque: é o que leva a ordem a mexer em `variation_location_details`. */
function mfgGuardaControlaEstoque(int $variationId): void
{
    $produto = DB::table('variations')->where('id', $variationId)->value('product_id');
    DB::table('products')->where('id', $produto)->update(['enable_stock' => 1]);
}

/** Linha de estoque com saldo conhecido. */
function mfgGuardaEstoque(int $variationId, int $localId, float $saldo): void
{
    $v = DB::table('variations')->where('id', $variationId)->first(['product_id', 'product_variation_id']);
    DB::table('variation_location_details')->insert([
        'product_id' => $v->product_id, 'product_variation_id' => $v->product_variation_id,
        'variation_id' => $variationId, 'location_id' => $localId, 'qty_available' => $saldo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Retrato do estoque num filtro: número de linhas e saldo somado. */
function mfgGuardaRetrato(string $coluna, int $id): array
{
    $q = DB::table('variation_location_details')->where($coluna, $id);

    return ['linhas' => (clone $q)->count(), 'saldo' => (float) (clone $q)->sum('qty_available')];
}

/**
 * Rascunho de ordem da empresa 98, gravado pela própria rota. Devolve o id da ordem, o id da linha
 * de insumo (o PUT indexa `ingredients` por ela), a variação do acabado e o local.
 */
function mfgGuardaRascunho($teste, \App\User $user): array
{
    [, $variacao, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo rascunho proprio');
    $local = mfgGuardaLocal(MFG_EMP_BIZ);

    $teste->actingAs($user)
        ->post('/manufacturing/production', mfgGuardaPayload($variacao, $local, [$linha => ['quantity' => '2']]))
        ->assertRedirect('/manufacturing/production');

    $ordem = DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
    $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
    $linhaVenda = DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('id');

    return [$ordem, $linhaVenda, $variacao, $local];
}

describe('Ordem de produção — local e produto da própria empresa (Tier 0 · H1)', function () {
    it('UC-OP-14 — POST com local de outra empresa é recusado com 404, sem ordem e sem estoque nesse local', function () {
        $user = mfgGuardaUsuario();
        [, $variacao, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo post local alheio');
        mfgGuardaControlaEstoque($variacao);
        $localAlheio = mfgGuardaLocal(MFG_EMP_OUTRO);
        $ordensAntes = mfgGuardaOrdens();
        $estoqueAntes = mfgGuardaRetrato('location_id', $localAlheio);

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgGuardaPayload($variacao, $localAlheio, [$linha => ['quantity' => '2']], ['finalize' => 1]));

        $resposta->assertNotFound();
        expect(mfgGuardaOrdens())->toBe($ordensAntes);
        expect(mfgGuardaRetrato('location_id', $localAlheio))->toBe($estoqueAntes);
    });

    it('UC-OP-14 — PUT na própria empresa grava o local e o total novos (âncora positiva)', function () {
        $user = mfgGuardaUsuario();
        [$ordem, $linhaVenda, $variacao] = mfgGuardaRascunho($this, $user);
        $outroLocal = mfgGuardaLocal(MFG_EMP_BIZ);

        // O total é do servidor (UC-OP-19): 4 do insumo a 7,5 = 30. O `final_total` do payload não entra.
        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgGuardaPayload($variacao, $outroLocal, [$linhaVenda => ['quantity' => '4']], ['final_total' => '30']));

        $resposta->assertRedirect('/manufacturing/production');
        $gravada = DB::table('transactions')->where('id', $ordem)->first(['location_id', 'final_total']);
        expect((int) $gravada->location_id)->toBe($outroLocal);
        expect((float) $gravada->final_total)->toBe(30.0);
    });

    it('UC-OP-14 — PUT com local de outra empresa é recusado com 404 e a ordem fica como estava', function () {
        $user = mfgGuardaUsuario();
        [$ordem, $linhaVenda, $variacao, $local] = mfgGuardaRascunho($this, $user);
        $localAlheio = mfgGuardaLocal(MFG_EMP_OUTRO);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgGuardaPayload($variacao, $localAlheio, [$linhaVenda => ['quantity' => '2']], ['final_total' => '30']));

        $resposta->assertNotFound();
        $gravada = DB::table('transactions')->where('id', $ordem)->first(['location_id', 'final_total']);
        expect((int) $gravada->location_id)->toBe($local);
        expect((float) $gravada->final_total)->toBe(15.0);
    });

    it('UC-OP-14 — PUT finalizando com produto de outra empresa é recusado com 404 e o estoque dela fica intacto', function () {
        $user = mfgGuardaUsuario();
        [$ordem, $linhaVenda, $variacao, $local] = mfgGuardaRascunho($this, $user);
        [, $variacaoAlheia] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo put produto alheio');
        mfgGuardaControlaEstoque($variacaoAlheia);
        mfgGuardaEstoque($variacaoAlheia, mfgGuardaLocal(MFG_EMP_OUTRO), 10.0);
        $estoqueAntes = mfgGuardaRetrato('variation_id', $variacaoAlheia);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgGuardaPayload($variacaoAlheia, $local, [$linhaVenda => ['quantity' => '2']], ['finalize' => 1]));

        $resposta->assertNotFound();
        expect(mfgGuardaRetrato('variation_id', $variacaoAlheia))->toBe($estoqueAntes);
        expect((int) DB::table('purchase_lines')->where('transaction_id', $ordem)->value('variation_id'))->toBe($variacao);
        expect((int) DB::table('transactions')->where('id', $ordem)->value('mfg_is_final'))->toBe(0);
    });
});
