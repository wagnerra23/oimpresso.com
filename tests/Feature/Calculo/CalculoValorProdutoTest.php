<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use App\Utils\ProductUtil;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\EstoqueFixture;
use Tests\TestCase;

/**
 * Onda 1.4 — Dente de cálculo aplicado ao módulo PRODUTO (preço / margem / markup).
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A REGRA MESTRE (memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE") exige dupla
 * confirmação MANUAL de toda mudança que mexa em valor/estoque — controle humano que
 * NÃO sobrevive ao tempo. A Onda 1.4 já converteu esse controle em AUTOMÁTICO no coração
 * de Sells (`calculateInvoiceTotal` + num_uf) e nas Compras. Faltava o núcleo do PRODUTO:
 * o motor de MARGEM/MARKUP que decide `selling_price` a partir de `purchase_price` e
 * `profit_percent`, e o PREÇO por grupo/tabela. Zero teste até hoje (verificado 2026-07-03
 * em origin/main via git grep — nenhum Test.php cita calc_percentage / get_percent /
 * getVariationGroupPrice / calculateComboDetails).
 *
 * O QUE JÁ ESTAVA COBERTO (e este teste NÃO duplica):
 *   - `num_uf('204.99605')` em ISOLAMENTO      → tests/Unit/Utils/IncidentValorInfladoNumUfTest.php
 *   - heurística pt-BR geral do num_uf         → tests/Unit/Utils/NumUfHeuristicPtBRTest.php
 *   - round-trip num_uf(num_f) + totalizador   → tests/Feature/Calculo/CalculoValorSellsTest.php
 *   - valor+estoque no POST /purchases         → tests/Feature/Calculo/CalculoValorComprasTest.php
 *
 * O QUE ESTE TESTE ADICIONA (o motor de preço/margem do Produto — "indefeso" hoje):
 *   1. PROPERTY markup: `calc_percentage(p, pct, p) == p*(1 + pct/100)` — a fórmula EXATA
 *      "selling_price = purchase_price × (1 + profit_percent/100)" que o código usa em
 *      ProductUtil::updateProductFromPurchase (dpp_inc_tax, app/Utils/ProductUtil.php:911).
 *      Markup fracionário (ex 33,33 %) NÃO pode inflar de ordem de grandeza (mesma classe
 *      do incidente num_uf 2026-06-05).
 *   2. PROPERTY round-trip preço com imposto: `calc_percentage_base(calc_percentage(base,
 *      tax, base), tax) == base` — simetria inc-tax ↔ exc-tax (ProductUtil.php:911 ↔ :917).
 *   3. PROPERTY fechamento da margem: `get_percent(compra, venda)` → aplica de volta →
 *      devolve a venda (ProductUtil.php:911 ↔ :920). Prova que margem→preço→margem fecha.
 *   4. GOLDEN preço por grupo percentual: `getVariationGroupPrice` de um grupo `percentage`
 *      = calc_percentage(sell_price_inc_tax, %) (ProductUtil.php:1067) — DB-backed, skip-graceful.
 *   5. GOLDEN combo/kit: `calculateComboDetails` → qty_required = num_uf(quantity)×multiplier,
 *      não infla (ProductUtil.php:623) — DB-backed, skip-graceful.
 *   6. DISCRIMINAÇÃO RED: prova que uma versão de num_uf que strippa o ponto decimal (o vetor
 *      do incidente) inflaria um `selling_price` fracionário — o teste tem poder de pegar a regressão.
 *
 * ⛔ TEST-ONLY: este arquivo NÃO altera nenhum método de cálculo. Qualquer mudança em
 *    calc_percentage / calc_percentage_base / get_percent / num_uf ou nos métodos de preço
 *    é mudança de valor em prod → US separada sob REGRA MESTRE (dupla confirmação por 2
 *    caminhos + tabela antes→depois + OK [W]). Este dente só CARACTERIZA o comportamento atual.
 */
class CalculoValorProdutoTest extends TestCase
{
    use DatabaseTransactions;

    private Util $util;

    private ProductUtil $productUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->util = app(Util::class);
        $this->productUtil = app(ProductUtil::class);

