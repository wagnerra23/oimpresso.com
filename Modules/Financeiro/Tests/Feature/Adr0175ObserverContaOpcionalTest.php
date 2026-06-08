<?php

declare(strict_types=1);

namespace Modules\Financeiro\Tests\Feature;

use App\Business;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * ADR 0175 — Observer Financeiro permite baixa sem fin_contas_bancarias.
 *
 * Verifica que TransactionPaymentObserver dispara registrarPagamento() corretamente
 * mesmo quando biz não tem fin_contas_bancarias cadastrada — cria TituloBaixa com
 * conta_bancaria_id = NULL (pos-migration 2026_05_20_200000).
 *
 * Substitui comportamento prévio (commit 540a26a41 2026-05-08) que fazia no-op
 * gracioso silencioso — bug invisível em prod por 12 dias até Larissa biz=4
 * reportar (sessão 2026-05-20).
 *
 * Skip gracioso:
 *  - SQLite: schema UltimatePOS MySQL-only não roda
 *  - Tabelas legacy ausentes: env sem seeder UltimatePOS
 *  - Migration 2026_05_20_200000 não aplicada (coluna NOT NULL ainda)
 *
 * @see memory/decisions/0175-fix-observer-conta-bancaria-opcional.md
 * @see memory/sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md
 * @see memory/reference/feedback-fin-bridge-no-op-account-gap.md
 */
class Adr0175ObserverContaOpcionalTest extends FinanceiroTestCase
{
    private const TEST_BIZ_ID = 99; // fictício, sem dados reais (ADR 0101 isolation)

