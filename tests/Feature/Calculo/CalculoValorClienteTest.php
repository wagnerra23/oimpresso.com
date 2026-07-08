<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use App\Transaction;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WithSeededTenant;
use Tests\TestCase;

/**
 * Onda Cliente — Dente de cálculo do SALDO DO CLIENTE (o "quem me deve quanto").
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md (dente irmão, Sells)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O módulo Cliente mostra "quanto o cliente me deve" por DOIS caminhos, e nenhum
 * dos dois tinha teste (grep `getContactDue|getLedgerDetails` em tests/** = 0 em
 * 2026-07-03):
 *
 *   1. `Util::getContactDue($contact_id, $business_id)` — o número-resumo do saldo
 *      devedor. É a fonte do "customer_due" em SellController/SellPosController e da
 *      coluna `due` na listagem de contatos. [app/Utils/Util.php:1358]
 *
 *          due = Σ(sell final).final_total
 *              + Σ(purchase).final_total
 *              − net_paid_venda            ← SUM(IF(is_return=1, -1*amount, amount))
 *              − purchase_paid
 *              + opening_balance
 *              − opening_balance_paid
 *
 *   2. `TransactionUtil::getLedgerDetails(...)['all_balance_due']` — o saldo do EXTRATO
 *      exibido na tela `resources/js/Pages/Cliente/Ledger.tsx` (servida por
 *      `Modules/Crm/Http/Controllers/ClienteOssDataController::ledger`). Método próprio,
 *      independente do #1, que reconcilia is_return da mesma forma. [app/Utils/TransactionUtil.php:5156]
 *
 * O saldo NÃO é mera reutilização de `getTotalPaid` (já coberto pela Onda 1.4 #3695):
 * ambos os métodos têm SQL/agregação próprios com o mesmo risco de dupla contagem de
 * `is_return` que o incidente de valor tornou concreto. Por isso ganham dente.
 *
 * O QUE ESTE TESTE ADICIONA (ângulos novos — "0 teste hoje"):
 *   1. GOLDEN `getContactDue`: cenário concreto com devolução → número exato.
 *   2. PROPERTY: a devolução (is_return=1) NÃO é contada em dobro e tem o sinal certo
 *      (sobe o saldo devedor em +X, nunca −X nem +2X); e o saldo é aditivo (dividir um
 *      pagamento em parcelas não muda o saldo).
 *   3. DUPLA CONFIRMAÇÃO (dois caminhos independentes): `getContactDue` e
 *      `getLedgerDetails['all_balance_due']` DEVEM convergir pro mesmo cliente. É a
 *      "dupla confirmação por dois caminhos" da REGRA MESTRE, embutida no teste.
 *   4. RÉGUA MULTI-TENANT (Cliente é PII-heavy — Tier 0): o `business_id` é a única
 *      catraca de vazamento; o saldo de um cliente de OUTRO tenant nunca pode entrar
 *      no número do tenant corrente.
 *
 * ⛔ TEST-ONLY: este arquivo NÃO altera nenhum método de cálculo. Unificar/mexer em
 *    getContactDue ou getLedgerDetails é mudança de valor em prod → US separada sob
 *    REGRA MESTRE (dupla confirmação + antes→depois + OK [W]). Aqui só CARACTERIZA
 *    e trava o comportamento ATUAL.
 * ⛔ CT100 only (proibições Tier 0): nunca local, nunca Hostinger.
 */
class CalculoValorClienteTest extends TestCase
{
    use DatabaseTransactions;
    use WithSeededTenant;

    private TransactionUtil $transactionUtil;

