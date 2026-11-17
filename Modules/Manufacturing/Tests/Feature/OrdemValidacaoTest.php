<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-OP-21 — a ordem recusa quantidade que não existe, antes de gravar ou de dividir por ela.
 * Auditoria do Manufacturing, lote 2.2 (eixo 5 M3).
 *
 * Contrato: SDD Fabricação §4 (a ordem produz uma quantidade positiva e consome insumo; a quantidade
 * que entra no estoque é a produzida menos a perdida) e SPEC US-MANU-008 (a ordem só grava o que
 * passou pela validação do servidor). Quantidade 0 não é ordem, quantidade negativa somaria estoque
 * em vez de baixar, e perda igual ou maior que a produção deixaria o acabado com zero ou menos.
 *
 * Cada recusa confere que nada foi gravado e que o erro volta no campo; a âncora é a mesma ordem
 * com números válidos, que grava.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'products', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'transaction_sell_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgValUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_validacao_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgValLocal(): int
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => 'Local validacao '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

function mfgValPayload(int $variationId, int $localId, array $ingredientes, array $extra = []): array
{
    $empresa = DB::table('business')->where('id', MFG_EMP_BIZ)->first(['date_format', 'time_format']);
    $formato = $empresa->date_format.((string) $empresa->time_format === '12' ? ' h:i A' : ' H:i');

    return array_merge([
        'transaction_date' => now()->format($formato),
        'location_id' => $localId,
        'final_total' => '15',
        'variation_id' => $variationId,
        'quantity' => '3',
        'ingredients' => $ingredientes,
    ], $extra);
}

function mfgValOrdens(): int
{
    return DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->count();
}

describe('UC-OP-21 — gravar a ordem recusa quantidade inválida', function () {
    dataset('ordem_invalida', [
        'quantidade zero'               => [['quantity' => '0'], 'quantity'],
        'quantidade negativa'           => [['quantity' => '-3'], 'quantity'],
        'perda igual à produção'        => [['quantity' => '3', 'mfg_wasted_units' => '3'], 'mfg_wasted_units'],
        'perda negativa'                => [['mfg_wasted_units' => '-1'], 'mfg_wasted_units'],
        'custo extra negativo'          => [['production_cost' => '-2', 'mfg_production_cost_type' => 'fixed'], 'production_cost'],
    ]);

    it('UC-OP-21 POST com número inválido volta com erro no campo e não grava ordem', function (array $campos, string $campoComErro) {
        $user = mfgValUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo validacao '.$campoComErro);
        $antes = mfgValOrdens();

        $this->actingAs($user)
            ->post('/manufacturing/production', mfgValPayload($acabado, mfgValLocal(), [$linha => ['quantity' => '2']], $campos))
            ->assertSessionHasErrors($campoComErro);

        expect(mfgValOrdens())->toBe($antes);
    })->with('ordem_invalida');

    it('UC-OP-21 POST com consumo de insumo negativo volta com erro na linha e não grava ordem', function () {
        $user = mfgValUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo consumo negativo');
        $antes = mfgValOrdens();

        $this->actingAs($user)
            ->post('/manufacturing/production', mfgValPayload($acabado, mfgValLocal(), [$linha => ['quantity' => '-2']]))
            ->assertSessionHasErrors('ingredients.'.$linha.'.quantity');

        expect(mfgValOrdens())->toBe($antes);
    });

    it('UC-OP-21 âncora: a mesma ordem com números válidos grava', function () {
        $user = mfgValUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo validacao ancora');
        $antes = mfgValOrdens();

        $this->actingAs($user)
            ->post('/manufacturing/production', mfgValPayload($acabado, mfgValLocal(), [$linha => ['quantity' => '2']], ['mfg_wasted_units' => '1', 'production_cost' => '2', 'mfg_production_cost_type' => 'fixed']))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/manufacturing/production');

        expect(mfgValOrdens())->toBe($antes + 1);
    });
});

describe('UC-OP-21 — editar o rascunho recusa quantidade inválida', function () {
    /** Rascunho pela própria rota. Devolve [ordem, linha de venda, variação, local]. */
    function mfgValRascunho($teste, \App\User $user): array
    {
        [, $variacao, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo rascunho validacao');
        $local = mfgValLocal();
        $teste->actingAs($user)
            ->post('/manufacturing/production', mfgValPayload($variacao, $local, [$linhaReceita => ['quantity' => '2']]))
            ->assertRedirect('/manufacturing/production');

        $ordem = (int) DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
        $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
        $linhaVenda = (int) DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('id');

        return [$ordem, $linhaVenda, $variacao, $local];
    }

    it('UC-OP-21 PUT com quantidade zero ou consumo negativo volta com erro e o rascunho não muda', function () {
        $user = mfgValUsuario();
        [$ordem, $linhaVenda, $variacao, $local] = mfgValRascunho($this, $user);
        $total = (float) DB::table('transactions')->where('id', $ordem)->value('final_total');

        $this->actingAs($user)
            ->put('/manufacturing/production/'.$ordem, mfgValPayload($variacao, $local, [$linhaVenda => ['quantity' => '2']], ['quantity' => '0']))
            ->assertSessionHasErrors('quantity');
        $this->actingAs($user)
            ->put('/manufacturing/production/'.$ordem, mfgValPayload($variacao, $local, [$linhaVenda => ['quantity' => '-2']]))
            ->assertSessionHasErrors('ingredients.'.$linhaVenda.'.quantity');

        expect((float) DB::table('transactions')->where('id', $ordem)->value('final_total'))->toBe($total);
        expect((float) DB::table('transaction_sell_lines')->where('id', $linhaVenda)->value('quantity'))->toBe(2.0);
    });

    it('UC-OP-21 âncora: o PUT com números válidos grava o consumo novo', function () {
        $user = mfgValUsuario();
        [$ordem, $linhaVenda, $variacao, $local] = mfgValRascunho($this, $user);

        $this->actingAs($user)
            ->put('/manufacturing/production/'.$ordem, mfgValPayload($variacao, $local, [$linhaVenda => ['quantity' => '4']]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/manufacturing/production');

        $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
        expect((float) DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('quantity'))->toBe(4.0);
    });
});
