<?php

namespace Modules\Financeiro\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conciliação OFX — dedupe idempotente no upload (anti-race condition).
 *
 * Origem do fix: upload() usava check-then-insert (exists() + insert()). Dois
 * uploads concorrentes do mesmo arquivo (double-click / retry) passavam ambos no
 * exists() antes de qualquer insert, e o segundo insert estourava o unique
 * (business_id, fitid) "unique_fitid_per_biz" → QueryException → 500 na request
 * inteira. Fix: insertOrIgnore atômico — duplicado vira skip gracioso.
 *
 * Isolamento: FinanceiroTestCase roda contra o DB dev real (sem RefreshDatabase).
 * Usamos DatabaseTransactions pra dar rollback em TUDO (inclusive nos UPDATEs que
 * sugerirMatches() possa fazer em linhas pendentes pré-existentes) + fitids únicos
 * por execução (prefixo) pra os asserts valerem mesmo com a tabela já populada.
 *
 * @see \Modules\Financeiro\Http\Controllers\ConciliacaoController::upload()
 */
class ConciliacaoUploadDedupeTest extends FinanceiroTestCase
{
    use DatabaseTransactions;

    private const URL = '/financeiro/conciliacao/upload';

    /** Prefixo único por teste — garante fitids inéditos no DB dev real. */
    private string $pfx = '';

    protected function setUp(): void
    {
        parent::setUp();

        // FinanceiroTestCase roda contra o schema legacy UltimatePOS, que não migra
        // em SQLite. Convenção do módulo: skip fora de MySQL (igual
        // TransactionPaymentInertiaSmokeTest / Adr0175ObserverContaOpcionalTest).
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requer MySQL (UltimatePOS legacy schema — rode com DB_CONNECTION=mysql).');
        }

        $this->actAsAdmin(); // markTestSkipped se não houver business/user seedado

        if (! Schema::hasTable('fin_bank_statement_lines')) {
            $this->markTestSkipped('Tabela fin_bank_statement_lines ausente — rode php artisan migrate.');
        }

