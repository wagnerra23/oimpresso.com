<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * As três escritas do módulo que ainda não tinham teste de empresa — Tier 0, ADR 0093.
 * Auditoria do Manufacturing, lote 3.4 (eixo 1 §5).
 *
 *  - UC-OP-17    excluir rascunho de ordem (`ProductionController::destroy`);
 *  - UC-INGRED-15 grupo de ingredientes de outra empresa no salvar da receita (`RecipeController::store`);
 *  - UC-CFG-04   gravar as configurações (`SettingsController::store`), agora por HTTP.
 *
 * Cada caso tem a âncora positiva: a mesma chamada sobre o dado da própria empresa funciona,
 * para a ausência de escrita no outro lado não ser uma requisição que nem chegou ao código.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a outra empresa. NUNCA biz=4. Tudo em transação.
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'products', 'variations', 'transactions', 'mfg_recipes', 'mfg_recipe_ingredients', 'mfg_ingredient_groups'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgIsoEscSessao(): array
{
    return ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
}

function mfgIsoEscRascunho(int $biz, int $userId): int
{
    mfgEmpNegocio($biz);

    return DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'production_purchase', 'status' => 'received',
        'transaction_date' => '2026-10-01 10:00:00', 'created_by' => $userId, 'essentials_duration' => 0,
        'ref_no' => 'OP-ISO-'.random_int(1000, 9999), 'final_total' => 0, 'mfg_is_final' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

describe('UC-OP-17 — excluir rascunho só apaga ordem da minha empresa', function () {
    it('UC-OP-17 o rascunho de outra empresa continua; o da própria é apagado', function () {
        $user = mfgEmpUsuario('mfg_iso_excluir_ordem');
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));
        $alheia = mfgIsoEscRascunho(MFG_EMP_OUTRO, $user->id);
        $propria = mfgIsoEscRascunho(MFG_EMP_BIZ, $user->id);

        $this->actingAs($user)->withSession(mfgIsoEscSessao())
            ->deleteJson('/manufacturing/production/'.$alheia, [], ['X-Requested-With' => 'XMLHttpRequest']);
        expect(DB::table('transactions')->where('id', $alheia)->exists())->toBeTrue();

        $this->actingAs($user)->withSession(mfgIsoEscSessao())
            ->deleteJson('/manufacturing/production/'.$propria, [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJson(['success' => true]);
        expect(DB::table('transactions')->where('id', $propria)->exists())->toBeFalse();
    });
});

describe('UC-INGRED-15 — grupo de ingredientes de outra empresa não é renomeado nem reaproveitado', function () {
    /** POST da receita com UMA linha num grupo: `$grupoId` é o id que o formulário manda. */
    function mfgIsoEscSalvarComGrupo($test, $user, int $variationId, int $insumo, int $linha, int $grupoId, string $nome)
    {
        return $test->actingAs($user)->withSession(mfgIsoEscSessao())->post('/manufacturing/recipe', [
            'variation_id' => $variationId,
            'ingredients' => [[
                'ingredient_id' => $insumo, 'ingredient_line_id' => $linha, 'quantity' => '2',
                'waste_percent' => '0', 'sort_order' => 1, 'ig_index' => 0, 'mfg_ingredient_group_id' => $grupoId,
            ]],
            'ingredient_groups' => [$nome], 'ingredient_group_description' => [''],
            'total' => '0', 'total_quantity' => '1', 'ingredients_cost' => '0',
            'waste_percent' => '0', 'extra_cost' => '0', 'production_cost_type' => 'fixed',
            'instructions' => 'gravado pelo teste de grupo',
        ]);
    }

    function mfgIsoEscGrupo(int $biz, string $nome): int
    {
        return DB::table('mfg_ingredient_groups')->insertGetId([
            'business_id' => $biz, 'name' => $nome, 'description' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    it('UC-INGRED-15 o grupo da outra empresa fica intacto e a linha ganha grupo da própria', function () {
        $user = mfgEmpUsuario('mfg_iso_grupo_alheio');
        [$receita, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo do grupo');
        $insumo = (int) MfgRecipeIngredient::find($linha)->variation_id;
        $alheio = mfgIsoEscGrupo(MFG_EMP_OUTRO, 'Grupo da outra empresa');

        mfgIsoEscSalvarComGrupo($this, $user, $acabado, $insumo, $linha, $alheio, 'Nome trocado pelo teste')->assertRedirect();

        expect(DB::table('mfg_ingredient_groups')->where('id', $alheio)->value('name'))->toBe('Grupo da outra empresa');
        $grupoDaLinha = (int) DB::table('mfg_recipe_ingredients')->where('mfg_recipe_id', $receita)->value('mfg_ingredient_group_id');
        expect($grupoDaLinha)->not->toBe($alheio);
        expect((int) DB::table('mfg_ingredient_groups')->where('id', $grupoDaLinha)->value('business_id'))->toBe(MFG_EMP_BIZ);
    });

    it('UC-INGRED-15 âncora: o grupo da própria empresa, só desta receita, é renomeado no lugar', function () {
        $user = mfgEmpUsuario('mfg_iso_grupo_proprio');
        [$receita, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo do grupo proprio');
        $insumo = (int) MfgRecipeIngredient::find($linha)->variation_id;
        $proprio = mfgIsoEscGrupo(MFG_EMP_BIZ, 'Grupo da propria empresa');
        DB::table('mfg_recipe_ingredients')->where('id', $linha)->update(['mfg_ingredient_group_id' => $proprio]);

        mfgIsoEscSalvarComGrupo($this, $user, $acabado, $insumo, $linha, $proprio, 'Grupo renomeado')->assertRedirect();

        expect(DB::table('mfg_ingredient_groups')->where('id', $proprio)->value('name'))->toBe('Grupo renomeado');
        expect((int) DB::table('mfg_recipe_ingredients')->where('mfg_recipe_id', $receita)->value('mfg_ingredient_group_id'))->toBe($proprio);
    });
});

describe('UC-CFG-04 — gravar configurações só altera a empresa da sessão (por HTTP)', function () {
    it('UC-CFG-04 o POST grava na 98 e a configuração da 99 não muda', function () {
        $user = mfgEmpUsuario('mfg_iso_config');
        mfgEmpNegocio(MFG_EMP_OUTRO);
        $antesOutra = DB::table('business')->where('id', MFG_EMP_OUTRO)->value('manufacturing_settings');

        $this->actingAs($user)->withSession(mfgIsoEscSessao())->post('/manufacturing/settings', [
            'ref_no_prefix' => 'ISO98', 'disable_editing_ingredient_qty' => '1',
        ])->assertRedirect();

        $gravada = json_decode((string) DB::table('business')->where('id', MFG_EMP_BIZ)->value('manufacturing_settings'), true);
        expect($gravada['ref_no_prefix'] ?? null)->toBe('ISO98');
        expect($gravada['disable_editing_ingredient_qty'] ?? null)->toBeTrue();
        expect(DB::table('business')->where('id', MFG_EMP_OUTRO)->value('manufacturing_settings'))->toBe($antesOutra);
    });
});