    private Util $util;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionUtil = app(TransactionUtil::class);
        $this->util = app(Util::class);
    }

    /**
     * Skip-graceful se o schema MySQL canônico não estiver montado (ADR 0101 — CT100
     * tem MySQL real; runners de schema-próprio não têm as tabelas UltimatePOS).
     * Devolve [businessId, locationId, userId] do tenant canônico biz=1.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function seedBaseOrSkip(): array
    {
        $tenant = $this->seededTenant(); // biz=1 (ADR 0101). markTestSkipped acionável se faltar.

        $user = \App\User::where('business_id', $tenant->id)->first();
        if (! $user) {
            $this->markTestSkipped('Sem user no business canônico.');
        }
        $location = DB::table('business_locations')->where('business_id', $tenant->id)->first();
        if (! $location) {
            $this->markTestSkipped('Sem business_location no seed.');
        }

        // Sessão do operador — getLedgerDetails/__transactionQuery leem business_id via
        // `request()->session()` (não por parâmetro). Fora do ciclo HTTP o request singleton
        // não tem session store: bindamos o driver ao request pra `request()->session()`
        // e o helper `session()` apontarem pro MESMO store. Session canon BRL + date_format.
        $store = $this->app['session']->driver();
        $store->put('user', ['business_id' => $tenant->id]);
        $store->put('business', ['date_format' => 'd/m/Y', 'time_format' => 24]);
        $store->put('currency', ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']);
        request()->setLaravelSession($store);

        return [(int) $tenant->id, (int) $location->id, (int) $user->id];
    }

    // =========================================================================
    // 1) GOLDEN — getContactDue com devolução: número exato, sem dupla contagem
    // =========================================================================

    /**
     * Cliente com 1 venda finalizada de 227,90, pagou 100,00 e teve 30,00 de troco/
     * devolução (is_return=1). O saldo devedor tem que ser:
     *
     *   net_paid = 100 − 30 = 70
     *   due      = 227.90 − 70 = 157.90
     *
     * Se a devolução fosse ignorada (bug bruto), net_paid viraria 130 → due 97.90.
     * Se fosse contada como pagamento normal (sinal trocado), net_paid 130 também.
     * O golden trava o 157.90.
     */
    // Cobre UC-CLED-01 (Ledger.casos.md) — saldo devedor conta a devolução com o sinal certo.
    #[Test]
    public function get_contact_due_golden_com_devolucao(): void
    {
        [$biz, $loc, $usr] = $this->seedBaseOrSkip();

        $contact = $this->makeContact($biz, $usr);
        $sell = $this->makeSell($biz, $loc, $contact, $usr, 227.90);
        $this->insertPayment($sell->id, $biz, $usr, 100.00, isReturn: false); // pagou 100
        $this->insertPayment($sell->id, $biz, $usr, 30.00, isReturn: true);    // troco/devolução 30

        $due = (float) $this->util->getContactDue($contact, $biz);

        $this->assertEqualsWithDelta(
            157.90,
            $due,
            0.0001,
            "Saldo do cliente = 227.90 − (100 − 30) = 157.90. Veio {$due} — devolução ignorada (bug bruto → 97.90) "
            . 'ou contada em dobro?'
        );
    }

    /**
     * Cliente com opening_balance (saldo inicial de abertura) + venda, sem pagamentos.
     * Exercita a perna `opening_balance` da fórmula (que NÃO é `getTotalPaid`).
     *
     *   due = opening_balance(500) + sell(227.90) − 0 = 727.90
     */
    #[Test]
    public function get_contact_due_golden_com_opening_balance(): void
    {
        [$biz, $loc, $usr] = $this->seedBaseOrSkip();

        $contact = $this->makeContact($biz, $usr);
        $this->makeSell($biz, $loc, $contact, $usr, 227.90, type: 'sell', status: 'final');
        $this->makeSell($biz, $loc, $contact, $usr, 500.00, type: 'opening_balance', status: 'final');

        $due = (float) $this->util->getContactDue($contact, $biz);

        $this->assertEqualsWithDelta(
            727.90,
            $due,
            0.0001,
            "Saldo = opening_balance 500 + venda 227.90 = 727.90. Veio {$due}."
        );
    }

    // =========================================================================
    // 1b) DISCRIMINAÇÃO RED — prova que o golden pega a versão que ignora is_return
    // =========================================================================

    /**
     * TEST-ONLY não pode mutar o código de prod pra provar o RED (e não deve tocar a
     * fonte compartilhada do CT100). Em vez disso, reproduzimos INLINE a lógica BUGADA
     * (net_paid = SUM(amount), ignorando is_return — o que `getTotalAmountPaid` faz) e
     * travamos o CONTRATO: a versão bugada infla o pagamento e ENCOLHE o saldo devedor;
     * a versão atual (is_return-aware) não. O golden acima já é a armadilha — ele fixa
     * 157.90, que SÓ o código correto produz; a versão bugada daria 97.90 e o assert
     * quebraria (RED). Aqui deixamos o discriminador explícito.
     */
    // Cobre UC-CLED-01 (Ledger.casos.md) — versão que ignora is_return daria número diferente (red).
    #[Test]
    public function discriminacao_versao_que_ignora_is_return_seria_red(): void
    {
        [$biz, $loc, $usr] = $this->seedBaseOrSkip();

        $contact = $this->makeContact($biz, $usr);
        $sell = $this->makeSell($biz, $loc, $contact, $usr, 227.90);
        $this->insertPayment($sell->id, $biz, $usr, 100.00, isReturn: false);
        $this->insertPayment($sell->id, $biz, $usr, 30.00, isReturn: true);

        // Versão ATUAL (correta): net_paid = 100 − 30 = 70 → saldo 157.90.
        $dueReal = (float) $this->util->getContactDue($contact, $biz);

        // Versão BUGADA reproduzida inline: net_paid = SUM(amount) = 130 (ignora is_return)
        // → saldo encolhe pra 97.90 (o cliente "pareceria" dever menos do que deve).
        $brutoPaid = (float) DB::table('transaction_payments')->where('payment_for', $contact)->sum('amount');
        $dueBugado = 227.90 - $brutoPaid;

        $this->assertEqualsWithDelta(157.90, $dueReal, 0.0001, 'A versão atual tem que dar o saldo líquido 157.90.');
        $this->assertEqualsWithDelta(97.90, $dueBugado, 0.0001, 'Sanidade do vetor: ignorar is_return daria 97.90.');

        // O discriminador: enquanto os dois caminhos divergirem (por 2× a devolução = 60),
        // o golden tem poder de pegar a regressão. Se convergirem (getContactDue voltar a
        // ignorar is_return), o assert de 157.90 quebra = RED consciente.
        $this->assertEqualsWithDelta(
            60.00,
            abs($dueReal - $dueBugado),
            0.0001,
            'getContactDue convergiu com a versão que ignora is_return — regressão do saldo do cliente.'
        );
    }

    // =========================================================================
    // 2) PROPERTY — devolução não é contada em dobro + saldo é aditivo
    // =========================================================================

    /**
     * A devolução (is_return=1) DESFAZ um pagamento: adicionar um is_return=1 de X a um
     * cliente sobe o saldo devedor em EXATAMENTE +X (nem −X, nem +2X).
     *
     * Dois clientes idênticos (mesma venda, mesmo pagamento de 100); o segundo ganha um
     * is_return=1 de 30. A diferença dos saldos devedores tem que ser +30.
     */
    // Cobre UC-CLED-02 (Ledger.casos.md) — devolução sobe o saldo pelo valor exato, nunca em dobro.
    #[Test]
    public function property_devolucao_sobe_saldo_em_exatamente_o_valor_devolvido(): void
    {
        [$biz, $loc, $usr] = $this->seedBaseOrSkip();

        // Controle: venda 227.90, pagou 100 → deve 127.90
        $cA = $this->makeContact($biz, $usr);
        $sA = $this->makeSell($biz, $loc, $cA, $usr, 227.90);
        $this->insertPayment($sA->id, $biz, $usr, 100.00, isReturn: false);
        $dueA = (float) $this->util->getContactDue($cA, $biz);

        // Igual + uma devolução de 30 → deve 157.90
        $cB = $this->makeContact($biz, $usr);
        $sB = $this->makeSell($biz, $loc, $cB, $usr, 227.90);
        $this->insertPayment($sB->id, $biz, $usr, 100.00, isReturn: false);
        $this->insertPayment($sB->id, $biz, $usr, 30.00, isReturn: true);
        $dueB = (float) $this->util->getContactDue($cB, $biz);

        $this->assertEqualsWithDelta(
            30.00,
            $dueB - $dueA,
            0.0001,
            "Uma devolução de 30 deve subir o saldo em +30 (Δ={$dueB}−{$dueA}). Se deu 60 = dupla contagem; "
            . 'se deu −30 = sinal invertido.'
        );
    }

    /**
     * Aditividade: dividir o MESMO pagamento em duas parcelas (60 + 40) deixa o saldo
     * idêntico a um pagamento único de 100. Pega qualquer regressão que dependa da
     * granularidade das linhas de pagamento (SUM é insensível ao split).
     */
    // Cobre UC-CLED-05 (Ledger.casos.md) — parcelar um pagamento não muda o saldo (aditividade).
    #[Test]
    public function property_saldo_e_aditivo_no_split_de_pagamentos(): void
    {
        [$biz, $loc, $usr] = $this->seedBaseOrSkip();

        $cUnico = $this->makeContact($biz, $usr);
        $sUnico = $this->makeSell($biz, $loc, $cUnico, $usr, 227.90);
        $this->insertPayment($sUnico->id, $biz, $usr, 100.00, isReturn: false);
        $dueUnico = (float) $this->util->getContactDue($cUnico, $biz);

        $cSplit = $this->makeContact($biz, $usr);
        $sSplit = $this->makeSell($biz, $loc, $cSplit, $usr, 227.90);
        $this->insertPayment($sSplit->id, $biz, $usr, 60.00, isReturn: false);
        $this->insertPayment($sSplit->id, $biz, $usr, 40.00, isReturn: false);
        $dueSplit = (float) $this->util->getContactDue($cSplit, $biz);

        $this->assertEqualsWithDelta(
            $dueUnico,
            $dueSplit,
            0.0001,
            "Split 60+40 deve dar o mesmo saldo de um pagamento único de 100 ({$dueUnico} vs {$dueSplit})."
        );
        $this->assertEqualsWithDelta(127.90, $dueUnico, 0.0001);
    }

    // =========================================================================
    // 3) DUPLA CONFIRMAÇÃO — getContactDue == getLedgerDetails['all_balance_due']
    // =========================================================================

    /**
     * Os DOIS caminhos independentes de "quanto o cliente me deve" têm que convergir
     * pro MESMO número. É a "dupla confirmação por dois caminhos independentes" da
     * REGRA MESTRE, e cobre de quebra o método que a tela Cliente/Ledger.tsx usa de
     * verdade (getLedgerDetails, via ClienteOssDataController::ledger).
     *
     * Cenário: venda 227.90 + opening_balance 500 + pagou 100 + devolveu 30.
     *   getContactDue        = 500 + 227.90 − (100 − 30) = 657.90
     *   all_balance_due      = mesmo 657.90 (reconcilia is_return igual)
     */
    // Cobre UC-CLED-03 (Ledger.casos.md) — resumo (getContactDue) bate com o extrato (all_balance_due).
    #[Test]
    public function dupla_confirmacao_contact_due_bate_com_ledger_all_balance_due(): void
    {
        [$biz, $loc, $usr] = $this->seedBaseOrSkip();

        $contact = $this->makeContact($biz, $usr);
        $this->makeSell($biz, $loc, $contact, $usr, 227.90, type: 'sell', status: 'final');
        $ob = $this->makeSell($biz, $loc, $contact, $usr, 500.00, type: 'opening_balance', status: 'final');
        $sell = Transaction::where('contact_id', $contact)->where('type', 'sell')->first();
        $this->insertPayment($sell->id, $biz, $usr, 100.00, isReturn: false);
        $this->insertPayment($sell->id, $biz, $usr, 30.00, isReturn: true);
        // opening_balance sem pagamento → fica devendo os 500 cheios
        unset($ob);

        $due = (float) $this->util->getContactDue($contact, $biz);

        // getLedgerDetails usa business_id da SESSÃO (setada em seedBaseOrSkip) e datas
        // amplas; 'all_balance_due' é o saldo geral (independente do range).
        $ledger = $this->transactionUtil->getLedgerDetails(
            $contact,
            '2000-01-01',
            '2999-12-31',
            'format_1'
        );
        $allBalanceDue = (float) $ledger['all_balance_due'];

        $this->assertEqualsWithDelta(
            657.90,
            $due,
            0.0001,
            "getContactDue = 500 + 227.90 − 70 = 657.90. Veio {$due}."
        );
        $this->assertEqualsWithDelta(
            $due,
            $allBalanceDue,
            0.01,
            "Os dois caminhos do saldo divergiram: getContactDue={$due} vs ledger.all_balance_due={$allBalanceDue}. "
            . 'Um dos dois mudou de definição de is_return/opening_balance — REGRA MESTRE.'
        );
    }

    // =========================================================================
    // 4) RÉGUA MULTI-TENANT — o saldo de outro tenant nunca entra no número (Tier 0)
    // =========================================================================

    /**
     * Cliente é PII-heavy: o saldo devedor de um contato jamais pode aparecer quando a
     * consulta é feita com o `business_id` de OUTRO tenant. O `business_id` é a catraca
     * — este teste prova que ela é load-bearing sem precisar de um segundo tenant
     * completo: cria o contato+venda no biz corrente (deve 999) e consulta o MESMO
     * contato passando um business_id estrangeiro → o `where('contacts.business_id')`
     * scopa o contato pra fora e o saldo tem que dar 0 (nenhum vazamento).
     */
    // Cobre UC-CLED-04 (Ledger.casos.md) — saldo de cliente de outro tenant nunca entra no meu.
    #[Test]
    public function regua_multi_tenant_business_id_estrangeiro_zera_o_saldo(): void
    {
        [$biz, $loc, $usr] = $this->seedBaseOrSkip();

        $contact = $this->makeContact($biz, $usr);
        $this->makeSell($biz, $loc, $contact, $usr, 999.00); // deve 999, sem pagar

        // business_id que garantidamente NÃO é o do contato (nem precisa existir na tabela
        // business — getContactDue só filtra `contacts.business_id`, sem join a business).
        $bizEstrangeiro = $biz + 987654;

        $dueScopedOut = (float) $this->util->getContactDue($contact, $bizEstrangeiro);
        $this->assertEqualsWithDelta(
            0.0,
            $dueScopedOut,
            0.0001,
            "VAZAMENTO TIER 0: saldo do contato (biz={$biz}) apareceu ao consultar com business_id={$bizEstrangeiro} "
            . "(veio {$dueScopedOut}, esperado 0)."
        );

        // Com o business_id correto, o valor real aparece (a catraca não é falso-negativo geral).
        $dueReal = (float) $this->util->getContactDue($contact, $biz);
        $this->assertEqualsWithDelta(
            999.00,
            $dueReal,
            0.0001,
            "Com business_id={$biz} o saldo real (999) tem que aparecer. Veio {$dueReal}."
        );
    }

    // ---------------------------------------------------------------------
    // Helpers (fixtures mínimas — espelham CalculoValorSellsTest, Onda 1.4)
    // ---------------------------------------------------------------------

    /**
     * Cria um contato limpo (customer) do tenant, isolando as transações do golden.
     * Insere direto na tabela: controle total, sem depender de factory inexistente.
     */
    private function makeContact(int $businessId, int $userId, string $type = 'customer'): int
    {
        return (int) DB::table('contacts')->insertGetId([
            'business_id' => $businessId,
            'type' => $type,
            'name' => 'CVC-' . uniqid(),
            'mobile' => '',
            'created_by' => $userId,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    /**
     * `withoutEvents`: a criação da venda dispara o TransactionObserver do Financeiro
     * (TituloAutoService gera `fin_titulos` com `SELECT ... FOR UPDATE`), que causa
     * DeadlockException sob DatabaseTransactions e é side-effect que este dente NÃO
     * caracteriza. Suprimimos os eventos: só queremos as LINHAS pra as queries SUM.
     */
    private function makeSell(
        int $businessId,
        int $locationId,
        int $contactId,
        int $userId,
        float $total,
        string $type = 'sell',
        string $status = 'final'
    ): Transaction {
        return Transaction::withoutEvents(fn () => Transaction::create([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'type' => $type,
            'status' => $status,
            'payment_status' => 'due',
            'contact_id' => $contactId,
            'transaction_date' => Carbon::now()->toDateTimeString(),
            'final_total' => $total,
            'total_remaining_amount' => $total,
            'created_by' => $userId,
            'invoice_no' => 'CVC-' . uniqid(),
        ]));
    }

    /**
     * Insere pagamento direto (bypass do TransactionPaymentObserver): o teste caracteriza
     * a agregação SUM(IF(is_return...)) do saldo, não os side-effects do Financeiro.
     * Controle total de is_return.
     */
    private function insertPayment(int $transactionId, int $businessId, int $userId, float $amount, bool $isReturn): void
    {
        // `payment_for` = contato do pagamento: é a coluna que o extrato (getLedgerDetails
        // → __paymentQuery) usa pra achar os pagamentos. getContactDue usa `transaction_id`.
        // Setar os dois (como o UltimatePOS real faz) mantém os DOIS caminhos consistentes.
        $contactId = DB::table('transactions')->where('id', $transactionId)->value('contact_id');

        DB::table('transaction_payments')->insert([
            'transaction_id' => $transactionId,
            'payment_for' => $contactId,
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
