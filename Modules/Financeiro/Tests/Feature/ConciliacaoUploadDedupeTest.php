<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Tests\Feature\FinanceiroTestCase;

uses(FinanceiroTestCase::class, DatabaseTransactions::class);

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
 * ─────────────────────────────────────────────────────────────────────────────
 * 2026-08-02 — CONVERTIDO de classe PHPUnit para Pest `it()`, mesma receita já
 * aplicada no ConciliacaoLeExtratoApiTest (#5177) e pelo mesmo motivo MEDIDO: o
 * `casos-results-collect.mjs` (manifesto G-7) lê o UC-id do atributo `name` do
 * `<testcase>`. Método PHPUnit vira nome HUMANIZADO sem hífen; o regex canônico
 * (scripts/lib/uc-regex.mjs) exige `UC-FCC-NN`. Com o id em docblock, UC-FCC-01..03
 * rodavam VERDES na lane e valiam 0 no painel.
 *
 * NADA de asserção mudou — os 4 corpos são verbatim. Baseline a preservar, medido
 * no JUnit da lane (run 30768509891, PR #5178): 4 testcases · 0 fail · 0 skip.
 *
 * Contrato: resources/js/Pages/Financeiro/Conciliacao/Index.casos.md (UC-FCC-01..03).
 *
 * @see \Modules\Financeiro\Http\Controllers\ConciliacaoController::upload()
 */

const CUD_URL = '/financeiro/conciliacao/upload';

/** Guard de ambiente — MySQL + tabela presente. Skip gracioso (convenção do módulo). */
function cudGuard(): void
{
    // FinanceiroTestCase roda contra o schema legacy UltimatePOS, que não migra
    // em SQLite. Convenção do módulo: skip fora de MySQL (igual
    // TransactionPaymentInertiaSmokeTest / Adr0175ObserverContaOpcionalTest).
    if (DB::getDriverName() !== 'mysql') {
        test()->markTestSkipped('Requer MySQL (UltimatePOS legacy schema — rode com DB_CONNECTION=mysql).');
    }

    if (! Schema::hasTable('fin_bank_statement_lines')) {
        test()->markTestSkipped('Tabela fin_bank_statement_lines ausente — rode php artisan migrate.');
    }
}

/** Prefixo único por caso — garante fitids inéditos no DB dev real. */
function cudPfx(): string
{
    return 'PEST-DEDUPE-'.uniqid().'-';
}

/**
 * Monta um arquivo OFX fake (SGML estilo banco) com os blocos STMTTRN dados.
 * Cada item: ['fitid' => sufixo, 'valor' => '-300.00'] — o prefixo único é aplicado aqui.
 *
 * @param  array<int,array<string,string>>  $transacoes
 */
function cudOfx(string $pfx, array $transacoes): UploadedFile
{
    $blocos = '';
    foreach ($transacoes as $t) {
        $fitid = $pfx.$t['fitid'];
        $tipo = $t['tipo'] ?? 'DEBIT';
        $data = $t['data'] ?? '20260520';
        $valor = $t['valor'] ?? '-300.00';
        $memo = $t['memo'] ?? 'Pagamento teste';
        $blocos .= "<STMTTRN><TRNTYPE>{$tipo}<DTPOSTED>{$data}<TRNAMT>{$valor}<FITID>{$fitid}<MEMO>{$memo}</STMTTRN>\n";
    }

    $conteudo = "OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRNRS><BANKTRANLIST>\n{$blocos}</BANKTRANLIST></STMTTRNRS></BANKMSGSRSV1></OFX>";

    return UploadedFile::fake()->createWithContent('extrato.ofx', $conteudo);
}

/** Quantas linhas DESTE caso existem (escopo pelo prefixo único). */
function cudCount(int $businessId, string $pfx): int
{
    return (int) DB::table('fin_bank_statement_lines')
        ->where('business_id', $businessId)
        ->where('fitid', 'like', $pfx.'%')
        ->count();
}

/**
 * Backstop caso o DatabaseTransactions não engate: remove só as linhas do caso.
 * (Mantido do tearDown da versão em classe — mesma semântica.)
 */
function cudCleanup(int $businessId, string $pfx): void
{
    DB::table('fin_bank_statement_lines')
        ->where('business_id', $businessId)
        ->where('fitid', 'like', $pfx.'%')
        ->delete();
}

it('UC-FCC-01 · happy path importa todas as transações novas', function () {
    cudGuard();
    $this->actAsAdmin(); // markTestSkipped se não houver business/user seedado
    $pfx = cudPfx();

    try {
        $response = $this->from(CUD_URL)->post(CUD_URL, [
            'arquivo' => cudOfx($pfx, [
                ['fitid' => '1', 'valor' => '-100.00'],
                ['fitid' => '2', 'valor' => '-200.00'],
            ]),
        ]);

        $response->assertRedirect(CUD_URL);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 2 novas'));

        $this->assertSame(2, cudCount($this->business->id, $pfx));
    } finally {
        cudCleanup($this->business->id, $pfx);
    }
});

/**
 * Núcleo do fix: um fitid já existente no banco é PULADO sem QueryException,
 * o count reflete só as linhas realmente novas, e a linha pré-existente NÃO
 * é sobrescrita (prova insertOrIgnore vs upsert).
 */
it('UC-FCC-02 · fitid duplicado é pulado sem exceção e o count fica correto', function () {
    cudGuard();
    $this->actAsAdmin();
    $pfx = cudPfx();

    try {
        // Linha já importada antes — e já conciliada. Valores-sentinela (descricao
        // + valor) diferentes do que o upload mandaria pro mesmo fitid: se o fix
        // fosse upsert, eles seriam sobrescritos; com insertOrIgnore, ficam intactos.
        // (Não setamos titulo_id real porque há FK pra fin_titulos.)
        DB::table('fin_bank_statement_lines')->insert([
            'business_id' => $this->business->id,
            'fitid' => $pfx.'DUP',
            'data_movimento' => now()->toDateString(),
            'descricao' => 'SENTINELA pre-existente conciliada',
            'valor' => -999.0000,
            'tipo' => 'debit',
            'status' => 'conciliado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(CUD_URL)->post(CUD_URL, [
            'arquivo' => cudOfx($pfx, [
                ['fitid' => 'DUP', 'valor' => '-100.00'], // duplicado → pula
                ['fitid' => 'NEW', 'valor' => '-200.00'], // novo → importa
            ]),
        ]);

        // Sem 500 / QueryException — redirect com flash de sucesso.
        $response->assertRedirect(CUD_URL);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 1 novas'));

        // Uma linha por fitid (duplicado não recriou; novo entrou) → total 2.
        $this->assertSame(2, cudCount($this->business->id, $pfx));
        $this->assertSame(1, (int) DB::table('fin_bank_statement_lines')
            ->where('business_id', $this->business->id)->where('fitid', $pfx.'DUP')->count());
        $this->assertSame(1, (int) DB::table('fin_bank_statement_lines')
            ->where('business_id', $this->business->id)->where('fitid', $pfx.'NEW')->count());

        // insertOrIgnore (não upsert): a linha pré-existente conciliada ficou INTACTA
        // — status, descrição e valor são os da sentinela, NÃO os do arquivo.
        $dup = DB::table('fin_bank_statement_lines')
            ->where('business_id', $this->business->id)->where('fitid', $pfx.'DUP')->first();
        $this->assertSame('conciliado', $dup->status);
        $this->assertSame('SENTINELA pre-existente conciliada', $dup->descricao);
        $this->assertSame(-999.0, (float) $dup->valor);
    } finally {
        cudCleanup($this->business->id, $pfx);
    }
});

/**
 * Double-click / retry do MESMO arquivo: segundo upload não duplica nem 500.
 * Modela o cenário exato da race (reenvio do mesmo OFX).
 */
it('UC-FCC-02 · upload duplicado (double-click) é idempotente', function () {
    cudGuard();
    $this->actAsAdmin();
    $pfx = cudPfx();

    try {
        $arquivo = fn () => cudOfx($pfx, [
            ['fitid' => 'A', 'valor' => '-10.00'],
            ['fitid' => 'B', 'valor' => '-20.00'],
        ]);

        $r1 = $this->from(CUD_URL)->post(CUD_URL, ['arquivo' => $arquivo()]);
        $r1->assertSessionHasNoErrors();
        $r1->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 2 novas'));

        // Segundo clique do mesmo arquivo — tudo já existe.
        $r2 = $this->from(CUD_URL)->post(CUD_URL, ['arquivo' => $arquivo()]);
        $r2->assertRedirect(CUD_URL);
        $r2->assertSessionHasNoErrors();
        $r2->assertSessionHas('success', fn ($m) => str_contains((string) $m, 'processado: 0 novas'));

        // Continua 2 (sem duplicar).
        $this->assertSame(2, cudCount($this->business->id, $pfx));
    } finally {
        cudCleanup($this->business->id, $pfx);
    }
});

it('UC-FCC-03 · Tier 0 (ADR 0093): toda linha importada carrega o business_id do tenant', function () {
    cudGuard();
    $this->actAsAdmin();
    $pfx = cudPfx();

    try {
        $this->from(CUD_URL)->post(CUD_URL, [
            'arquivo' => cudOfx($pfx, [
                ['fitid' => 'T1'],
                ['fitid' => 'T2'],
            ]),
        ]);

        // Nenhuma linha deste teste pode ter vazado pra outro business.
        $foraDoTenant = (int) DB::table('fin_bank_statement_lines')
            ->where('fitid', 'like', $pfx.'%')
            ->where('business_id', '!=', $this->business->id)
            ->count();

        $this->assertSame(0, $foraDoTenant);
        $this->assertSame(2, cudCount($this->business->id, $pfx));
    } finally {
        cudCleanup($this->business->id, $pfx);
    }
});
