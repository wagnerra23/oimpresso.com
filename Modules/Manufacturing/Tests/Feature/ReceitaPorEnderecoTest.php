<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-RECIPE-27 — a aba Receitas abre uma receita pelo endereço (`?receita=ID`).
 *
 * Contrato: Pendências da Fabricação I-05 (medição de 2026-11-25) e o protótipo, em que o nome da
 * receita afetada, na gaveta do Insumo, abre a receita na aba Receitas. Plano aprovado por [W] em
 * 2026-11-25. O link do Insumo (UC-INS-08) aponta para este endereço.
 *
 *  - a receita vem mesmo fora da página/filtro atual (a gaveta não depende da lista visível);
 *  - receita de OUTRA empresa vem null (Tier 0, ADR 0093) — âncora positiva: a dona a recebe;
 *  - sem `view_purchase_price` o custo vem vazio, como na lista (UC-RECIPE-26).
 *
 * Tenant 98 contra 99 (ADR 0358), nunca biz=4. Transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see resources/js/Pages/Manufacturing/Recipes.casos.md UC-RECIPE-27
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('Schema MySQL UltimatePOS ausente.');
    }
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);
});

function mfgEnderecoUsuario($teste, int $biz, bool $veCusto): \App\User
{
    $perms = ['manufacturing.access_recipe', 'access_all_locations'];
    if ($veCusto) {
        $perms[] = 'view_purchase_price';
    }

    return $teste->usuarioComPermissoes($perms, \App\Business::find($biz));
}

function mfgEnderecoProps($resp): array
{
    $resp->assertOk();

    return (array) data_get($resp->viewData('page'), 'props', []);
}

describe('UC-RECIPE-27 — a receita abre pelo endereço', function () {
    it('UC-RECIPE-27 ?receita=ID traz a receita fora da pagina, e so da propria empresa', function () {
        mfgEmpNegocio(MFG_EMP_BIZ);
        mfgEmpNegocio(MFG_EMP_OUTRO);
        $dono = (int) DB::table('users')->min('id');
        [$daEmpresa] = mfgEmpReceita(MFG_EMP_BIZ, $dono, 'Insumo por endereco');
        [$deOutra] = mfgEmpReceita(MFG_EMP_OUTRO, $dono, 'Insumo por endereco alheio');

        $usuario = mfgEnderecoUsuario($this, MFG_EMP_BIZ, true);

        // Fora da página: a busca não casa com nenhuma receita, a lista vem vazia, e a receita vem.
        $fora = mfgEnderecoProps($this->actingAs($usuario)->get('/manufacturing/recipe?q=zz-nao-casa-nada&receita='.$daEmpresa));
        expect($fora['recipes']['data'])->toBe([]);
        expect($fora['receita_aberta']['id'] ?? null)->toBe($daEmpresa);
        expect($fora['receita_aberta']['extra'])->toBeFloat();

        // Outra empresa: null para quem não é dona; a dona recebe a mesma receita.
        $alheia = mfgEnderecoProps($this->actingAs($usuario)->get('/manufacturing/recipe?receita='.$deOutra));
        expect($alheia['receita_aberta'])->toBeNull();
        $daDona = mfgEnderecoProps($this->actingAs(mfgEnderecoUsuario($this, MFG_EMP_OUTRO, true))->get('/manufacturing/recipe?receita='.$deOutra));
        expect($daDona['receita_aberta']['id'] ?? null)->toBe($deOutra);

        // Sem o parâmetro, nada aberto.
        expect(mfgEnderecoProps($this->actingAs($usuario)->get('/manufacturing/recipe'))['receita_aberta'])->toBeNull();
    });

    it('UC-RECIPE-27 sem view_purchase_price a receita aberta vem sem custo', function () {
        mfgEmpNegocio(MFG_EMP_BIZ);
        $dono = (int) DB::table('users')->min('id');
        [$receita] = mfgEmpReceita(MFG_EMP_BIZ, $dono, 'Insumo por endereco sem custo');

        $props = mfgEnderecoProps($this->actingAs(mfgEnderecoUsuario($this, MFG_EMP_BIZ, false))->get('/manufacturing/recipe?receita='.$receita));

        expect($props['receita_aberta']['id'] ?? null)->toBe($receita);
        expect($props['receita_aberta']['venda'])->toBeNull();
        expect($props['receita_aberta']['extra'])->toBeNull();
    });
});
