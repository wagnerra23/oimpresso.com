<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Onda 1.4 — Dente de cálculo (a PROVA real).
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A REGRA MESTRE (memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE") exige dupla
 * confirmação MANUAL de toda mudança de cálculo — controle humano que NÃO sobrevive
 * ao tempo. Este dente converte esse controle manual em controle AUTOMÁTICO no coração
 * de Sells: o totalizador `ProductUtil::calculateInvoiceTotal` (roda `num_uf`) e a
 * divergência dos dois somadores de pagamento. É o UC-S02 do contrato da tela
 * (resources/js/Pages/Sells/Create.casos.md).
 *
 * O QUE JÁ ESTAVA COBERTO (e este teste NÃO duplica):
 *   - `num_uf('204.99605')` em ISOLAMENTO → tests/Unit/Utils/IncidentValorInfladoNumUfTest.php
 *   - heurística pt-BR geral do num_uf   → tests/Unit/Utils/NumUfHeuristicPtBRTest.php
 *
 * O QUE ESTE TESTE ADICIONA (ângulos novos — "0 teste hoje" na Onda 1.4):
 *   1. Property `num_uf(num_f(x)) == x` — simetria formata↔desformata (round-trip),
 *      invariante geral que os testes de tabela acima NÃO exercem.
 *   2. Golden no TOTALIZADOR REAL `calculateInvoiceTotal` (não o num_uf solto): a venda
 *      227,90 − 10,05% tem que dar final_total 204.99605 e NUNCA ~20.499.605.
 *   3. Discriminação RED: prova que o teste FALHA contra uma versão de num_uf que
 *      strippa o ponto decimal (o vetor do incidente 2026-06-05).
 *   4. Caracterização da divergência `getTotalPaid` (líquido, desconta devolução) ≠
 *      `getTotalAmountPaid` (bruto, ignora is_return) — trava o comportamento ATUAL
 *      de cada método e documenta qual é a fonte de verdade.
 *
 * ⛔ TEST-ONLY: este arquivo NÃO altera nenhum método de cálculo. Unificar
 *    getTotalPaid/getTotalAmountPaid é mudança de valor em prod → US separada sob
 *    REGRA MESTRE (dupla confirmação + antes→depois + OK [W]).
 */
class CalculoValorSellsTest extends TestCase
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
    // 1) PROPERTY — num_uf(num_f(x)) == x  (round-trip formata↔desformata)
    // =========================================================================

    /**
     * Simetria: desformatar o que foi formatado devolve o mesmo número, pra valores
     * na precisão de moeda (2 casas). Pega o strip do ponto: se num_uf voltasse a
     * remover o "." de milhar sem heurística, "1.234,56" → 1234.56 quebraria (viraria
     * outra ordem de grandeza) e o round-trip falharia.
     *
     * @return array<string, array{0: float}>
     */
    public static function valoresMoedaProvider(): array
    {
        return [
            'zero'                 => [0.0],
            'centavos'             => [0.05],
            'dez'                  => [10.05],
            'oitenta en-US paste'  => [80.00],
            'cento e quarenta'     => [147.77],
            'antes do desconto'    => [227.90],
            'milhar'               => [1329.05],
            'milhar cheio'         => [1234.56],
            'exatamente 3 digitos' => [25000.00],
            'milhoes'              => [1234567.89],
            'quase um milhao'      => [999999.99],
            'devolucao negativa'   => [-50.25],
        ];
    }

    #[Test]
    #[DataProvider('valoresMoedaProvider')]
    public function property_num_uf_desfaz_num_f(float $x): void
    {
        $formatted = $this->util->num_f($x);        // ex 1234.56 → "1.234,56"
        $roundTrip = $this->util->num_uf($formatted); // "1.234,56" → 1234.56

        $this->assertEqualsWithDelta(
            $x,
            $roundTrip,
            0.005,
            "Round-trip quebrou: num_f({$x}) = '{$formatted}', num_uf('{$formatted}') = {$roundTrip} (esperado {$x})."
        );
    }

    // =========================================================================
    // 2) GOLDEN — o totalizador REAL calculateInvoiceTotal não infla
    // =========================================================================

    /**
     * O vetor EXATO do incidente 2026-06-05, agora pelo caminho do totalizador central
     * (não pelo num_uf solto): venda de 227,90 com desconto percentual de 10,05%.
     *
     *   total_before_tax = 227.90
     *   discount         = 10.05% * 227.90 = 22.90395
     *   final_total      = 227.90 - 22.90395 = 204.99605
     *
     * Se num_uf strippasse o ponto, unit_price_inc_tax viraria 22790 e o final_total
     * explodiria pra dezenas de milhar. Provamos o número certo E o teto de sanidade.
     */
    #[Test]
    public function calculate_invoice_total_desconto_percentual_nao_infla(): void
    {
        $products = [
            ['unit_price_inc_tax' => '227.90', 'quantity' => '1'],
        ];
        $discount = ['discount_type' => 'percentage', 'discount_amount' => '10.05'];

        $out = $this->productUtil->calculateInvoiceTotal($products, null, $discount);

        // Número exato (dupla confirmação: bate com o cálculo à mão acima).
        $this->assertEqualsWithDelta(
            227.90,
            $out['total_before_tax'],
            0.0001,
            "total_before_tax inflou pra {$out['total_before_tax']} (esperado 227.90) — num_uf tratou '227.90' como milhar?"
        );
        $this->assertEqualsWithDelta(22.90395, $out['discount'], 0.0001);
        $this->assertEqualsWithDelta(
            204.99605,
            $out['final_total'],
            0.0001,
            "final_total = {$out['final_total']} (esperado 204.99605) — regressão do incidente 2026-06-05."
        );

        // Teto de sanidade: desconto só REDUZ; final_total NUNCA pode passar do bruto.
        $this->assertLessThanOrEqual(
            227.90 + 0.01,
            $out['final_total'],
            "final_total {$out['final_total']} > total_before_tax — cálculo inflou (vetor num_uf)."
        );
    }

    // =========================================================================
    // 3) DISCRIMINAÇÃO RED — prova que o teste falha contra o strip do ponto
    // =========================================================================

    /**
     * TEST-ONLY não pode mutar o código de prod pra provar o RED. Em vez disso,
     * reproduzimos INLINE a lógica legacy que strippava o ponto (`str_replace('.','')`)
     * e travamos o CONTRATO: a versão bugada infla, a versão atual não. Se um dia
     * num_uf voltar a strippar, a asserção `assertNotEquals` do valor-bug + o golden
     * acima falham juntos (RED). É o discriminador que garante que o green tem valor.
     */
    #[Test]
    public function discriminacao_versao_que_strippa_o_ponto_seria_red(): void
    {
        $entradaDoIncidente = '204.99605';

        // Como a versão BUGADA (legacy) tratava: ponto = milhar SEMPRE.
        $bugado = (float) str_replace('.', '', $entradaDoIncidente); // 20499605.0

        // A versão ATUAL (fix #2279): heurística — 5 casas após o ponto = decimal.
        $atual = $this->util->num_uf($entradaDoIncidente); // 204.99605

        $this->assertEqualsWithDelta(20499605.0, $bugado, 0.001, 'Sanidade do vetor: a lógica legacy inflava mesmo.');
        $this->assertEqualsWithDelta(204.99605, $atual, 0.001, 'A versão atual NÃO pode inflar.');

        // O discriminador: os dois caminhos DIVERGEM em ~10^5×. Enquanto divergirem, o
        // teste tem poder de pegar a regressão. Se convergirem (num_uf voltar a strippar),
        // este assert quebra = RED consciente.
        $this->assertGreaterThan(
            1000.0,
            abs($bugado - $atual),
            'num_uf convergiu com a versão que strippa o ponto — regressão do incidente 2026-06-05.'
        );
    }

    // =========================================================================
    // 4) CARACTERIZAÇÃO — getTotalPaid (líquido) ≠ getTotalAmountPaid (bruto)
    // =========================================================================

    /**
     * Trava o comportamento ATUAL dos dois somadores e documenta a fonte de verdade.
     *
     *   getTotalPaid       = SUM(IF(is_return=0, amount, amount*-1))  → LÍQUIDO (desconta devolução)
     *   getTotalAmountPaid = SUM(amount)                              → BRUTO   (ignora is_return)
     *
     * FONTE DE VERDADE do status de pagamento é o LÍQUIDO: `calculatePaymentStatus`
     * chama `getTotalPaid` (app/Utils/TransactionUtil.php). O bruto é usado em outros
     * pontos. UNIFICAR os dois é mudança de valor em prod → US separada sob REGRA MESTRE
     * (dupla confirmação + antes→depois + OK [W]); este teste só CARACTERIZA a diferença.
     */
    #[Test]
    public function divergencia_pagamento_liquido_vs_bruto_com_devolucao(): void
    {
        $tenant = $this->seededTenant(); // biz=1 canônico (ADR 0101). Skip acionável se faltar.

        $user = \App\User::where('business_id', $tenant->id)->first();
        if (! $user) {
            $this->markTestSkipped('Sem user no business canônico.');
        }
        $location = DB::table('business_locations')->where('business_id', $tenant->id)->first();
        if (! $location) {
            $this->markTestSkipped('Sem business_location no seed.');
        }
        $contact = DB::table('contacts')->where('business_id', $tenant->id)->where('type', '!=', 'lead')->first();
        if (! $contact) {
            $this->markTestSkipped('Sem contact no seed.');
        }

        $transactionUtil = app(\App\Utils\TransactionUtil::class);

        // ── Caso A: venda COM devolução (is_return=1) → os dois DIVERGEM ────────────
        $txA = $this->makeSell($tenant->id, (int) $location->id, (int) $contact->id, (int) $user->id, 100.00);
        $this->insertPayment($txA->id, $tenant->id, (int) $user->id, 100.00, isReturn: false); // pagou 100
        $this->insertPayment($txA->id, $tenant->id, (int) $user->id, 30.00, isReturn: true);    // troco/devolução 30

        $liquido = (float) $transactionUtil->getTotalPaid($txA->id);
        $bruto = (float) $transactionUtil->getTotalAmountPaid($txA->id);

        $this->assertEqualsWithDelta(70.00, $liquido, 0.0001, 'getTotalPaid (líquido) deve descontar a devolução: 100 - 30 = 70.');
        $this->assertEqualsWithDelta(130.00, $bruto, 0.0001, 'getTotalAmountPaid (bruto) deve ignorar is_return: 100 + 30 = 130.');

        // A divergência existe e vale exatamente 2× o valor devolvido (30 → 60).
        $this->assertEqualsWithDelta(
            60.00,
            $bruto - $liquido,
            0.0001,
            'A divergência bruto−líquido deve ser 2× a devolução. Se zerou, algum método mudou de definição — REGRA MESTRE.'
        );

        // ── Caso B: venda SEM devolução → os dois CONVERGEM (controle) ─────────────
        $txB = $this->makeSell($tenant->id, (int) $location->id, (int) $contact->id, (int) $user->id, 50.00);
        $this->insertPayment($txB->id, $tenant->id, (int) $user->id, 50.00, isReturn: false);

        $liquidoB = (float) $transactionUtil->getTotalPaid($txB->id);
        $brutoB = (float) $transactionUtil->getTotalAmountPaid($txB->id);

        $this->assertEqualsWithDelta($liquidoB, $brutoB, 0.0001, 'Sem devolução, líquido e bruto DEVEM ser iguais (50 = 50).');
        $this->assertEqualsWithDelta(50.00, $liquidoB, 0.0001);
    }

    // ---------------------------------------------------------------------
    // Helpers (fixtures mínimas — sem depender de factory inexistente)
    // ---------------------------------------------------------------------

    private function makeSell(int $businessId, int $locationId, int $contactId, int $userId, float $total): Transaction
    {
        return Transaction::create([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => 'due',
            'contact_id' => $contactId,
            'transaction_date' => Carbon::now()->toDateTimeString(),
            'final_total' => $total,
            'total_remaining_amount' => $total,
            'created_by' => $userId,
            'invoice_no' => 'CVS-' . uniqid(),
        ]);
    }

    /**
     * Insere direto na tabela (bypass do TransactionPaymentObserver): o teste caracteriza
     * apenas as duas queries SUM, não os side-effects do Financeiro. Controle total de is_return.
     */
    private function insertPayment(int $transactionId, int $businessId, int $userId, float $amount, bool $isReturn): void
    {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $transactionId,
            'business_id' => $businessId,
            'amount' => $amount,
            'method' => 'cash',
            'is_return' => $isReturn ? 1 : 0,
            'paid_on' => Carbon::now()->toDateTimeString(),
            'created_by' => $userId,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }
}
