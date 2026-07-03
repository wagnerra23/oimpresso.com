<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use App\TaxRate;
use App\Transaction;
use App\Utils\TaxUtil;
use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Camada de correção do Financeiro — dente de cálculo (ciclo-padrão da Onda, passo 3/D1).
 * @see memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md §"O ciclo-padrão de UMA onda"
 * @see memory/requisitos/_Roadmap_Faturamento.md §"Camada de correção contínua (dente de cálculo)"
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O PLANO-MESTRE (linha 49) verificou em origin/main que 6/6 métodos de cálculo core
 * estão sem teste. A Onda 1.4 (tests/Feature/Calculo/CalculoValorSellsTest.php, #3695)
 * fechou `calculateInvoiceTotal`, o round-trip `num_uf`/`num_f` e caracterizou
 * `getTotalPaid ≠ getTotalAmountPaid`. Sobraram indefesos (verificado 2026-07-02, 0 teste):
 *
 *   1. `calculatePaymentStatus`  — app/Utils/TransactionUtil.php:3009 — decide pago/parcial/
 *      devendo. É o coração do Financeiro: erra e nasce **título fantasma pago** em
 *      ContasReceber/ContasPagar/`fin_titulos`.
 *   2. `updateGroupTaxAmount`     — app/Utils/TaxUtil.php:15 — soma o imposto de um grupo.
 *      Trunca centavo e a NFe sai com imposto errado.
 *
 * O QUE ESTE TESTE ADICIONA (NÃO duplica a Onda 1.4):
 *   A) Golden truth-table de `calculatePaymentStatus` (paid/partial/due) + a fronteira SEM
 *      tolerância (sub-centavo vira 'partial' → título fantasma) + discriminador RED que
 *      prova que trocar líquido→bruto criaria 'paid' numa venda devolvida.
 *   B) Golden NFe realista + property "amount do grupo == soma dos sub-impostos" +
 *      discriminador RED que prova que truncar centavo (`(int)`) erraria o imposto.
 *
 * ⛔ TEST-ONLY: este arquivo NÃO altera nenhum método de cálculo. Unificar
 *    getTotalPaid/getTotalAmountPaid, dar tolerância ao `<=`, ou trocar a soma do grupo
 *    é mudança de valor em prod → US separada sob REGRA MESTRE (memory/proibicoes.md:
 *    dupla confirmação + tabela antes→depois + OK [W]). Este teste só CARACTERIZA e trava
 *    o comportamento ATUAL (red contra o bug, green no código de hoje).
 */
class CalculoValorFinanceiroTest extends TestCase
{
    use DatabaseTransactions;

    private TransactionUtil $transactionUtil;

    private TaxUtil $taxUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionUtil = app(TransactionUtil::class);
        $this->taxUtil = app(TaxUtil::class);

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
    // A1) GOLDEN — calculatePaymentStatus: tabela-verdade paid/partial/due
    // =========================================================================

    /**
     * O status vem do LÍQUIDO (`getTotalPaid`, desconta devolução) vs `final_total`:
     *   final <= pago            → 'paid'
     *   pago > 0 && final > pago → 'partial'
     *   senão                    → 'due'
     *
     * @return array<string, array{0: float, 1: list<array{0: float, 1: bool}>, 2: string}>
     */
    public static function statusPagamentoProvider(): array
    {
        // [ final_total, [ [amount, is_return], ... ], status_esperado ]
        return [
            'exato: pago == final → paid'          => [100.00, [[100.00, false]], 'paid'],
            'sobrepago → paid'                     => [100.00, [[150.00, false]], 'paid'],
            'parcial → partial'                    => [100.00, [[40.00, false]], 'partial'],
            'nada pago → due'                      => [100.00, [], 'due'],
            'devolução total → due (líquido 0)'    => [100.00, [[100.00, false], [100.00, true]], 'due'],
            'devolução parcial → partial (líq 70)' => [100.00, [[100.00, false], [30.00, true]], 'partial'],
        ];
    }