        // Session canon BRL (paridade biz=4 Larissa / biz=1 dogfood):
        // ponto = milhar, vírgula = decimal. currency_precision default 2.
        session([
            'currency' => [
                'symbol' => 'R$',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
        ]);
    }

    // =========================================================================
    // 1) PROPERTY — markup: selling_price = purchase_price × (1 + profit%/100)
    //    (motor: calc_percentage($p, $pct, $p) — ProductUtil::updateProductFromPurchase:911)
    // =========================================================================

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     *   [purchase_price, profit_percent, selling_price esperado]
     */
    public static function markupProvider(): array
    {
        return [
            'sem margem'            => [100.00, 0.0, 100.00],
            'markup 50%'            => [100.00, 50.0, 150.00],
            'markup 100%'          => [10.00, 100.0, 20.00],
            'markup fracionario'    => [100.00, 33.33, 133.33],
            'centavos'              => [0.50, 20.0, 0.60],
            'preco do incidente'    => [227.90, 10.05, 250.80395],
            'milhar'                => [1234.56, 25.0, 1543.20],
            'margem alta'           => [80.00, 250.0, 280.00],
        ];
    }

    /**
     * A fórmula de markup que o código aplica (ProductUtil.php:911 — dpp_inc_tax) tem que
     * bater com a definição de negócio "preço = custo × (1 + margem/100)" dentro da precisão
     * de moeda. Se `calc_percentage` regredisse (ex trocasse `+addition` por outra base), o
     * preço de venda sairia errado silenciosamente em TODO produto novo/editado.
     */
    #[Test]
    #[DataProvider('markupProvider')]
    public function property_markup_bate_com_a_definicao_de_negocio(float $purchase, float $profit, float $expected): void
    {
        // Caminho REAL do código (addition = a própria base → base × (1 + pct/100)).
        $viaEngine = $this->util->calc_percentage($purchase, $profit, $purchase);

        // Caminho INDEPENDENTE (fórmula fechada da task), pra dupla confirmação.
        $viaFormula = $purchase * (1 + $profit / 100);

        $this->assertEqualsWithDelta(
            $expected,
            $viaEngine,
            0.0005,
            "Markup do motor divergiu: calc_percentage({$purchase}, {$profit}, {$purchase}) = {$viaEngine} (esperado {$expected})."
        );
        $this->assertEqualsWithDelta(
            $viaFormula,
            $viaEngine,
            0.0005,
            'Os dois caminhos independentes (motor vs fórmula fechada) têm que convergir — dupla confirmação REGRA MESTRE.'
        );

        // Teto de sanidade (classe do incidente num_uf): markup fracionário NÃO pode saltar
        // de ordem de grandeza. Com margem finita, o preço fica entre o custo e um múltiplo são.
        $this->assertLessThanOrEqual(
            $purchase * (1 + $profit / 100) + 0.01,
            $viaEngine,
            "selling_price {$viaEngine} inflou acima do markup esperado — vetor de inflação (num_uf/base errada)."
        );
        $this->assertGreaterThanOrEqual(
            $purchase - 0.01,
            $viaEngine,
            'Markup positivo NUNCA pode devolver preço menor que o custo.'
        );
    }

    // =========================================================================
    // 2) PROPERTY — round-trip preço com imposto (inc-tax ↔ exc-tax)
    //    calc_percentage_base(calc_percentage(base, tax, base), tax) == base
    //    (ProductUtil.php:911 grava dpp_inc_tax ; :917 recompõe default_sell_price)
    // =========================================================================

    /**
     * @return array<string, array{0: float, 1: float}>  [base_exc_tax, tax_percent]
     */
    public static function baseImpostoProvider(): array
    {
        return [
            'sem imposto'      => [100.00, 0.0],
            'icms 10%'         => [100.00, 10.0],
            'imposto 17%'      => [147.77, 17.0],
            'imposto 27.5%'    => [227.90, 27.5],
            'milhar'           => [1234.56, 12.0],
        ];
    }

