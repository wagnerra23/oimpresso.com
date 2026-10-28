<?php

declare(strict_types=1);
// @covers-us US-FIN-084
// Cobre UC-EXT-07..08 (resources/js/Pages/Financeiro/Extrato/Index.casos.md · US-FIN-084).

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Http\Controllers\ConciliacaoController;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Services\Extrato\ImportarExtratoOfxService;
use Modules\RecurringBilling\Dto\StatementLineDto;
use Modules\RecurringBilling\Services\Banking\StatementPersister;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * OFX por conta + gravação comum dos drivers de extrato. Regra mestre de VALOR: o sinal de
 * cada lançamento é conferido por DOIS caminhos — a linha gravada (`tipo` + `valor`) e o
 * valor com sinal que a Conciliação monta para ela (`normalizeApi`, o método da tela) —, e o
 * total do arquivo bate com o resumo devolvido pelo serviço.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99. NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_* depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_extrato_lancamentos') || ! Schema::hasColumn('fin_extrato_lancamentos', 'external_id')) {
        $this->markTestSkipped('fin_extrato_lancamentos sem colunas da ADR 0236 — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    config(['financeiro.extrato_bancos.ofx_por_conta' => true]);
});

function ofxConta(int $biz, string $banco = '237'): int
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'PEST-OFX-'.uniqid(), 'account_number' => '999',
        'created_at'  => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('fin_contas_bancarias')->insertGetId([
        'business_id'               => $biz,
        'account_id'                => $accountId,
        'banco_codigo'              => $banco,
        'agencia'                   => '0001',
        'carteira'                  => '09',
        'beneficiario_documento'    => '00.000.000/0000-00', // pii-allowlist — CNPJ fixture de teste (nao-PII real)
        'beneficiario_razao_social' => 'Teste OFX',
        'created_at'                => now(),
        'updated_at'                => now(),
    ]);
}

/** OFX 1.x (SGML, sem tags de fechamento nos campos) como o Bradesco exporta: vírgula decimal. */
function ofxArquivo(string $sufixo): string
{
    return "OFXHEADER:100\nDATA:OFXSGML\n<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>\n"
        ."<STMTTRN>\n<TRNTYPE>DEBIT\n<DTPOSTED>20311010120000[-3:BRT]\n<TRNAMT>-1.250,40\n<FITID>D-{$sufixo}\n<MEMO>PEST PAGTO BOLETO\n</STMTTRN>\n"
        ."<STMTTRN>\n<TRNTYPE>CREDIT\n<DTPOSTED>20311011\n<TRNAMT>320.00\n<FITID>C-{$sufixo}\n<NAME>PEST PIX RECEBIDO\n</STMTTRN>\n"
        ."</BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";
}

function ofxValorNaConciliacao(int $linhaId): float
{
    $linha = DB::table('fin_extrato_lancamentos')->where('id', $linhaId)->first();
    $ref = new ReflectionMethod(ConciliacaoController::class, 'normalizeApi');
    $ref->setAccessible(true);

    return (float) $ref->invoke(new ConciliacaoController(), $linha)['valor'];
}

it('UC-EXT-07 · OFX entra no extrato da conta com o sinal certo, lido igual pela Conciliação, sem duplicar', function () {
    $conta = ofxConta($this->b98);
    $sufixo = uniqid();
    $service = app(ImportarExtratoOfxService::class);

    $r = $service->importar($this->b98, $conta, ofxArquivo($sufixo), 'extrato-bradesco.ofx');

    // Caminho 1: resumo do arquivo.
    expect($r['lidas'])->toBe(2);
    expect($r['novas'])->toBe(2);
    expect($r['debitos'])->toBe(1250.40);
    expect($r['creditos'])->toBe(320.0);

    // Caminho 2: a linha gravada e o valor com sinal que a Conciliação monta.
    $deb = DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)->where('external_id', "ofx:D-{$sufixo}")->first();
    $cre = DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)->where('external_id', "ofx:C-{$sufixo}")->first();
    expect($deb->tipo)->toBe('D');
    expect((float) $deb->valor)->toBe(1250.40);
    expect($deb->origem)->toBe('ofx');
    expect((int) $deb->business_id)->toBe($this->b98);
    expect($deb->data)->toBe('2031-10-10');
    expect($cre->tipo)->toBe('C');
    expect(ofxValorNaConciliacao((int) $deb->id))->toBe(-1250.40);
    expect(ofxValorNaConciliacao((int) $cre->id))->toBe(320.0);

    // Reimportar o mesmo arquivo: nada novo.
    $r2 = $service->importar($this->b98, $conta, ofxArquivo($sufixo), 'extrato-bradesco.ofx');
    expect($r2['novas'])->toBe(0);
    expect($r2['existentes'])->toBe(2);
    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)->count())->toBe(2);
});

it('UC-EXT-07 · Tier 0: conta de outro business não recebe o OFX', function () {
    $contaDo99 = ofxConta($this->b99);

    expect(fn () => app(ImportarExtratoOfxService::class)->importar($this->b98, $contaDo99, ofxArquivo(uniqid())))
        ->toThrow(ModelNotFoundException::class);
    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $contaDo99)->count())->toBe(0);
});

it('UC-EXT-07 · comando com a flag desligada não grava; ligada, grava e o --dry só conta', function () {
    $conta = ofxConta($this->b98);
    $arquivo = tempnam(sys_get_temp_dir(), 'ofx');
    file_put_contents($arquivo, ofxArquivo(uniqid()));
    $args = ['--business' => $this->b98, '--conta' => $conta, '--arquivo' => $arquivo];

    config(['financeiro.extrato_bancos.ofx_por_conta' => false]);
    Artisan::call('financeiro:importar-extrato-ofx', $args);
    expect(Artisan::output())->toContain('desligada');
    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)->count())->toBe(0);

    config(['financeiro.extrato_bancos.ofx_por_conta' => true]);
    Artisan::call('financeiro:importar-extrato-ofx', $args + ['--dry' => true]);
    expect(Artisan::output())->toContain('[dry] lidas=2 novas=0');
    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)->count())->toBe(0);

    Artisan::call('financeiro:importar-extrato-ofx', $args);
    expect(Artisan::output())->toContain('lidas=2 novas=2');
    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)->count())->toBe(2);
});

it('UC-EXT-08 · linha de API grava pelo mesmo caminho: positivo + C/D, business da conta, external_id api:', function () {
    $conta = ContaBancaria::query()->withoutGlobalScopes()->findOrFail(ofxConta($this->b98, '077'));
    $chave = 'inter-'.uniqid();

    $r = app(StatementPersister::class)->gravar($conta, collect([new StatementLineDto(
        data: Carbon::parse('2031-10-12'), valor: 89.9, tipo: 'D', descricao: 'PEST TARIFA',
        contraparteDocumento: null, contraparteNome: null, idempotencyKey: $chave, raw: ['idTransacao' => $chave],
    )]));

    expect($r)->toBe(['novas' => 1, 'existentes' => 0]);
    $linha = DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta->id)->first();
    expect($linha->origem)->toBe('api');
    expect($linha->external_id)->toBe("api:{$chave}");
    expect((int) $linha->business_id)->toBe($this->b98);
    expect(ofxValorNaConciliacao((int) $linha->id))->toBe(-89.9);
});