    /**
     * @param  list<array{0: float, 1: bool}>  $payments
     */
    #[Test]
    #[DataProvider('statusPagamentoProvider')]
    public function calculate_payment_status_tabela_verdade(float $finalTotal, array $payments, string $esperado): void
    {
        [$bizId, $locationId, $contactId, $userId] = $this->tenantFixtureOrSkip();

        $tx = $this->makeSell($bizId, $locationId, $contactId, $userId, $finalTotal);
        foreach ($payments as [$amount, $isReturn]) {
            $this->insertPayment($tx->id, $bizId, $userId, $amount, $isReturn);
        }

        $status = $this->transactionUtil->calculatePaymentStatus($tx->id);

        $this->assertSame(
            $esperado,
            $status,
            "final_total={$finalTotal}, líquido pago diverge do status esperado '{$esperado}'."
        );
    }

    // =========================================================================
    // A2) FRONTEIRA — o `<=` não tem tolerância: sub-centavo vira 'partial'
    // =========================================================================

    /**
     * Caracteriza (NÃO conserta) a fragilidade real: a comparação `final <= pago` é
     * exata, sem epsilon. Um centavo a mais no título que o cliente pagou → 'partial'
     * (aparece devendo). Dar tolerância é mudança de valor em prod → REGRA MESTRE.
     *
     * @return array<string, array{0: float, 1: float, 2: string}>
     */
    public static function fronteiraSemToleranciaProvider(): array
    {
        // [ final_total, pago (1 pagamento sem devolução), status_esperado ]
        return [
            'igual no centavo → paid'          => [100.00, 100.00, 'paid'],
            'título 1 centavo a mais → partial' => [100.01, 100.00, 'partial'],
            'cliente pagou 1 centavo a mais → paid' => [99.99, 100.00, 'paid'],
        ];
    }

    #[Test]
    #[DataProvider('fronteiraSemToleranciaProvider')]
    public function calculate_payment_status_fronteira_exata_sem_tolerancia(float $finalTotal, float $pago, string $esperado): void
    {
        [$bizId, $locationId, $contactId, $userId] = $this->tenantFixtureOrSkip();

        $tx = $this->makeSell($bizId, $locationId, $contactId, $userId, $finalTotal);
        $this->insertPayment($tx->id, $bizId, $userId, $pago, isReturn: false);

        $this->assertSame(
            $esperado,
            $this->transactionUtil->calculatePaymentStatus($tx->id),
            "Fronteira: final={$finalTotal} vs pago={$pago} não deu '{$esperado}' — o `<=` mudou de semântica."
        );
    }

    // =========================================================================
    // A3) DISCRIMINAÇÃO RED — líquido vs bruto: o título fantasma pago
    // =========================================================================

