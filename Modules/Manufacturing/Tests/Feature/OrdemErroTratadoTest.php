<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Erro ao gravar a ordem de produção é tratado, e 404/validação continuam sendo 404/validação.
 *
 * Quem falha por falta de insumo volta para a lista com a mensagem de erro, sem meia ordem nem meia
 * baixa de estoque (o `DB::rollBack()` precisa rodar). Os dois caminhos que NÃO são falha da ordem
 * têm de passar direto: ordem de outra empresa segue 404 (Tier 0, ADR 0093) e campo faltando segue
 * erro de validação no formulário.
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
        $this->markTestSkipped('SQLite-incompatível: a baixa de estoque depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'variation_location_details', 'purchase_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgErroUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_erro_tratado_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgErroLocal(int $biz): int
{
    mfgEmpNegocio($biz);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => $biz, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => $biz, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $biz, 'name' => 'Local erro '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

function mfgErroPayload(int $variationId, int $localId, array $ingredientes, array $extra = []): array
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

function mfgErroOrdens(string $tipo): int
{
    return DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', $tipo)->count();
}

/** Liga o controle de estoque do insumo da linha, sem saldo: é a falta de insumo. Devolve a variação. */
function mfgErroInsumoSemSaldo(int $linhaReceita, int $local): int
{
    $insumo = (int) DB::table('mfg_recipe_ingredients')->where('id', $linhaReceita)->value('variation_id');
    $v = DB::table('variations')->where('id', $insumo)->first(['product_id', 'product_variation_id']);
    DB::table('products')->where('id', $v->product_id)->update(['enable_stock' => 1]);
    DB::table('variation_location_details')->insert([
        'product_id' => $v->product_id, 'product_variation_id' => $v->product_variation_id,
        'variation_id' => $insumo, 'location_id' => $local, 'qty_available' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $insumo;
}

function mfgErroSaldo(int $variacao, int $local): float
{
    return (float) DB::table('variation_location_details')->where('variation_id', $variacao)->where('location_id', $local)->sum('qty_available');
}

/** Rascunho da empresa 98 pela própria rota. Devolve [ordem, linha de venda, variação, local, linha da receita]. */
function mfgErroRascunho($teste, \App\User $user): array
{
    [, $variacao, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo rascunho erro');
    $local = mfgErroLocal(MFG_EMP_BIZ);

    $teste->actingAs($user)
        ->post('/manufacturing/production', mfgErroPayload($variacao, $local, [$linhaReceita => ['quantity' => '2']]))
        ->assertRedirect('/manufacturing/production');

    $ordem = DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
    $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
    $linhaVenda = DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('id');

    return [$ordem, $linhaVenda, $variacao, $local, $linhaReceita];
}

describe('Erro ao gravar a ordem — tratado, sem esconder 404 nem validação', function () {
    it('UC-OP-15 — POST finalizando sem insumo volta com erro e desfaz a ordem e a baixa', function () {
        $user = mfgErroUsuario();
        [, $variacao, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo sem saldo post');
        $local = mfgErroLocal(MFG_EMP_BIZ);
        $insumo = mfgErroInsumoSemSaldo($linhaReceita, $local);
        $ordens = mfgErroOrdens('production_purchase');
        $vendas = mfgErroOrdens('production_sell');

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgErroPayload($variacao, $local, [$linhaReceita => ['quantity' => '2']], ['finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(0);
        expect(mfgErroOrdens('production_purchase'))->toBe($ordens);
        expect(mfgErroOrdens('production_sell'))->toBe($vendas);
        expect(mfgErroSaldo($insumo, $local))->toBe(0.0);
    });

    it('UC-OP-15 — PUT finalizando sem insumo volta com erro e o rascunho fica como estava', function () {
        $user = mfgErroUsuario();
        [$ordem, $linhaVenda, $variacao, $local, $linhaReceita] = mfgErroRascunho($this, $user);
        $insumo = mfgErroInsumoSemSaldo($linhaReceita, $local);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgErroPayload($variacao, $local, [$linhaVenda => ['quantity' => '2']], ['finalize' => 1, 'final_total' => '30']));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(0);
        $gravada = DB::table('transactions')->where('id', $ordem)->first(['mfg_is_final', 'final_total']);
        expect((int) $gravada->mfg_is_final)->toBe(0);
        expect((float) $gravada->final_total)->toBe(15.0);
        expect(mfgErroSaldo($insumo, $local))->toBe(0.0);
    });

    it('UC-OP-15 — PUT em ordem de outra empresa continua 404, e ela não muda', function () {
        $user = mfgErroUsuario();
        [, $variacao, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo ordem alheia');
        $local = mfgErroLocal(MFG_EMP_BIZ);
        $alheia = DB::table('transactions')->insertGetId([
            'business_id' => MFG_EMP_OUTRO, 'location_id' => mfgErroLocal(MFG_EMP_OUTRO), 'type' => 'production_purchase',
            'status' => 'pending', 'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 15,
            'mfg_is_final' => 0, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$alheia, mfgErroPayload($variacao, $local, [$linhaReceita => ['quantity' => '2']], ['final_total' => '30']));

        $resposta->assertNotFound();
        expect((float) DB::table('transactions')->where('id', $alheia)->value('final_total'))->toBe(15.0);
    });

    it('UC-OP-15 — PUT sem a data continua erro de validação no campo, não mensagem genérica', function () {
        $user = mfgErroUsuario();
        [$ordem, $linhaVenda, $variacao, $local] = mfgErroRascunho($this, $user);
        $corpo = mfgErroPayload($variacao, $local, [$linhaVenda => ['quantity' => '2']], ['final_total' => '30']);
        unset($corpo['transaction_date']);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, $corpo);

        $resposta->assertSessionHasErrors('transaction_date');
        expect((float) DB::table('transactions')->where('id', $ordem)->value('final_total'))->toBe(15.0);
    });
});
