<?php

namespace Modules\Financeiro\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 ADR 0236 — backfill OFX -> fin_extrato_lancamentos (canonica).
 *
 * Cobre:
 *  - --business obrigatorio (Tier 0)
 *  - --dry nao escreve
 *  - backfill move linha OFX -> canonica com external_id "ofx:<fitid>" + origem=ofx
 *  - preserva created_at original + workflow de conciliacao (status/titulo_id)
 *  - idempotente: rodar 2x nao duplica (external_id deterministico)
 *  - external_id prefixado nao colide (fitid == idempotency_key numerico -> 2 linhas)
 *  - linha OFX sem conta -> conta "OFX avulso" generica criada
 *  - API existente recebe external_id "api:<key>"
 *  - Tier 0: --business=A nao toca linhas de B
 *
 * Isolamento: MySQL-guard + DatabaseTransactions + prefixo unico (igual Fase 1).
 *
 * @see \Modules\Financeiro\Console\Commands\BackfillExtratoOfxCommand
 */
class BackfillExtratoOfxTest extends FinanceiroTestCase
{
    use DatabaseTransactions;

    private string $pfx = '';
    private int $contaId = 0;
    private int $accountId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requer MySQL (UltimatePOS legacy schema).');
        }

        $this->actAsAdmin();

        // Depende das colunas da Fase 1 + Fase 2.
        foreach (['status', 'external_id', 'origem'] as $col) {
            if (! Schema::hasColumn('fin_extrato_lancamentos', $col)) {
                $this->markTestSkipped("Coluna {$col} ausente — rode migrations Fase 1 + Fase 2.");
            }
        }
        if (! Schema::hasTable('fin_bank_statement_lines')) {
            $this->markTestSkipped('fin_bank_statement_lines ausente.');
        }

        $this->pfx = 'PEST-F2-' . uniqid() . '-';

        $this->accountId = DB::table('accounts')->insertGetId([
            'business_id' => $this->business->id,
            'name' => $this->pfx . 'Conta',
            'account_number' => '777',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->contaId = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id' => $this->business->id,
            'account_id' => $this->accountId,
            'banco_codigo' => '077',
            'agencia' => '0001',
            'carteira' => '112',
            'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture de teste
            'beneficiario_razao_social' => 'Teste F2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->business && $this->pfx !== '') {
            DB::table('fin_extrato_lancamentos')->where('business_id', $this->business->id)
                ->where('external_id', 'like', 'ofx:' . $this->pfx . '%')->delete();
            DB::table('fin_bank_statement_lines')->where('business_id', $this->business->id)
                ->where('fitid', 'like', $this->pfx . '%')->delete();
            // conta OFX avulso criada pelo command
            $avulso = DB::table('fin_contas_bancarias')->where('business_id', $this->business->id)
                ->where('banco_codigo', 'OFX')->where('beneficiario_razao_social', 'OFX avulso');
            $avulsoAccId = (clone $avulso)->value('account_id');
            (clone $avulso)->delete();
            if ($avulsoAccId) {
                DB::table('accounts')->where('id', $avulsoAccId)->where('account_number', 'OFX-AVULSO')->delete();
            }
            if ($this->contaId) {
                DB::table('fin_contas_bancarias')->where('id', $this->contaId)->delete();
            }
            if ($this->accountId) {
                DB::table('accounts')->where('id', $this->accountId)->delete();
            }
        }

        parent::tearDown();
    }

    /** Insere uma linha OFX (fin_bank_statement_lines). */
    private function linhaOfx(string $fitidSuffix, array $over = []): int
    {
        return DB::table('fin_bank_statement_lines')->insertGetId(array_merge([
            'business_id' => $this->business->id,
            'conta_bancaria_id' => $this->contaId,
            'fitid' => $this->pfx . $fitidSuffix,
            'data_movimento' => now()->subDays(2)->toDateString(),
            'descricao' => $this->pfx . 'linha OFX',
            'valor' => 1500.0000,
            'tipo' => 'credit',
            'status' => 'pendente',
            'source_file' => 'extrato.ofx',
            'created_at' => now()->subDays(5), // data antiga pra testar preservacao
            'updated_at' => now(),
        ], $over));
    }

    private function runBackfill(array $opts = []): int
    {
        return $this->artisan('financeiro:backfill-extrato-ofx', array_merge([
            '--business' => $this->business->id,
        ], $opts))->run();
    }

    /** Quantas linhas canonicas deste teste (escopo external_id ofx:pfx). */
    private function countCanonOfx(): int
    {
        return (int) DB::table('fin_extrato_lancamentos')
            ->where('business_id', $this->business->id)
            ->where('external_id', 'like', 'ofx:' . $this->pfx . '%')
            ->count();
    }

    public function test_business_obrigatorio_tier0(): void
    {
        $exit = $this->artisan('financeiro:backfill-extrato-ofx')->run();
        $this->assertSame(1, $exit, '--business ausente deve FALHAR (Tier 0)');
    }

    public function test_dry_run_nao_escreve(): void
    {
        $this->linhaOfx('DRY1');
        $antes = $this->countCanonOfx();

        $this->runBackfill(['--dry' => true]);

        $this->assertSame($antes, $this->countCanonOfx(), 'dry-run nao pode escrever na canonica');
    }

    public function test_backfill_move_linha_ofx_com_external_id_e_origem(): void
    {
        $this->linhaOfx('MOVE1', ['valor' => 1500.0000, 'tipo' => 'credit']);

        $this->runBackfill();

        $row = DB::table('fin_extrato_lancamentos')
            ->where('business_id', $this->business->id)
            ->where('external_id', 'ofx:' . $this->pfx . 'MOVE1')
            ->first();

        $this->assertNotNull($row, 'linha OFX deve aparecer na canonica');
        $this->assertSame('ofx', $row->origem);
        $this->assertSame('C', $row->tipo);               // credit -> C
        $this->assertSame(1500.0, (float) $row->valor);   // abs, positivo
        $this->assertSame('extrato.ofx', $row->source_file);
    }

    public function test_preserva_created_at_e_workflow_conciliacao(): void
    {
        $this->linhaOfx('PRES1', [
            'status' => 'conciliado',
            'titulo_id' => null, // sem FK real, mas status preservado
            'created_at' => now()->subDays(10),
        ]);

        $this->runBackfill();

        $row = DB::table('fin_extrato_lancamentos')
            ->where('external_id', 'ofx:' . $this->pfx . 'PRES1')->first();

        $this->assertSame('conciliado', $row->status, 'status de conciliacao preservado');
        // created_at preservado (10 dias atras, nao now())
        $this->assertTrue(
            \Illuminate\Support\Carbon::parse($row->created_at)->lt(now()->subDays(5)),
            'created_at original preservado (nao now())'
        );
    }

    public function test_idempotente_roda_2x_nao_duplica(): void
    {
        $this->linhaOfx('IDEM1');

        $this->runBackfill();
        $depois1 = $this->countCanonOfx();

        $this->runBackfill();
        $depois2 = $this->countCanonOfx();

        $this->assertSame(1, $depois1);
        $this->assertSame($depois1, $depois2, 'segundo backfill nao pode duplicar');
    }

    public function test_external_id_prefixado_nao_colide_com_api(): void
    {
        // Linha API com idempotency_key = "123"; linha OFX com fitid = "123".
        // Prefixos diferentes -> 2 linhas distintas, sem colisao.
        $apiKey = $this->pfx . '123';
        DB::table('fin_extrato_lancamentos')->insert([
            'business_id' => $this->business->id,
            'conta_bancaria_id' => $this->contaId,
            'origem' => 'api',
            'data' => now()->toDateString(),
            'valor' => 50.00,
            'tipo' => 'C',
            'descricao' => $this->pfx . 'api colisao',
            'idempotency_key' => $apiKey,
            'raw_payload' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->linhaOfx('123', ['fitid' => $this->pfx . '123']);

        $this->runBackfill();

        $apiRow = DB::table('fin_extrato_lancamentos')->where('external_id', 'api:' . $apiKey)->first();
        $ofxRow = DB::table('fin_extrato_lancamentos')->where('external_id', 'ofx:' . $this->pfx . '123')->first();

        $this->assertNotNull($apiRow, 'API recebe external_id api:<key>');
        $this->assertNotNull($ofxRow, 'OFX recebe external_id ofx:<fitid>');
        $this->assertNotSame($apiRow->id, $ofxRow->id, 'sao 2 linhas distintas (prefixo evita colisao)');

        // cleanup da linha API extra
        DB::table('fin_extrato_lancamentos')->where('external_id', 'api:' . $apiKey)->delete();
    }

    public function test_linha_ofx_sem_conta_usa_conta_avulso(): void
    {
        $this->linhaOfx('NOCONTA', ['conta_bancaria_id' => null]);

        $this->runBackfill();

        $row = DB::table('fin_extrato_lancamentos')
            ->where('external_id', 'ofx:' . $this->pfx . 'NOCONTA')->first();
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, (int) $row->conta_bancaria_id, 'deve ter conta (OFX avulso)');

        $conta = DB::table('fin_contas_bancarias')->where('id', $row->conta_bancaria_id)->first();
        $this->assertSame('OFX', $conta->banco_codigo, 'conta avulso tem banco_codigo OFX');
    }

    public function test_tier0_nao_toca_outro_business(): void
    {
        $bizB = \App\Business::where('id', '!=', $this->business->id)->first();
        if (! $bizB) {
            $this->markTestSkipped('Precisa 2+ businesses reais.');
        }

        // Linha OFX do business B.
        $ofxB = DB::table('fin_bank_statement_lines')->insertGetId([
            'business_id' => $bizB->id,
            'conta_bancaria_id' => null,
            'fitid' => $this->pfx . 'BIZB',
            'data_movimento' => now()->toDateString(),
            'descricao' => $this->pfx . 'cross',
            'valor' => 10.0000,
            'tipo' => 'credit',
            'status' => 'pendente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // Backfill SO do business atual (A).
            $this->runBackfill();

            // A linha de B NAO pode ter sido migrada.
            $migradaB = DB::table('fin_extrato_lancamentos')
                ->where('external_id', 'ofx:' . $this->pfx . 'BIZB')->count();
            $this->assertSame(0, $migradaB, 'Tier 0: backfill de A nao toca OFX de B');
        } finally {
            DB::table('fin_bank_statement_lines')->where('id', $ofxB)->delete();
            DB::table('fin_extrato_lancamentos')->where('external_id', 'ofx:' . $this->pfx . 'BIZB')->delete();
        }
    }
}
