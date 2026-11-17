<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — as 5 telas do Manufacturing que não tinham navegador nem axe, e os 4 casos
 * de navegador que viviam em `test.fixme` no Playwright (auditoria do Manufacturing, lote 3.6).
 *
 * ── POR QUE AQUI E NÃO NO PLAYWRIGHT ─────────────────────────────────────────
 * Nenhum workflow roda `e2e/*.spec.ts` (varredura em `.github/workflows/`: zero `playwright test`).
 * Os `fixme` de `e2e/manufacturing-*.spec.ts` não teriam lane nem se fossem destravados. Os
 * Browser tests deste diretório rodam no `visual-regression.yml` (step advisory abaixo do do
 * `RecipesIndexTest`). O caso R-04 (a tecla `/` foca a busca) já está no `RecipesIndexTest`;
 * os outros quatro vêm para cá, com o dado que faltava: cada caso semeia a própria receita e
 * apaga no `->after()`.
 *
 * ── O QUE PROVA ──────────────────────────────────────────────────────────────
 *   1. Ordens, Insumos, Relatório, Configurações e o Editor de ingredientes montam autenticados
 *      em 1280, com o cabeçalho e os containers de contrato, sem erro de console;
 *   2. zero violação axe CRITICAL em cada uma (mesmo nível do `RecipesIndexTest`);
 *   3. R-05 / UC-RECIPE-10: o cartão "Margem abaixo de 45%" tira da lista a receita de margem alta
 *      e mantém a de margem baixa;
 *   4. R-14: o painel da receita abre ao clicar na linha e fecha com Esc;
 *   5. R-22: a via de produção não mostra valor de compra ("R$");
 *   6. UC-INGRED-01: o editor abre a ficha da receita com o ingrediente gravado.
 *
 * ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
 *   - Fechar o painel pelo clique no scrim (o fixme pedia): sem medida de layout confiável sem
 *     executar, ficou só o Esc.
 *   - Pixel: nenhum `screenshot()`.
 *
 * ── TENANT ───────────────────────────────────────────────────────────────────
 * O mesmo do gate visual: `Business::orderBy('id')->first()` do `VisregTenantSeeder` no MySQL
 * `oimpresso_test`, tenant fictício. NUNCA biz=4. As receitas semeadas têm SKU com prefixo
 * `MFGTEL-` e saem no `->after()` (produto, variação, receita e ingredientes).
 *
 * ── EXECUÇÃO (CI / CT 100 — nunca local) ─────────────────────────────────────
 *   ./vendor/bin/pest tests/Browser/Manufacturing/TelasFabricacaoTest.php
 *
 * Não executado antes do PR (Pest Browser é CI/CT-100 only): o step nasce ADVISORY.
 *
 * @see resources/js/Pages/Manufacturing/Index.tsx
 * @see resources/js/Pages/Manufacturing/Insumos.tsx
 * @see resources/js/Pages/Manufacturing/Report.tsx
 * @see resources/js/Pages/Manufacturing/Settings.tsx
 * @see resources/js/Pages/Manufacturing/IngredientesEditor.tsx
 * @see resources/js/Pages/Manufacturing/Recipes.tsx
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

function mfgTelAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder nao rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: nao da pra autenticar.');
    }

    return $admin;
}

