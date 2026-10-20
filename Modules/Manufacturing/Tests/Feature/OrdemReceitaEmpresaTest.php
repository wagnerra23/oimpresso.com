<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Montar e gravar a ordem de produção só com receita da PRÓPRIA empresa — Tier 0, ADR 0093.
 *
 * Prova as hipóteses H1 e H2 de `memory/requisitos/Manufacturing/PARIDADE-ordem-producao-blade-vs-prototipo.md`
 * (§5, registradas em 2026-10-13 no #9086):
 *
 * - H2 · `GET /manufacturing/get-recipe-details` (`RecipeController::getRecipeDetails`) busca a receita
 *   só por `variation_id`, sem a cadeia de empresa. **Medido no CI (run 39422978037): NÃO vaza.** A
 *   receita de outra empresa já saía 404 antes do conserto, por acidente: `Util::getSubUnits()` busca a
 *   unidade do produto com `Unit::where('business_id', <sessão>)->findOrFail()`, e a unidade é da outra
 *   empresa. O conserto vira defesa explícita (não depende mais desse acidente) e acerta a variação
 *   inexistente, que estourava 500 ("Attempt to read property variation on null").
 * - H1 · `POST /manufacturing/production` (`ProductionController::store`) aceita `variation_id` de
 *   qualquer empresa (`exists:variations,id`) e busca a receita sem a cadeia. Lido no código (o CI deste
 *   PR confirma ou derruba): a mesma `getSubUnits()` derruba a requisição com 404 quando chega aos
 *   insumos da outra empresa — mas DEPOIS de incrementar o contador de referência da empresa da sessão
 *   (fora da transação, persiste) e de gravar a ordem numa transação que fica aberta (desfeita no fim
 *   da requisição em produção; visível dentro do teste, que usa a mesma conexão). Receita sem insumo e
 *   variação sem receita estouram 500 (`getIngredientDetails()` devolve `$ingredients`, que só nasce
 *   dentro do laço). O conserto recusa com 404 ANTES de qualquer escrita.
 *
 * Âncora positiva em cada endpoint (a própria empresa passa), para que o 404 da outra empresa não seja
 * rota que nem respondeu. O caso alheio exige 404, e não 403: o usuário TEM a permissão, quem recusa
 * é a cadeia de empresa — mesmo contrato do `ExcluirReceitaEmpresaTest` (#9071).
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4.
 * Tudo roda dentro de transação desfeita no fim (§5 2026-09-18: mutação no CT 100 persiste).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::getRecipeDetails()
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::store()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a cadeia de empresa depende do schema MySQL UltimatePOS (products/variations).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/** Usuário da empresa 98 com as permissões de ler receita e de produzir. */
function mfgOrdemUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_receita_empresa_test');
    foreach (['manufacturing.access_recipe', 'manufacturing.access_production'] as $permissao) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
    }

    return $user;
}

/** Ordens de produção (`production_purchase`) gravadas na empresa da sessão. */
function mfgOrdensDaEmpresa(): int
{
    return DB::table('transactions')
        ->where('business_id', MFG_EMP_BIZ)
        ->where('type', 'production_purchase')
        ->count();
}

/** Local da empresa 98 (mesmo idioma de `despLocal()` em ExpensesBaselineTest). */
function mfgOrdemLocal(): int
{
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => 'Local ordem '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

/**
 * Corpo mínimo do formulário da ordem (Blade `production/create`). A data vai no formato da empresa
 * (`business.date_format` + `time_format`), porque o store a lê com `uf_date(..., true)`.
 */
function mfgOrdemPayload(int $variationId, int $localId, int $linhaInsumo): array
{
    $empresa = DB::table('business')->where('id', MFG_EMP_BIZ)->first(['date_format', 'time_format']);
    $formato = $empresa->date_format.((string) $empresa->time_format === '12' ? ' h:i A' : ' H:i');

    return [
        'transaction_date' => now()->format($formato),
        'location_id' => $localId,
        'final_total' => '15',
        'variation_id' => $variationId,
        'quantity' => '1',
        // Quantidade de cada linha da receita, indexada pelo id da linha (mfg_recipe_ingredients.id).
        'ingredients' => [$linhaInsumo => ['quantity' => '2']],
    ];
}

describe('Montar a ordem — detalhes da receita só da própria empresa (Tier 0 · H2)', function () {
    it('UC-OP-12 — devolve a receita da própria empresa com os ingredientes dela (âncora positiva)', function () {
        $user = mfgOrdemUsuario();
        [, $variacao] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo detalhe proprio');

        $resposta = $this->actingAs($user)->get('/manufacturing/get-recipe-details?variation_id='.$variacao);

        $resposta->assertOk();
        expect($resposta->getContent())->toContain('Insumo detalhe proprio');
    });

    it('UC-OP-12 — recusa com 404 a receita de outra empresa e não mostra nada dela', function () {
        $user = mfgOrdemUsuario();
        [, $variacao] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo detalhe alheio');

        $resposta = $this->actingAs($user)->get('/manufacturing/get-recipe-details?variation_id='.$variacao);

        $resposta->assertNotFound();
        expect($resposta->getContent())->not->toContain('Insumo detalhe alheio');
    });

    it('UC-OP-12 — recusa com 404 uma variação que não existe', function () {
        $user = mfgOrdemUsuario();
        $inexistente = (int) DB::table('variations')->max('id') + 1000;

        $this->actingAs($user)->get('/manufacturing/get-recipe-details?variation_id='.$inexistente)->assertNotFound();
    });
});

describe('Gravar a ordem — só com produto da própria empresa (Tier 0 · H1)', function () {
    it('UC-OP-13 — a ordem com produto da própria empresa é gravada (âncora positiva)', function () {
        $user = mfgOrdemUsuario();
        [, $variacao, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo ordem proprio');
        $local = mfgOrdemLocal();
        $antes = mfgOrdensDaEmpresa();

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgOrdemPayload($variacao, $local, $linha));

        // O store devolve o mesmo redirect no sucesso e no erro que ele trata; a ordem gravada é o
        // que separa os dois caminhos (§5 2026-09-05: o status sozinho não discrimina).
        $resposta->assertRedirect('/manufacturing/production');
        expect(mfgOrdensDaEmpresa())->toBe($antes + 1);
    });

    it('UC-OP-13 — recusa com 404 a ordem com produto de outra empresa, sem gravar nada', function () {
        $user = mfgOrdemUsuario();
        [, $variacao, $linha] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo ordem alheio');
        $local = mfgOrdemLocal();
        $antes = mfgOrdensDaEmpresa();

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgOrdemPayload($variacao, $local, $linha));

        $resposta->assertNotFound();
        expect(mfgOrdensDaEmpresa())->toBe($antes);
    });
});
