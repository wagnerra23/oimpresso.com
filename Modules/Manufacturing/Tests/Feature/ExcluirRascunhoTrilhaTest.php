<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Excluir o rascunho leva a ordem inteira e deixa trilha; a trilha da receita tem empresa.
 * Auditoria do Manufacturing, lote 2.7 (eixo 5 M4).
 *
 *  - UC-OP-23  excluir o rascunho apaga a ordem E o consumo dela (a venda de produção filha, com as
 *              linhas), registra a exclusão na trilha, e ordem finalizada ou inexistente não responde
 *              "excluído";
 *  - UC-RECIPE-25 o registro de alteração da receita e dos ingredientes leva a empresa, senão some da
 *              tela /auditoria, que filtra por empresa.
 *
 * Contrato: a ordem é a compra de produção mais a venda de produção que consome os insumos (SDD Fabricação
 * §4; `ProductionController::store` cria as duas, ligadas por `mfg_parent_production_purchase_id`). Ordem
 * finalizada não se exclui (charter de Index). A trilha da auditoria filtra por `business_id`
 * (`ActivityCauserKindObserver`, ADR 0127).
 *
 * Rascunho não movimenta estoque (compra `pending`, venda `draft`): apagar as duas não muda saldo.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'products', 'variations', 'mfg_recipes', 'transactions', 'transaction_sell_lines', 'business_locations', 'activity_log'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    if (! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('activity_log sem business_id nesta base.');
    }
});

function mfgExcUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_excluir_rascunho_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgExcRascunho($teste, \App\User $user): array
{
    [, $variacao, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo excluir rascunho');
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);
    $local = DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => 'Local excluir '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
    $empresa = DB::table('business')->where('id', MFG_EMP_BIZ)->first(['date_format', 'time_format']);
    $formato = $empresa->date_format.((string) $empresa->time_format === '12' ? ' h:i A' : ' H:i');

    $teste->actingAs($user)->post('/manufacturing/production', [
        'transaction_date' => now()->format($formato), 'location_id' => $local, 'final_total' => '15',
        'variation_id' => $variacao, 'quantity' => '1', 'ingredients' => [$linhaReceita => ['quantity' => '2']],
    ])->assertRedirect('/manufacturing/production');

    $ordem = (int) DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
    $venda = (int) DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');

    return [$ordem, $venda];
}

function mfgExcApagar($teste, \App\User $user, int $ordem)
{
    return $teste->actingAs($user)->deleteJson('/manufacturing/production/'.$ordem, [], ['X-Requested-With' => 'XMLHttpRequest']);
}

describe('UC-OP-23 — excluir rascunho leva a ordem inteira e deixa trilha', function () {
    it('UC-OP-23 apaga a ordem, a venda de produção filha e as linhas dela', function () {
        $user = mfgExcUsuario();
        [$ordem, $venda] = mfgExcRascunho($this, $user);
        expect($venda)->toBeGreaterThan(0);
        expect(DB::table('transaction_sell_lines')->where('transaction_id', $venda)->count())->toBeGreaterThan(0);

        mfgExcApagar($this, $user, $ordem)->assertOk()->assertJson(['success' => true]);

        expect(DB::table('transactions')->where('id', $ordem)->exists())->toBeFalse();
        expect(DB::table('transactions')->where('id', $venda)->exists())->toBeFalse();
        expect(DB::table('transaction_sell_lines')->where('transaction_id', $venda)->count())->toBe(0);
    });

    it('UC-OP-23 a exclusão fica na trilha, com a empresa', function () {
        $user = mfgExcUsuario();
        [$ordem] = mfgExcRascunho($this, $user);

        mfgExcApagar($this, $user, $ordem)->assertJson(['success' => true]);

        $log = DB::table('activity_log')->where('subject_type', \App\Transaction::class)->where('subject_id', $ordem)
            ->where('description', 'deleted')->first();
        expect($log)->not->toBeNull('A exclusão do rascunho não deixou registro na trilha.');
        expect((int) $log->business_id)->toBe(MFG_EMP_BIZ);
    });

    it('UC-OP-23 ordem finalizada ou inexistente não responde "excluído", e a finalizada fica', function () {
        $user = mfgExcUsuario();
        [$ordem] = mfgExcRascunho($this, $user);
        DB::table('transactions')->where('id', $ordem)->update(['mfg_is_final' => 1]);

        mfgExcApagar($this, $user, $ordem)->assertJson(['success' => false]);
        expect(DB::table('transactions')->where('id', $ordem)->exists())->toBeTrue();

        mfgExcApagar($this, $user, 999999999)->assertJson(['success' => false]);
    });
});

describe('UC-RECIPE-25 — a trilha da receita leva a empresa', function () {
    it('UC-RECIPE-25 alterar a receita e um ingrediente grava o registro com a empresa da receita', function () {
        $user = mfgEmpUsuario('mfg_trilha_receita_test');
        [$receita, , $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo trilha receita');

        MfgRecipe::find($receita)->update(['instructions' => 'trilha alterada pelo teste']);
        MfgRecipeIngredient::find($linha)->update(['quantity' => 3]);

        $logReceita = DB::table('activity_log')->where('subject_type', MfgRecipe::class)->where('subject_id', $receita)
            ->where('description', 'updated')->first();
        $logLinha = DB::table('activity_log')->where('subject_type', MfgRecipeIngredient::class)->where('subject_id', $linha)
            ->where('description', 'updated')->first();

        expect($logReceita)->not->toBeNull('A alteração da receita não gerou registro.');
        expect($logLinha)->not->toBeNull('A alteração do ingrediente não gerou registro.');
        expect((int) $logReceita->business_id)->toBe(MFG_EMP_BIZ);
        expect((int) $logLinha->business_id)->toBe(MFG_EMP_BIZ);
    });
});