    private ?int $createdTxId = null;
    private ?int $createdTpId = null;
    private ?int $createdTituloId = null;
    private ?int $createdBaixaId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requer MySQL (UltimatePOS schema legacy).');
        }

        if (! Schema::hasTable('transactions') || ! Schema::hasTable('transaction_payments')
            || ! Schema::hasTable('fin_titulos') || ! Schema::hasTable('fin_titulo_baixas')
            || ! Schema::hasTable('fin_contas_bancarias')) {
            $this->markTestSkipped('Tabelas legacy/Financeiro ausentes — rode migrations.');
        }

        // Guard: migration 2026_05_20_200000 aplicada? coluna deve ser nullable.
        $coluna = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='fin_titulo_baixas'
               AND column_name='conta_bancaria_id'"
        );
        if (! $coluna || strtoupper((string) $coluna->IS_NULLABLE) !== 'YES') {
            $this->markTestSkipped('Migration 2026_05_20_200000 ADR 0175 não aplicada (conta_bancaria_id ainda NOT NULL).');
        }

        // Garante biz=99 fictício existe + zero fin_contas_bancarias
        if (! Business::find(self::TEST_BIZ_ID)) {
            DB::table('business')->insert([
                'id' => self::TEST_BIZ_ID,
                'name' => 'TEST-ADR-0175',
                'currency_id' => 1,
                'start_date' => now(),
                'default_profit_percent' => 0,
                'owner_id' => 1,
                'time_zone' => 'America/Sao_Paulo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('fin_contas_bancarias')->where('business_id', self::TEST_BIZ_ID)->delete();
    }

    protected function tearDown(): void
    {
        if ($this->createdBaixaId) {
            DB::table('fin_titulo_baixas')->where('id', $this->createdBaixaId)->delete();
        }
        if ($this->createdTituloId) {
            DB::table('fin_titulos')->where('id', $this->createdTituloId)->delete();
        }
        if ($this->createdTpId) {
            DB::table('transaction_payments')->where('id', $this->createdTpId)->delete();
        }
        if ($this->createdTxId) {
            DB::table('transactions')->where('id', $this->createdTxId)->delete();
        }
        DB::table('fin_caixa_movimentos')->where('business_id', self::TEST_BIZ_ID)->delete();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function observer_cria_titulo_baixa_mesmo_sem_fin_contas_bancarias()
    {
        // Pré-condição: biz=99 NÃO tem nenhuma fin_contas_bancarias
        $contasBancarias = DB::table('fin_contas_bancarias')
            ->where('business_id', self::TEST_BIZ_ID)
            ->count();
        $this->assertSame(0, $contasBancarias, 'Setup falhou: biz=99 deveria ter 0 fin_contas_bancarias');

        // Cria Transaction via Eloquent (dispara TransactionObserver → cria fin_titulos)
        $tx = new Transaction;
        $tx->business_id = self::TEST_BIZ_ID;
        $tx->location_id = 1;
        $tx->type = 'sell';
        $tx->status = 'final';
        $tx->payment_status = 'due';
        $tx->final_total = 100.00;
        $tx->total_before_tax = 100.00;
        $tx->ref_no = 'ADR-0175-' . uniqid();
        $tx->transaction_date = now();
        $tx->created_by = 1;
        $tx->save();
        $this->createdTxId = $tx->id;

        // TransactionPayment via Eloquent (dispara TransactionPaymentObserver → registrarPagamento)
        $tp = new TransactionPayment;
        $tp->business_id = self::TEST_BIZ_ID;
        $tp->transaction_id = $tx->id;
        $tp->amount = 100.00;
        $tp->method = 'pix';
        $tp->paid_on = now();
        $tp->created_by = 1;
        $tp->payment_ref_no = 'ADR-0175-PAY-' . uniqid();
        $tp->save();
        $this->createdTpId = $tp->id;

        // ASSERT: fin_titulos foi criado pelo TransactionObserver
        $titulo = DB::table('fin_titulos')
            ->where('business_id', self::TEST_BIZ_ID)
            ->where('origem', 'venda')
            ->where('origem_id', $tx->id)
            ->first();
        $this->assertNotNull($titulo, 'TransactionObserver deveria criar fin_titulos pra venda biz=99');
        $this->createdTituloId = (int) $titulo->id;

        // ASSERT CRÍTICA ADR 0175: fin_titulo_baixas foi criada COM conta_bancaria_id NULL
        // Antes (commit 540a26a41): no-op gracioso silenciava — não criava nada.
        // Pos-ADR 0175: cria com NULL pra biz reconciliar depois.
        $baixa = DB::table('fin_titulo_baixas')
            ->where('business_id', self::TEST_BIZ_ID)
            ->where('transaction_payment_id', $tp->id)
            ->first();
        $this->assertNotNull(
            $baixa,
            'ADR 0175 fix: TransactionPaymentObserver deve criar fin_titulo_baixa MESMO sem fin_contas_bancarias. '
            . 'Antes (commit 540a26a41) fazia no-op gracioso silencioso — causou bug Larissa biz=4 invisível por 12 dias.'
        );
        $this->createdBaixaId = (int) $baixa->id;

        // ASSERT: conta_bancaria_id é NULL (não vinculada a conta — biz não cadastrou)
        $this->assertNull(
            $baixa->conta_bancaria_id,
            'ADR 0175: baixa deve ter conta_bancaria_id NULL pra reconciliação posterior via UI/comando'
        );

        // ASSERT: outros campos OK
        $this->assertEquals(100.00, (float) $baixa->valor_baixa);
        $this->assertEquals('pix', $baixa->meio_pagamento);
        $this->assertSame((int) $tp->id, (int) $baixa->transaction_payment_id);
        $this->assertSame((int) $titulo->id, (int) $baixa->titulo_id);

        // ASSERT: fin_titulos.valor_aberto recalculado pelo recalcularTitulo() → 0 (totalmente quitado)
        $tituloAposBaixa = DB::table('fin_titulos')->where('id', $titulo->id)->first();
        $this->assertEquals(0.00, (float) $tituloAposBaixa->valor_aberto, 'recalcularTitulo deveria zerar valor_aberto');
        $this->assertSame('quitado', $tituloAposBaixa->status, 'status deveria virar quitado pos-baixa total');

        // ASSERT: fin_caixa_movimentos também criado com conta_bancaria_id NULL
        $movimento = DB::table('fin_caixa_movimentos')
            ->where('business_id', self::TEST_BIZ_ID)
            ->where('origem_tipo', 'titulo_baixa')
            ->where('origem_id', $baixa->id)
            ->first();
        $this->assertNotNull($movimento, 'CaixaMovimento deveria ser criado pareado com TituloBaixa');
        $this->assertNull(
            $movimento->conta_bancaria_id,
            'ADR 0175: CaixaMovimento também aceita conta_bancaria_id NULL'
        );
        $this->assertSame('entrada', $movimento->tipo, 'venda paga → entrada no caixa');
    }
}
