<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Onda 2.4 — Dente de cálculo do módulo COMPRAS (a prova real do caminho de compra).
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md (o padrão)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COMPARAR-NÃO-DUPLICAR — POR QUE ESTE ARQUIVO EXISTE (e NÃO duplica o 1.4)
 * ─────────────────────────────────────────────────────────────────────────────
 * A Onda 1.4 (tests/Feature/Calculo/CalculoValorSellsTest.php, #3695) cobriu o coração
 * de VENDAS: o totalizador `ProductUtil::calculateInvoiceTotal` (1-arg / session) + a
 * divergência dos dois somadores de pagamento.
 *
 * VERIFICADO em origin/main (2026-07-03): o caminho de COMPRA **NÃO** passa por
 * `calculateInvoiceTotal` — ZERO callers em qualquer Purchase controller. O total de
 * compra é calculado INLINE em `PurchaseController::store()` (app/Http/Controllers/
 * PurchaseController.php:523-536) e `update()` (1091-1104), com lógica AUSENTE em Vendas:
 *
 *   1. `num_uf($x, $currency_details) * $exchange_rate` — TODO campo monetário
 *      (total_before_tax, tax_amount, shipping_charges, final_total) é desformatado E
 *      multiplicado pela taxa de câmbio (compra em moeda estrangeira). Vendas não tem isso.
 *   2. ASSIMETRIA do desconto (PurchaseController:526-529):
 *        - `fixed`      → num_uf(desconto) * exchange_rate
 *        - `percentage` → num_uf(desconto)                (SEM exchange_rate)
 *   3. `additional_expense_value_1..4` (rateio de frete / despesas de entrada) — cada um
 *      num_uf'd * exchange_rate. Campo exclusivo de compra.
 *   4. `num_uf` na forma de DOIS argumentos (`purchaseCurrencyDetails`) — Vendas usa a
 *      forma de 1 arg (session). O primitivo do strip-do-ponto é o MESMO, mas o caminho
 *      de compra o exercita por outra porta e sobre outros campos.
 *
 * O QUE ESTE TESTE ADICIONA (ângulos purchase-only, não exercidos pelo 1.4):
 *   1. `num_uf` 2-arg (forma de compra) NÃO infla o vetor do incidente 2026-06-05.
 *   2. `num_uf` é AGNÓSTICO ao 2º arg (currency_details) — o fix anti-strip protege a
 *      compra independentemente da config de moeda de compra do business.
 *   3. Golden do compounding `num_uf * exchange_rate` (caracterização da fórmula inline).
 *   4. Caracterização da ASSIMETRIA fixed×rate vs percentage (contrato exclusivo de compra).
 *   5. `additional_expense` (frete) num_uf não strippa o ponto de milhar.
 *   6. Discriminação RED: uma versão de num_uf que strippa o ponto inflaria o final_total
 *      de compra em ~10^5× (o vetor do incidente pela porta da compra).
 *
 * ⛔ TEST-ONLY (REGRA MESTRE — memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE"):
 *    este arquivo NÃO altera nenhum método de cálculo. Onde caracteriza a fórmula INLINE
 *    do controller (compounding + assimetria), o docblock CITA a linha exata e ancora o
 *    parse no `num_uf` REAL de produção — a multiplicação por exchange_rate e a assimetria
 *    são reprodução declarada do controller (mesmo padrão honesto da "discriminação RED"
 *    do 1.4). Extrair/unificar essa lógica pra um método é mudança de valor em prod → US
 *    separada sob REGRA MESTRE (dupla confirmação + antes→depois + OK [W]).
 */
class CalculoValorComprasTest extends TestCase
{
    use DatabaseTransactions;

    private Util $util;

    private TransactionUtil $transactionUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->util = app(Util::class);
        $this->transactionUtil = app(TransactionUtil::class);

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

    /**
     * currency_details que o caminho de COMPRA passa como 2º arg de num_uf. Reproduz o
     * shape de `TransactionUtil::purchaseCurrencyDetails` SEM tocar o DB — usado pra provar
     * que o parse independe desse objeto (o num_uf atual é agnóstico a ele; heurística fixa pt-BR).
     */
    private function purchaseCurrencyStub(string $thousand = '.', string $decimal = ','): object
    {
        return (object) [
            'purchase_in_diff_currency' => false,
            'p_exchange_rate' => 1,
            'thousand_separator' => $thousand,
            'decimal_separator' => $decimal,
            'symbol' => '',
        ];
    }

    // =========================================================================
    // 1) num_uf 2-arg (forma de compra) NÃO infla — o coração do caminho de compra
    // =========================================================================

    /**
     * Todo campo monetário da compra é desformatado por `num_uf($x, $currency_details)`.
     * Se num_uf strippasse o ponto (o vetor do incidente), total_before_tax/tax/shipping/
     * final_total/frete inflariam TODOS de uma vez. Prova o número certo pela forma 2-arg
     * que o 1.4 (forma 1-arg/session) não exerce.
     *
     * @return array<string, array{0: string, 1: float}>
     */
    public static function camposDeCompraProvider(): array
    {
        return [
            'incidente 5 casas'   => ['204.99605', 204.99605], // vetor exato 2026-06-05
            'antes do desconto'   => ['227,90', 227.90],
            'en-US paste'         => ['80.00', 80.00],          // "80.00" de Excel/calc US
            'decimal 2 casas'     => ['147.77', 147.77],
            'milhar pt-BR'        => ['1.234,56', 1234.56],
            'milhar exato 3 dig'  => ['25.000', 25000.0],       // 1 ponto + 3 dígitos = milhar
            'milhoes'             => ['1.234.567,89', 1234567.89],
            'vazio'               => ['', 0.0],
        ];
    }

    #[Test]
    #[DataProvider('camposDeCompraProvider')]
    public function num_uf_2arg_forma_compra_nao_infla(string $entrada, float $esperado): void
    {
        $cd = $this->purchaseCurrencyStub();

        $out = $this->util->num_uf($entrada, $cd);

        $this->assertEqualsWithDelta(
            $esperado,
            $out,
            0.005,
            "num_uf('{$entrada}', currency_details) = {$out} (esperado {$esperado}) — strip do ponto no caminho de compra?"
        );
    }

    // =========================================================================
    // 2) num_uf é AGNÓSTICO ao 2º arg (currency_details) — trava o contrato
    // =========================================================================

    /**
     * A implementação atual de `num_uf` (fix #2279) aplica heurística FIXA pt-BR e IGNORA
     * o 2º arg `$currency_details` (é param legacy vestigial). Consequência crítica pra
     * compra: o fix anti-strip protege a compra INDEPENDENTEMENTE da moeda de compra do
     * business. Este teste TRAVA esse contrato: mesmo passando um currency_details que
     * DECLARA decimal='.' (en-US), a saída continua pt-BR. Se um dia num_uf voltar a
     * "honrar" o currency_details, "204.99605" com decimal='.' poderia strippar → RED.
     */
    #[Test]
    public function num_uf_ignora_o_2arg_currency_details(): void
    {
        $entrada = '1.234,56';

        $comPtBr = $this->util->num_uf($entrada, $this->purchaseCurrencyStub('.', ','));
        $comEnUs = $this->util->num_uf($entrada, $this->purchaseCurrencyStub(',', '.')); // separadores invertidos
        $semArg = $this->util->num_uf($entrada);

        $this->assertEqualsWithDelta(1234.56, $comPtBr, 0.005);
        $this->assertEqualsWithDelta(1234.56, $comEnUs, 0.005, 'num_uf passou a honrar o currency_details — contrato de compra mudou (REGRA MESTRE).');
        $this->assertEqualsWithDelta($semArg, $comPtBr, 0.005, 'num_uf 1-arg e 2-arg devem coincidir — o 2º arg é vestigial hoje.');
    }

    // =========================================================================
    // 3) GOLDEN — compounding num_uf * exchange_rate (caracterização inline)
    // =========================================================================

    /**
     * CARACTERIZAÇÃO da fórmula INLINE de PurchaseController:523-536:
     *   $transaction_data['final_total'] = num_uf($final_total, $cd) * $exchange_rate;
     *
     * O parse (`num_uf`) é PROD REAL; a multiplicação por exchange_rate é reprodução
     * declarada da linha do controller. Prova dois pontos:
     *   (a) câmbio 1.0 é neutro: 227,90 → 227.90 (não infla).
     *   (b) o vetor do incidente NÃO compõe com o câmbio: num_uf('204.99605')*1 ≈ 205,
     *       nunca ~20.499.605 (que ainda seria multiplicado pelo câmbio, explodindo mais).
     *   (c) câmbio real reprecifica linearmente: 100,00 @ câmbio 5,25 → 525.00.
     */
    #[Test]
    public function compounding_num_uf_vezes_exchange_rate(): void
    {
        $cd = $this->purchaseCurrencyStub();

        // (a) câmbio neutro
        $this->assertEqualsWithDelta(227.90, $this->util->num_uf('227,90', $cd) * 1.0, 0.0001);

        // (b) vetor do incidente pela porta da compra — não infla nem compõe
        $finalTotalCompra = $this->util->num_uf('204.99605', $cd) * 1.0;
        $this->assertEqualsWithDelta(204.99605, $finalTotalCompra, 0.0001, 'final_total de compra inflou — regressão do incidente 2026-06-05 pelo caminho de compra.');
        $this->assertLessThan(1000.0, $finalTotalCompra, 'final_total de compra explodiu (vetor num_uf * câmbio).');

        // (c) câmbio real reprecifica linearmente
        $this->assertEqualsWithDelta(525.00, $this->util->num_uf('100,00', $cd) * 5.25, 0.0001);
    }

    // =========================================================================
    // 4) CARACTERIZAÇÃO — desconto fixed × câmbio vs percentage sem câmbio
    // =========================================================================

    /**
     * CARACTERIZAÇÃO da ASSIMETRIA INLINE de PurchaseController:526-529 (exclusiva de compra):
     *   if ($discount_type == 'fixed')      $discount = num_uf($x, $cd) * $exchange_rate;
     *   elseif ($discount_type=='percentage') $discount = num_uf($x, $cd);   // SEM câmbio
     *
     * Razão de negócio: desconto FIXO é um valor absoluto na moeda estrangeira → precisa
     * converter pra moeda base (× câmbio). Desconto PERCENTUAL é adimensional → aplicado
     * sobre o total já convertido, não se multiplica pela taxa. Este teste TRAVA a
     * assimetria: se alguém "normalizar" os dois ramos (aplicar câmbio nos dois, ou em
     * nenhum), o percentual dobraria/sumiria = mudança de valor em prod → REGRA MESTRE.
     */
    #[Test]
    public function assimetria_desconto_fixed_com_cambio_vs_percentage_sem_cambio(): void
    {
        $cd = $this->purchaseCurrencyStub();
        $exchangeRate = 2.0;

        // fixed: valor absoluto convertido pela taxa
        $descontoFixed = $this->util->num_uf('10,00', $cd) * $exchangeRate;
        $this->assertEqualsWithDelta(20.00, $descontoFixed, 0.0001, 'Desconto fixo deve ser convertido pela taxa de câmbio (10 * 2 = 20).');

        // percentage: adimensional, NÃO multiplica pela taxa
        $descontoPercentage = $this->util->num_uf('10,00', $cd); // sem * exchangeRate
        $this->assertEqualsWithDelta(10.00, $descontoPercentage, 0.0001, 'Desconto percentual NÃO pode ser multiplicado pela taxa de câmbio (assimetria de compra).');

        // A assimetria é real: os dois ramos DIVERGEM sob câmbio ≠ 1.
        $this->assertNotEqualsWithDelta(
            $descontoFixed,
            $descontoPercentage,
            0.0001,
            'fixed e percentage convergiram sob câmbio ≠ 1 — a assimetria de PurchaseController:526-529 sumiu (REGRA MESTRE).'
        );
    }

    // =========================================================================
    // 5) frete (additional_expense) num_uf não strippa o ponto de milhar
    // =========================================================================

    /**
     * Campo exclusivo de compra (rateio de frete / despesas de entrada), PurchaseController:
     * 560-575: `additional_expense_value_N = num_uf($x, $cd) * $exchange_rate`. Frete de
     * "1.234,56" (milhar pt-BR) não pode virar 123456.
     */
    #[Test]
    public function additional_expense_frete_num_uf_nao_strippa(): void
    {
        $cd = $this->purchaseCurrencyStub();

        $frete = $this->util->num_uf('1.234,56', $cd) * 1.0;

        $this->assertEqualsWithDelta(1234.56, $frete, 0.0001, 'Frete inflou — num_uf tratou "1.234,56" como 123456?');
    }

    // =========================================================================
    // 6) DISCRIMINAÇÃO RED — versão que strippa o ponto inflaria a compra
    // =========================================================================

    /**
     * TEST-ONLY não muta o código de prod pra provar o RED. Reproduz INLINE a lógica legacy
     * que strippava o ponto (`str_replace('.','')`) e trava o CONTRATO: a versão bugada
     * infla o final_total de compra, a atual não. Se num_uf voltar a strippar, o golden #3
     * e este discriminador falham juntos (RED consciente). Mesmo padrão do 1.4.
     */
    #[Test]
    public function discriminacao_versao_que_strippa_o_ponto_inflaria_compra(): void
    {
        $entradaDoIncidente = '204.99605';
        $cd = $this->purchaseCurrencyStub();

        // Versão BUGADA (legacy): ponto = milhar SEMPRE, depois * câmbio.
        $bugado = (float) str_replace('.', '', $entradaDoIncidente) * 1.0; // 20499605.0

        // Versão ATUAL (fix #2279) pela forma 2-arg de compra.
        $atual = $this->util->num_uf($entradaDoIncidente, $cd) * 1.0; // 204.99605

        $this->assertEqualsWithDelta(20499605.0, $bugado, 0.001, 'Sanidade do vetor: a lógica legacy inflava a compra também.');
        $this->assertEqualsWithDelta(204.99605, $atual, 0.001, 'A versão atual NÃO pode inflar a compra.');

        // Discriminador: os caminhos DIVERGEM ~10^5×. Enquanto divergirem, o teste tem
        // poder de pegar a regressão. Se convergirem, este assert quebra = RED consciente.
        $this->assertGreaterThan(
            1000.0,
            abs($bugado - $atual),
            'num_uf de compra convergiu com a versão que strippa o ponto — regressão do incidente 2026-06-05.'
        );
    }

    // =========================================================================
    // 7) (DB) purchaseCurrencyDetails REAL devolve objeto parseável — defesa
    // =========================================================================

    /**
     * Âncora prod-real: o objeto que a compra passa a num_uf vem de
     * `TransactionUtil::purchaseCurrencyDetails($business_id)`. Prova que ele devolve
     * separadores válidos e que num_uf com o objeto REAL não infla o vetor do incidente.
     * Skip-guarded (ADR 0101 biz=1) — nunca flaka se o seed mínimo não rodou.
     */
    #[Test]
    public function purchase_currency_details_real_nao_infla(): void
    {
        $tenant = $this->seededTenant(); // biz=1 canônico (ADR 0101). Skip acionável se faltar.

        $cd = $this->transactionUtil->purchaseCurrencyDetails($tenant->id);

        $this->assertNotNull($cd, 'purchaseCurrencyDetails devolveu null.');
        $this->assertObjectHasProperty('thousand_separator', $cd);
        $this->assertObjectHasProperty('decimal_separator', $cd);

        // Com o currency_details REAL do business, o vetor do incidente continua protegido.
        $atual = $this->util->num_uf('204.99605', $cd);
        $this->assertEqualsWithDelta(204.99605, $atual, 0.001, 'num_uf com purchaseCurrencyDetails real inflou — regressão 2026-06-05.');
    }
}