        $this->pfx = 'PEST-DEDUPE-' . uniqid() . '-';
    }

    protected function tearDown(): void
    {
        // Backstop caso o DatabaseTransactions não engate: remove só as linhas deste
        // teste. Guard $pfx !== '' — setUp pode ter dado markTestSkipped antes de inicializá-lo.
        if ($this->business && $this->pfx !== '') {
            DB::table('fin_bank_statement_lines')
                ->where('business_id', $this->business->id)
                ->where('fitid', 'like', $this->pfx . '%')
                ->delete();
        }

        parent::tearDown();
    }

    /**
     * Monta um arquivo OFX fake (SGML estilo banco) com os blocos STMTTRN dados.
     * Cada item: ['fitid' => sufixo, 'valor' => '-300.00'] — o prefixo único é aplicado aqui.
     *
     * @param  array<int,array<string,string>>  $transacoes
     */
    private function ofx(array $transacoes): UploadedFile
    {
        $blocos = '';
        foreach ($transacoes as $t) {
            $fitid = $this->pfx . $t['fitid'];
            $tipo = $t['tipo'] ?? 'DEBIT';
            $data = $t['data'] ?? '20260520';
            $valor = $t['valor'] ?? '-300.00';
            $memo = $t['memo'] ?? 'Pagamento teste';
            $blocos .= "<STMTTRN><TRNTYPE>{$tipo}<DTPOSTED>{$data}<TRNAMT>{$valor}<FITID>{$fitid}<MEMO>{$memo}</STMTTRN>\n";
        }

        $conteudo = "OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRNRS><BANKTRANLIST>\n{$blocos}</BANKTRANLIST></STMTTRNRS></BANKMSGSRSV1></OFX>";

        return UploadedFile::fake()->createWithContent('extrato.ofx', $conteudo);
    }

    /** Quantas linhas DESTE teste existem (escopo pelo prefixo único). */
    private function countMinhas(): int
    {
        return (int) DB::table('fin_bank_statement_lines')
            ->where('business_id', $this->business->id)
            ->where('fitid', 'like', $this->pfx . '%')
            ->count();
    }

    public function test_happy_path_importa_todas_as_transacoes_novas(): void
    {
        $response = $this->from(self::URL)->post(self::URL, [
            'arquivo' => $this->ofx([
                ['fitid' => '1', 'valor' => '-100.00'],
                ['fitid' => '2', 'valor' => '-200.00'],
            ]),
        ]);

        $response->assertRedirect(self::URL);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 2 novas'));

        $this->assertSame(2, $this->countMinhas());
    }

    /**
     * Núcleo do fix: um fitid já existente no banco é PULADO sem QueryException,
     * o count reflete só as linhas realmente novas, e a linha pré-existente NÃO
     * é sobrescrita (prova insertOrIgnore vs upsert).
     */
    public function test_fitid_duplicado_e_pulado_sem_excecao_e_count_correto(): void
    {
        // Linha já importada antes — e já conciliada. Valores-sentinela (descricao
        // + valor) diferentes do que o upload mandaria pro mesmo fitid: se o fix
        // fosse upsert, eles seriam sobrescritos; com insertOrIgnore, ficam intactos.
        // (Não setamos titulo_id real porque há FK pra fin_titulos.)
        DB::table('fin_bank_statement_lines')->insert([
            'business_id' => $this->business->id,
            'fitid' => $this->pfx . 'DUP',
            'data_movimento' => now()->toDateString(),
            'descricao' => 'SENTINELA pre-existente conciliada',
            'valor' => -999.0000,
            'tipo' => 'debit',
            'status' => 'conciliado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(self::URL)->post(self::URL, [
            'arquivo' => $this->ofx([
                ['fitid' => 'DUP', 'valor' => '-100.00'], // duplicado → pula
                ['fitid' => 'NEW', 'valor' => '-200.00'], // novo → importa
            ]),
        ]);

        // Sem 500 / QueryException — redirect com flash de sucesso.
        $response->assertRedirect(self::URL);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 1 novas'));

        // Uma linha por fitid (duplicado não recriou; novo entrou) → total 2.
        $this->assertSame(2, $this->countMinhas());
        $this->assertSame(1, (int) DB::table('fin_bank_statement_lines')
            ->where('business_id', $this->business->id)->where('fitid', $this->pfx . 'DUP')->count());
        $this->assertSame(1, (int) DB::table('fin_bank_statement_lines')
            ->where('business_id', $this->business->id)->where('fitid', $this->pfx . 'NEW')->count());

        // insertOrIgnore (não upsert): a linha pré-existente conciliada ficou INTACTA
        // — status, descrição e valor são os da sentinela, NÃO os do arquivo.
        $dup = DB::table('fin_bank_statement_lines')
            ->where('business_id', $this->business->id)->where('fitid', $this->pfx . 'DUP')->first();
        $this->assertSame('conciliado', $dup->status);
        $this->assertSame('SENTINELA pre-existente conciliada', $dup->descricao);
        $this->assertSame(-999.0, (float) $dup->valor);
    }

    /**
     * Double-click / retry do MESMO arquivo: segundo upload não duplica nem 500.
     * Modela o cenário exato da race (reenvio do mesmo OFX).
     */
    public function test_upload_duplicado_double_click_e_idempotente(): void
    {
        $arquivo = fn () => $this->ofx([
            ['fitid' => 'A', 'valor' => '-10.00'],
            ['fitid' => 'B', 'valor' => '-20.00'],
        ]);

        $r1 = $this->from(self::URL)->post(self::URL, ['arquivo' => $arquivo()]);
        $r1->assertSessionHasNoErrors();
        $r1->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 2 novas'));

        // Segundo clique do mesmo arquivo — tudo já existe.
        $r2 = $this->from(self::URL)->post(self::URL, ['arquivo' => $arquivo()]);
        $r2->assertRedirect(self::URL);
        $r2->assertSessionHasNoErrors();
        $r2->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 0 novas'));

        // Continua 2 (sem duplicar).
        $this->assertSame(2, $this->countMinhas());
    }

    /** Tier 0 (ADR 0093): toda linha importada carrega o business_id do tenant. */
    public function test_todas_as_linhas_recebem_business_id_do_tenant(): void
    {
        $this->from(self::URL)->post(self::URL, [
            'arquivo' => $this->ofx([
                ['fitid' => 'T1'],
                ['fitid' => 'T2'],
            ]),
        ]);

        // Nenhuma linha deste teste pode ter vazado pra outro business.
        $foraDoTenant = (int) DB::table('fin_bank_statement_lines')
            ->where('fitid', 'like', $this->pfx . '%')
            ->where('business_id', '!=', $this->business->id)
            ->count();

        $this->assertSame(0, $foraDoTenant);
        $this->assertSame(2, $this->countMinhas());
    }
}