    /**
     * A falha nomeada no dente: se `calculatePaymentStatus` somasse o BRUTO
     * (`getTotalAmountPaid`, ignora is_return) em vez do LÍQUIDO (`getTotalPaid`),
     * uma venda 100% devolvida apareceria como **paid** (título fantasma pago).
     *
     * TEST-ONLY não muta o prod pra provar o RED: reproduzimos INLINE a mesma lógica
     * de status usando o bruto e travamos o contrato — atual='due', bugado='paid'.
     * Enquanto divergirem, o teste tem poder de pegar a regressão líquido→bruto.
     */
    #[Test]
    public function discriminacao_liquido_vs_bruto_evita_titulo_fantasma_pago(): void
    {
        [$bizId, $locationId, $contactId, $userId] = $this->tenantFixtureOrSkip();

        // Venda de 100 paga integralmente e depois 100% devolvida → líquido 0.
        $tx = $this->makeSell($bizId, $locationId, $contactId, $userId, 100.00);
        $this->insertPayment($tx->id, $bizId, $userId, 100.00, isReturn: false);
        $this->insertPayment($tx->id, $bizId, $userId, 100.00, isReturn: true);

        $final = 100.00;
        $liquido = (float) $this->transactionUtil->getTotalPaid($tx->id);        // 0.0
        $bruto = (float) $this->transactionUtil->getTotalAmountPaid($tx->id);    // 200.0

        // Sanidade dos dois somadores (caracterizado na Onda 1.4, re-ancorado aqui).
        $this->assertEqualsWithDelta(0.0, $liquido, 0.0001, 'Líquido de venda devolvida deve ser 0 (100 − 100).');
        $this->assertEqualsWithDelta(200.0, $bruto, 0.0001, 'Bruto ignora is_return: 100 + 100 = 200.');

        // Comportamento ATUAL (usa líquido): nada efetivamente pago → 'due'.
        $atual = $this->transactionUtil->calculatePaymentStatus($tx->id);
        $this->assertSame('due', $atual, 'Venda devolvida deve ficar devendo — o dinheiro voltou pro cliente.');

        // Versão BUGADA (usa bruto): mesma lógica de status, mas sobre o bruto → 'paid'.
        $bugado = $this->statusUsandoBruto($final, $bruto);
        $this->assertSame('paid', $bugado, 'Sanidade do vetor: somar bruto criaria título fantasma pago.');

        // O discriminador: enquanto atual='due' e bugado='paid' divergirem, o teste
        // pega a regressão líquido→bruto. Se convergirem, é RED consciente.
        $this->assertNotSame(
            $atual,
            $bugado,
            'calculatePaymentStatus convergiu com a versão que soma o bruto — título fantasma pago.'
        );
    }

    // =========================================================================
    // B1) GOLDEN — updateGroupTaxAmount: grupo NFe realista (ICMS+PIS+COFINS)
    // =========================================================================

    /**
     * Grupo de imposto realista: ICMS 18,00 + PIS 1,65 + COFINS 7,60 = 27,25.
     * Depois de `updateGroupTaxAmount`, o `amount` do grupo tem que bater 27,25 — e
     * NUNCA 26,00 (o que aconteceria se a soma truncasse os centavos).
     */
    #[Test]
    public function update_group_tax_amount_grupo_nfe_realista(): void
    {
        [$bizId, , , $userId] = $this->tenantFixtureOrSkip();

        $group = $this->makeTaxGroup($bizId, $userId, [18.00, 1.65, 7.60]);

        $this->taxUtil->updateGroupTaxAmount($group->id);
        $group->refresh();

        $this->assertEqualsWithDelta(
            27.25,
            (float) $group->amount,
            0.0001,
            "amount do grupo = {$group->amount} (esperado 27.25) — a soma dos sub-impostos truncou?"
        );
    }

    // =========================================================================
    // B2) PROPERTY — amount do grupo == soma aritmética dos sub-impostos
    // =========================================================================

    /**
     * Invariante geral: seja qual for o conjunto de sub-impostos, o `amount` do grupo
     * salvo é a soma deles (dentro da precisão de 4 casas do float(22,4) da coluna).
     *
     * @return array<string, array{0: list<float>}>
     */
    public static function subImpostosProvider(): array
    {
        return [
            'nfe realista'          => [[18.00, 1.65, 7.60]],
            'dois impostos'         => [[5.00, 12.00]],
            'um imposto só'         => [[4.00]],
            'acumulação de float'   => [[0.10, 0.10, 0.10]],
            'grupo vazio → 0'       => [[]],
        ];
    }

    /**
     * @param  list<float>  $amounts
     */
    #[Test]
    #[DataProvider('subImpostosProvider')]
    public function update_group_tax_amount_soma_os_sub_impostos(array $amounts): void
    {
        [$bizId, , , $userId] = $this->tenantFixtureOrSkip();

        $group = $this->makeTaxGroup($bizId, $userId, $amounts);
        $esperado = array_sum($amounts);

        $this->taxUtil->updateGroupTaxAmount($group->id);
        $group->refresh();

        $this->assertEqualsWithDelta(
            $esperado,
            (float) $group->amount,
            0.0001,
            'amount do grupo divergiu da soma aritmética dos sub-impostos.'
        );
    }