/** Espera o probe JS devolver o valor esperado; falha alto com o último valor lido. */
function mfgTelEsperar($page, string $js, string $esperado, string $oque): void
{
    $ultimo = null;
    for ($i = 0; $i < 24; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === $esperado) {
            return;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Nao estabilizou: {$oque} — esperado '{$esperado}', ultimo '{$ultimo}'.");
}

/** Probe de prontidão: a raiz da tela e os containers de contrato pedidos existem. */
function mfgTelProntoJs(string $rotulo, array $contratos): string
{
    $sel = json_encode(array_map(fn ($c) => '[data-contract="'.$c.'"]', $contratos));
    $rot = json_encode($rotulo);

    return "(() => { const r = document.querySelector('[data-screen-label=' + JSON.stringify({$rot}) + ']');"
        ." if (!r) return 'RAIZ-AUSENTE';"
        ." const falta = {$sel}.filter((s) => !r.querySelector(s));"
        ." return falta.length ? 'FALTA ' + falta.join(',') : 'PRONTO'; })()";
}

function mfgTelAbrir(string $rota, string $rotulo, array $contratos)
{
    $page = visit('/_visreg-login/'.mfgTelAdmin()->id.'?to='.urlencode($rota))->resize(1280, 800);
    mfgTelEsperar($page, mfgTelProntoJs($rotulo, $contratos), 'PRONTO', "a tela {$rotulo} montar");

    return $page;
}

/**
 * Receita no tenant do gate. Devolve [variation_id do acabado, nome do produto].
 * `$custo` é o preço do insumo (1 unidade na receita); `$venda` é o preço de venda da receita.
 */
function mfgTelReceita(string $nome, float $custo, float $venda): array
{
    $admin = mfgTelAdmin();
    $biz = (int) $admin->business_id;
    $unit = (int) DB::table('units')->where('business_id', $biz)->orderBy('id')->value('id');
    $novo = function (string $nomeProduto, float $preco) use ($biz, $unit, $admin) {
        $sku = 'MFGTEL-'.random_int(100000, 999999);
        $p = DB::table('products')->insertGetId([
            'business_id' => $biz, 'name' => $nomeProduto, 'type' => 'single', 'unit_id' => $unit,
            'tax_type' => 'exclusive', 'sku' => $sku, 'barcode_type' => 'C128', 'enable_stock' => 0,
            'alert_quantity' => 0, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pv = DB::table('product_variations')->insertGetId([
            'product_id' => $p, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $v = DB::table('variations')->insertGetId([
            'name' => 'DUMMY', 'product_id' => $p, 'product_variation_id' => $pv, 'sub_sku' => $sku,
            'default_purchase_price' => $preco, 'dpp_inc_tax' => $preco, 'profit_percent' => 0,
            'default_sell_price' => 0, 'sell_price_inc_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$p, $v];
    };
    [$produto, $acabado] = $novo($nome, 0.0);
    [, $insumo] = $novo('Insumo '.$nome, $custo);
    $receita = DB::table('mfg_recipes')->insertGetId([
        'product_id' => $produto, 'variation_id' => $acabado, 'instructions' => 'mfg-telas-browser',
        'waste_percent' => 0, 'ingredients_cost' => $custo, 'extra_cost' => 0, 'production_cost_type' => 'fixed',
        'total_quantity' => 1, 'final_price' => $venda, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('mfg_recipe_ingredients')->insert([
        'mfg_recipe_id' => $receita, 'variation_id' => $insumo, 'quantity' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$acabado, $nome];
}

function mfgTelLimpar(): void
{
    $produtos = DB::table('products')->where('sku', 'like', 'MFGTEL-%')->pluck('id');
    $variacoes = DB::table('variations')->whereIn('product_id', $produtos)->pluck('id');
    $receitas = DB::table('mfg_recipes')->whereIn('variation_id', $variacoes)->pluck('id');
    DB::table('mfg_recipe_ingredients')->whereIn('mfg_recipe_id', $receitas)->delete();
    DB::table('mfg_recipes')->whereIn('id', $receitas)->delete();
    DB::table('variations')->whereIn('product_id', $produtos)->delete();
    DB::table('product_variations')->whereIn('product_id', $produtos)->delete();
    DB::table('products')->whereIn('id', $produtos)->delete();
}

/** As linhas da lista de receitas que contêm o texto. */
function mfgTelLinhasCom(string $texto): string
{
    $t = json_encode($texto);

    return "(() => String([...document.querySelectorAll('[data-contract=\"lista\"] [role=\"button\"]')]"
        .".filter((l) => (l.textContent || '').includes({$t})).length))()";
}

dataset('mfg_telas', [
    'Ordens de produção' => ['/manufacturing/production', 'Fabricação · Ordens de produção', ['cabecalho', 'kpis']],
    'Insumos'            => ['/manufacturing/insumos', 'Fabricação · Insumos', ['cabecalho', 'lista']],
    'Relatório'          => ['/manufacturing/report', 'Fabricação · Relatório', ['cabecalho', 'filtros', 'lista']],
    'Configurações'      => ['/manufacturing/settings', 'Fabricação · Configurações', ['cabecalho', 'form']],
]);

it('render — a tela monta autenticada, sem erro de console e sem violação axe CRITICAL', function (string $rota, string $rotulo, array $contratos) {
    $page = mfgTelAbrir($rota, $rotulo, $contratos);

    $page->assertNoConsoleLogs();
    $page->assertNoAccessibilityIssues(level: 0);
})->with('mfg_telas');

it('UC-INGRED-01 · render — o editor abre a ficha com o ingrediente gravado, sem violação axe CRITICAL', function () {
    [$acabado, $nome] = mfgTelReceita('Receita editor browser', 4.0, 10.0);

    $page = mfgTelAbrir("/manufacturing/add-ingredient?variation_id={$acabado}&tela=nova", 'Fabricação · Editor de ingredientes', ['cabecalho', 'formulario']);

    $page->assertSee('Insumo '.$nome);
    $page->assertNoConsoleLogs();
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => mfgTelLimpar());

it('UC-RECIPE-10 · R-05 — o cartão de margem tira da lista a receita de margem alta', function () {
    mfgTelReceita('Receita margem alta browser', 10.0, 100.0); // margem 90%
    mfgTelReceita('Receita margem baixa browser', 10.0, 12.0); // margem ~16,7%

    $page = mfgTelAbrir('/manufacturing/recipe?q='.urlencode('browser'), 'Fabricação · Receitas', ['kpis', 'lista']);
    mfgTelEsperar($page, mfgTelLinhasCom('Receita margem alta browser'), '1', 'a receita de margem alta aparecer');
    expect($page->script(mfgTelLinhasCom('Receita margem baixa browser')))->toBe('1');

    $page->script("(() => { const b = [...document.querySelectorAll('[data-contract=\"kpis\"] button')]"
        .".find((x) => (x.textContent || '').includes('Margem abaixo de 45%')); if (b) b.click(); return b ? 'OK' : 'SEM-CARTAO'; })()");

    mfgTelEsperar($page, mfgTelLinhasCom('Receita margem alta browser'), '0', 'o filtro de margem tirar a receita de margem alta');
    expect($page->script(mfgTelLinhasCom('Receita margem baixa browser')))->toBe('1');
})->after(fn () => mfgTelLimpar());

it('R-14 — o painel da receita abre ao clicar na linha e fecha com Esc', function () {
    mfgTelReceita('Receita painel browser', 5.0, 20.0);
    $page = mfgTelAbrir('/manufacturing/recipe?q='.urlencode('Receita painel browser'), 'Fabricação · Receitas', ['lista']);
    mfgTelEsperar($page, mfgTelLinhasCom('Receita painel browser'), '1', 'a receita aparecer');

    $dialogo = "(() => document.querySelector('[role=\"dialog\"]') ? 'ABERTO' : 'FECHADO')()";
    $page->script("(() => { const l = [...document.querySelectorAll('[data-contract=\"lista\"] [role=\"button\"]')]"
        .".find((x) => (x.textContent || '').includes('Receita painel browser')); if (l) l.click(); return 'OK'; })()");
    mfgTelEsperar($page, $dialogo, 'ABERTO', 'o painel abrir');
    expect($page->script($dialogo))->toBe('ABERTO');

    $page->script("(() => { document.activeElement?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); return 'OK'; })()");
    mfgTelEsperar($page, $dialogo, 'FECHADO', 'o painel fechar com Esc');
    expect($page->script($dialogo))->toBe('FECHADO');
})->after(fn () => mfgTelLimpar());

it('R-22 — a via de produção não mostra valor de compra', function () {
    mfgTelReceita('Receita via producao browser', 7.0, 30.0);
    $page = mfgTelAbrir('/manufacturing/recipe?q='.urlencode('Receita via producao browser'), 'Fabricação · Receitas', ['lista']);
    mfgTelEsperar($page, mfgTelLinhasCom('Receita via producao browser'), '1', 'a receita aparecer');

    $page->script("(() => { const l = [...document.querySelectorAll('[data-contract=\"lista\"] [role=\"button\"]')]"
        .".find((x) => (x.textContent || '').includes('Receita via producao browser')); if (l) l.click(); return 'OK'; })()");
    mfgTelEsperar($page, "(() => [...document.querySelectorAll('button')].some((b) => (b.textContent || '').trim() === 'Via de produção') ? 'TEM' : 'NAO')()", 'TEM', 'o botão Via de produção aparecer');

    $page->script("(() => { window.print = () => {}; const b = [...document.querySelectorAll('button')]"
        .".find((x) => (x.textContent || '').trim() === 'Via de produção'); b.click(); return 'OK'; })()");
    mfgTelEsperar($page, "(() => document.querySelector('.mfg-print-host') ? 'MONTOU' : 'NAO')()", 'MONTOU', 'a folha de produção montar');

    $folha = (string) $page->script("(() => document.querySelector('.mfg-print-host')?.textContent || '')()");
    expect($folha)->toContain('Receita via producao browser');
    expect($folha)->not->toContain('R$');
})->after(fn () => mfgTelLimpar());