    #[Test]
    #[DataProvider('baseImpostoProvider')]
    public function property_preco_com_imposto_faz_round_trip(float $base, float $tax): void
    {
        $incTax = $this->util->calc_percentage($base, $tax, $base);   // base → preço COM imposto
        $roundTrip = $this->util->calc_percentage_base($incTax, $tax); // COM imposto → base de volta

        $this->assertEqualsWithDelta(
            $base,
            $roundTrip,
            0.0005,
            "Round-trip preço quebrou: base {$base} → inc {$incTax} → base {$roundTrip} (esperado {$base})."
        );
        // O preço com imposto nunca é menor que a base (imposto ≥ 0).
        $this->assertGreaterThanOrEqual($base - 0.0005, $incTax);
    }

    // =========================================================================
    // 3) PROPERTY — fechamento da margem (get_percent inverte o markup)
    //    profit% = get_percent(compra, venda) ; aplicar de volta devolve a venda
    //    (ProductUtil.php:920 grava profit_percent ; :911 recompõe o preço)
    // =========================================================================

    /**
     * @return array<string, array{0: float, 1: float}>  [purchase_price, selling_price]
     */
    public static function margemProvider(): array
    {
        return [
            'margem 50%'      => [100.00, 150.00],
            'sem margem'      => [100.00, 100.00],
            'margem 25%'      => [80.00, 100.00],
            'prejuizo'        => [100.00, 90.00],   // venda < custo → margem negativa
            'fracionaria'     => [227.90, 250.80],
        ];
    }

    #[Test]
    #[DataProvider('margemProvider')]
    public function property_margem_fecha_o_loop(float $purchase, float $selling): void
    {
        $profit = $this->util->get_percent($purchase, $selling);          // margem implícita
        $reconstituido = $this->util->calc_percentage($purchase, $profit, $purchase); // preço de volta

        $this->assertEqualsWithDelta(
            $selling,
            $reconstituido,
            0.0005,
            "Margem não fechou: compra {$purchase}, venda {$selling}, margem {$profit}% → reconstituiu {$reconstituido}."
        );

        // Consistência de sinal: venda>custo ⇒ margem>0 ; venda<custo ⇒ margem<0.
        if ($selling > $purchase) {
            $this->assertGreaterThan(0, $profit, 'Venda acima do custo tem que dar margem positiva.');
        } elseif ($selling < $purchase) {
            $this->assertLessThan(0, $profit, 'Venda abaixo do custo tem que dar margem negativa (prejuízo).');
        }
    }

    // =========================================================================
    // 4) GOLDEN (DB) — preço por grupo PERCENTUAL: getVariationGroupPrice
    //    price_type='percentage' → calc_percentage(sell_price_inc_tax, %) (ProductUtil.php:1067)
    // =========================================================================