    // =========================================================================
    // B3) DISCRIMINAÇÃO RED — truncar centavo erraria o imposto na NFe
    // =========================================================================

    /**
     * A falha nomeada no dente: "floating-point truncation → imposto errado na NFe".
     * Reproduz INLINE a versão que truncaria cada sub-imposto (`(int)`) e trava o
     * contrato — atual soma 27,25; a truncada daria 26,00. Enquanto divergirem
     * (Δ 1,25), o teste pega a regressão de truncamento.
     */
    #[Test]
    public function discriminacao_truncar_centavo_erraria_o_imposto(): void
    {
        [$bizId, , , $userId] = $this->tenantFixtureOrSkip();

        $amounts = [18.00, 1.65, 7.60];
        $group = $this->makeTaxGroup($bizId, $userId, $amounts);

        $this->taxUtil->updateGroupTaxAmount($group->id);
        $group->refresh();

        // Como a versão BUGADA truncaria: perde os centavos de cada sub-imposto.
        $truncado = 0.0;
        foreach ($amounts as $a) {
            $truncado += (int) $a; // 18 + 1 + 7 = 26
        }

        $this->assertEqualsWithDelta(27.25, (float) $group->amount, 0.0001, 'Atual soma correto: 27.25.');
        $this->assertEqualsWithDelta(26.00, $truncado, 0.0001, 'Sanidade do vetor: truncar daria 26.00.');

        $this->assertGreaterThan(
            1.0,
            abs((float) $group->amount - $truncado),
            'updateGroupTaxAmount convergiu com a versão que trunca centavo — imposto errado na NFe.'
        );
    }

    // ---------------------------------------------------------------------
    // Helpers (fixtures mínimas — espelham o padrão da Onda 1.4)
    // ---------------------------------------------------------------------

    /**
     * Resolve tenant canônico (biz=1, ADR 0101) + location + contact + user, ou skip
     * acionável. Retorna [businessId, locationId, contactId, userId].
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function tenantFixtureOrSkip(): array
    {
        $tenant = $this->seededTenant(); // skip-graceful se o seed não rodou

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

        return [(int) $tenant->id, (int) $location->id, (int) $contact->id, (int) $user->id];
    }

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
            'invoice_no' => 'CVF-'.uniqid(),
        ]);
    }

    /**
     * Insere direto na tabela (bypass do TransactionPaymentObserver): o dente caracteriza
     * o cálculo de status, não os side-effects do Financeiro. Controle total de is_return.
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

    /**
     * Cria um grupo de imposto (is_tax_group=1) com N sub-impostos ligados via
     * `group_sub_taxes`. O grupo nasce com amount=0 — quem preenche é o método sob teste.
     *
     * @param  list<float>  $subAmounts
     */
    private function makeTaxGroup(int $businessId, int $userId, array $subAmounts): TaxRate
    {
        $group = TaxRate::create([
            'business_id' => $businessId,
            'name' => 'Grupo CVF-'.uniqid(),
            'amount' => 0,
            'is_tax_group' => 1,
            'created_by' => $userId,
        ]);

        $subIds = [];
        foreach ($subAmounts as $i => $amount) {
            $sub = TaxRate::create([
                'business_id' => $businessId,
                'name' => 'Sub CVF-'.$i.'-'.uniqid(),
                'amount' => $amount,
                'is_tax_group' => 0,
                'created_by' => $userId,
            ]);
            $subIds[] = $sub->id;
        }

        if ($subIds !== []) {
            $group->sub_taxes()->sync($subIds);
        }

        return $group;
    }

    /**
     * Reprodução INLINE da lógica de `calculatePaymentStatus` usando o BRUTO — só pro
     * discriminador RED de A3. NÃO é usada em prod; existe pra provar o vetor do bug.
     */
    private function statusUsandoBruto(float $finalAmount, float $bruto): string
    {
        if ($finalAmount <= $bruto) {
            return 'paid';
        }
        if ($bruto > 0 && $finalAmount > $bruto) {
            return 'partial';
        }

        return 'due';
    }
}