    /**
     * Uma tabela de preço "percentage" de 90 aplicada a uma variação de 200 tem que dar 180
     * (90 % de 200) — e NUNCA inflar. Prova o método nomeado ponta-a-ponta (não só o motor).
     * Skip-graceful em sqlite/sem-seed (padrão ADR 0101 — o gate real é a lane MySQL + CT100).
     */
    #[Test]
    public function golden_get_variation_group_price_percentual_nao_infla(): void
    {
        if (! EstoqueFixture::schemaReady()) {
            $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
        }

        $businessId = EstoqueFixture::businessId();
        $produto = EstoqueFixture::singleProduct($businessId);
        $variationId = $produto->variationId();

        // Estado inicial conhecido e INDEPENDENTE (não pelo método sob teste): preço da variação = 200.
        DB::table('variations')->where('id', $variationId)->update(['sell_price_inc_tax' => 200]);

        $priceGroupId = (int) DB::table('selling_price_groups')->insertGetId([
            'name' => 'CVP-GRUPO-'.uniqid(),
            'business_id' => $businessId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('variation_group_prices')->insert([
            'variation_id' => $variationId,
            'price_group_id' => $priceGroupId,
            'price_inc_tax' => 90,          // o valor guardado é o PERCENTUAL, pra price_type=percentage
            'price_type' => 'percentage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $out = $this->productUtil->getVariationGroupPrice($variationId, $priceGroupId, null);

        // 90 % de 200 = 180. Se num_uf/calc_percentage inflasse, viraria dezenas de milhar.
        $this->assertEqualsWithDelta(
            180.0,
            (float) $out['price_inc_tax'],
            0.0005,
            "Preço do grupo percentual = {$out['price_inc_tax']} (esperado 180 = 90% de 200)."
        );
        // Sem tax_id, exc-tax = inc-tax.
        $this->assertEqualsWithDelta(180.0, (float) $out['price_exc_tax'], 0.0005);
        // Teto de sanidade: nunca acima do preço cheio da variação.
        $this->assertLessThanOrEqual(200.0 + 0.01, (float) $out['price_inc_tax'], 'Grupo percentual < 100% não pode passar do preço cheio.');
    }

    // =========================================================================
    // 5) GOLDEN (DB) — combo/kit: calculateComboDetails, qty_required não infla
    //    qty_required = num_uf(quantity) × multiplier (ProductUtil.php:623)
    // =========================================================================

    /**
     * Uma linha de combo com quantidade "2" e mesma unidade (multiplier = 1) tem que exigir
     * 2 — e uma quantidade fracionária "1,5" tem que virar 1.5, não 15 nem 1500 (classe num_uf).
     * Skip-graceful (padrão ADR 0101).
     */
    #[Test]
    public function golden_calculate_combo_details_quantidade_nao_infla(): void
    {
        if (! EstoqueFixture::schemaReady()) {
            $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
        }

        $businessId = EstoqueFixture::businessId();
        $componente = EstoqueFixture::singleProduct($businessId);

        $comboVariations = [
            ['variation_id' => $componente->variationId(), 'unit_id' => $componente->unitId, 'quantity' => '2'],
        ];

        $details = $this->productUtil->calculateComboDetails(
            EstoqueFixture::locationId($businessId),
            $comboVariations
        );

        $this->assertCount(1, $details);
        $this->assertEqualsWithDelta(
            2.0,
            (float) $details[0]['qty_required'],
            0.0005,
            "qty_required = {$details[0]['qty_required']} (esperado 2) — multiplier de mesma unidade tem que ser 1."
        );
        $this->assertSame($componente->variationId(), (int) $details[0]['variation_id']);

        // Quantidade fracionária pt-BR: "1,5" → 1.5 (não 15, não 1500).
        $comboFrac = [
            ['variation_id' => $componente->variationId(), 'unit_id' => $componente->unitId, 'quantity' => '1,5'],
        ];
        $detalhesFrac = $this->productUtil->calculateComboDetails(
            EstoqueFixture::locationId($businessId),
            $comboFrac
        );
        $this->assertEqualsWithDelta(
            1.5,
            (float) $detalhesFrac[0]['qty_required'],
            0.0005,
            "quantidade '1,5' virou {$detalhesFrac[0]['qty_required']} — num_uf inflou a quantidade do combo."
        );
    }

    // =========================================================================
    // 6) DISCRIMINAÇÃO RED — prova que o strip do ponto inflaria um selling_price
    // =========================================================================

    /**
     * TEST-ONLY não pode mutar o código de prod pra provar o RED. Reproduzimos INLINE a lógica
     * legacy que strippava o ponto decimal (`str_replace('.','')`) — o vetor exato do incidente
     * 2026-06-05 — e travamos o contrato: a versão bugada infla um preço fracionário, a atual não.
     * Enquanto os dois caminhos DIVERGIREM, o dente tem poder de pegar a regressão; se convergirem
     * (num_uf voltar a strippar), este assert quebra = RED consciente.
     */
    #[Test]
    public function discriminacao_strip_do_ponto_inflaria_selling_price(): void
    {
        $sellingPriceFracionario = '204.99605'; // como o front manda um preço/total fracionado

        // Versão BUGADA (legacy): ponto = milhar SEMPRE → infla ~10^5×.
        $bugado = (float) str_replace('.', '', $sellingPriceFracionario); // 20499605.0

        // Versão ATUAL (fix #2279): heurística — 5 casas após o ponto = decimal.
        $atual = $this->util->num_uf($sellingPriceFracionario); // 204.99605

        $this->assertEqualsWithDelta(20499605.0, $bugado, 0.001, 'Sanidade do vetor: a lógica legacy inflava mesmo.');
        $this->assertEqualsWithDelta(204.99605, $atual, 0.001, 'A versão atual NÃO pode inflar o preço de venda.');

        $this->assertGreaterThan(
            1000.0,
            abs($bugado - $atual),
            'num_uf convergiu com a versão que strippa o ponto — regressão do incidente 2026-06-05 no motor de preço.'
        );
    }
}
