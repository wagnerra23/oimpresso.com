<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Financeiro\Services\ExportacaoContabilService;

uses(Tests\TestCase::class);

/**
 * Exportação contábil por competência (US-FIN-077), casos UC-EXPC-01..05. Backend sem tela ainda:
 * a US está no SPEC e os casos vivem aqui; o .casos.md nasce com a tela (regra da rodada 27/10).
 *
 * Leiautes derivados da documentação oficial de cada fornecedor:
 *  - Domínio: "Leiaute Domínio Sistemas com Separador", suporte.dominioatendimento.com
 *    solução 672, registros 0000/6000/6100; o exemplo oficial `exemplo_arquivo__lancamento_lote.txt`
 *    é `|0000|33333333000191|` / `|6000|X||||` / `|6100|10/09/2015|55|5|5571,24|1|DESCRICAO...|GERENTE|||` (pii-allowlist — CNPJ fictício do exemplo oficial).
 *  - Alterdata: "Importação Personalizada - Texto", ajuda.alterdata.com.br artigo 150435497; o
 *    arquivo-modelo oficial tem a linha `"","16","814","01/01/2025","110,00","1","Serviço Prestado","002","002","NF1"`.
 *
 * Regra mestre de valor: o total exportado é conferido contra a soma independente das baixas
 * (dois caminhos). Tenant 98 (ADR 0358), competência isolada 2031-03, transação desfeita no fim.
 *
 * @covers-us US-FIN-077
 */

const EXPC_MES = '2031-03';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema MySQL do Financeiro.');
    }
    if (! Schema::hasTable('fin_contabil_contas')) {
        $this->markTestSkipped('Tabela fin_contabil_contas ausente — rode migrate.');
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->userId = (int) (User::where('business_id', $this->bizId)->value('id') ?? 0);
    if ($this->userId === 0) {
        $this->markTestSkipped('Sem usuário no tenant de teste.');
    }
    DB::beginTransaction();

    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $this->bizId, 'name' => 'ZZ EXPC banco', 'account_number' => '9031',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->bancoId = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $this->bizId, 'account_id' => $accountId, 'banco_codigo' => '000',
        'agencia' => '0', 'carteira' => '-', 'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture
        'beneficiario_razao_social' => 'ZZ EXPC', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->planoId = DB::table('fin_planos_conta')->insertGetId([
        'business_id' => $this->bizId, 'codigo' => '9.9.31.001', 'nome' => 'ZZ EXPC receita', 'tipo' => 'receita',
        'nivel' => 4, 'natureza' => 'credito', 'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function expcTitulo(int $biz, int $user, int $plano, string $tipo, string $numero): int
{
    return DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz, 'numero' => $numero, 'tipo' => $tipo, 'status' => 'quitado',
        'cliente_descricao' => 'Cliente Ficticio', 'valor_total' => 100, 'valor_aberto' => 0,
        'emissao' => EXPC_MES.'-01', 'vencimento' => EXPC_MES.'-10', 'competencia_mes' => EXPC_MES,
        'origem' => 'manual', 'plano_conta_id' => $plano, 'created_by' => $user,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function expcBaixa(int $biz, int $user, int $titulo, int $banco, string $dia, float $valor, float $juros = 0, float $desconto = 0): void
{
    DB::table('fin_titulo_baixas')->insert([
        'business_id' => $biz, 'titulo_id' => $titulo, 'conta_bancaria_id' => $banco,
        'valor_baixa' => $valor, 'juros' => $juros, 'multa' => 0, 'desconto' => $desconto,
        'data_baixa' => EXPC_MES.'-'.$dia, 'meio_pagamento' => 'pix',
        'idempotency_key' => (string) Str::uuid(), 'created_by' => $user,
    ]);
}

function expcDepara(int $biz, string $tipo, int $id, string $codigo): void
{
    DB::table('fin_contabil_contas')->insert([
        'business_id' => $biz, 'origem_tipo' => $tipo, 'origem_id' => $id, 'codigo' => $codigo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-EXPC-01: Domínio sai no leiaute oficial com separador (0000/6000/6100)', function () {
    $t = expcTitulo($this->bizId, $this->userId, $this->planoId, 'receber', 'ZZ-R1');
    expcBaixa($this->bizId, $this->userId, $t, $this->bancoId, '15', 5571.24);
    expcDepara($this->bizId, 'conta_bancaria', $this->bancoId, '5');
    expcDepara($this->bizId, 'plano_conta', $this->planoId, '55');

    $svc = app(ExportacaoContabilService::class);
    $r = $svc->lancamentos($this->bizId, EXPC_MES);
    expect($r['pendencias'])->toBe([]);

    // CNPJ do arquivo de exemplo oficial do Domínio (fictício, não é de cliente).
    $cnpjExemplo = str_repeat('3', 8).'000191';
    $arquivo = $svc->gerar('dominio', $cnpjExemplo, $r['lancamentos']);
    $linhas = explode("\r\n", rtrim($arquivo, "\r\n"));

    expect($linhas[0])->toBe('|0000|'.$cnpjExemplo.'|')
        ->and($linhas[1])->toBe('|6000|X||||')
        // receber: débito no banco (5), crédito na receita (55) — mesma forma do 6100 oficial
        ->and($linhas[2])->toBe('|6100|15/03/2031|5|55|5571,24||Recebimento ZZ-R1 Cliente Ficticio||||');
});

it('UC-EXPC-02: Alterdata sai no leiaute de importação personalizada em texto', function () {
    $t = expcTitulo($this->bizId, $this->userId, $this->planoId, 'pagar', 'ZZ-P1');
    expcBaixa($this->bizId, $this->userId, $t, $this->bancoId, '07', 110.00);
    expcDepara($this->bizId, 'conta_bancaria', $this->bancoId, '16');
    expcDepara($this->bizId, 'plano_conta', $this->planoId, '814');

    $svc = app(ExportacaoContabilService::class);
    $arquivo = $svc->gerar('alterdata', '', $svc->lancamentos($this->bizId, EXPC_MES)['lancamentos']);

    // pagar: débito na conta do título (814), crédito no banco (16)
    expect($arquivo)->toBe('"","814","16","07/03/2031","110,00","","Pagamento ZZ-P1 Cliente Ficticio","","","ZZ-P1"'."\r\n");
});

it('UC-EXPC-03: juros e desconto viram linhas próprias e o líquido fecha com o banco (dois caminhos)', function () {
    $t = expcTitulo($this->bizId, $this->userId, $this->planoId, 'receber', 'ZZ-R2');
    expcBaixa($this->bizId, $this->userId, $t, $this->bancoId, '20', 1000.10, 12.35, 3.40);
    expcDepara($this->bizId, 'conta_bancaria', $this->bancoId, '5');
    expcDepara($this->bizId, 'plano_conta', $this->planoId, '55');
    expcDepara($this->bizId, 'juros', 0, '70');
    expcDepara($this->bizId, 'desconto', 0, '80');

    $l = app(ExportacaoContabilService::class)->lancamentos($this->bizId, EXPC_MES)['lancamentos'];

    // caminho 1: movimento líquido no banco pelos lançamentos gerados
    $banco = 0;
    foreach ($l as $x) {
        $banco += ($x['debito'] === '5' ? $x['centavos'] : 0) - ($x['credito'] === '5' ? $x['centavos'] : 0);
    }
    // caminho 2: soma independente direto do banco de dados
    $esperado = (int) round((float) DB::table('fin_titulo_baixas')->where('business_id', $this->bizId)
        ->whereBetween('data_baixa', [EXPC_MES.'-01', EXPC_MES.'-31'])
        ->selectRaw('SUM(valor_baixa + juros + multa - desconto) as t')->value('t') * 100);

    expect($l)->toHaveCount(3)
        ->and($banco)->toBe(100905)
        ->and($banco)->toBe($esperado);
});

it('UC-EXPC-04: sem código no de-para o arquivo não sai e a pendência é listada', function () {
    $t = expcTitulo($this->bizId, $this->userId, $this->planoId, 'receber', 'ZZ-R3');
    expcBaixa($this->bizId, $this->userId, $t, $this->bancoId, '05', 50);
    expcDepara($this->bizId, 'conta_bancaria', $this->bancoId, '5');

    $r = app(ExportacaoContabilService::class)->lancamentos($this->bizId, EXPC_MES);

    expect($r['lancamentos'])->toBe([])
        ->and($r['pendencias'])->toHaveCount(1)
        ->and($r['pendencias'][0])->toContain('plano de contas sem código contábil');
});

it('UC-EXPC-05: baixa de outra empresa não entra no arquivo', function () {
    $t = expcTitulo($this->bizId, $this->userId, $this->planoId, 'receber', 'ZZ-R4');
    expcBaixa($this->bizId, $this->userId, $t, $this->bancoId, '09', 10);
    expcDepara($this->bizId, 'conta_bancaria', $this->bancoId, '5');
    expcDepara($this->bizId, 'plano_conta', $this->planoId, '55');

    $outro = (int) DB::table('business')->where('id', '!=', $this->bizId)->value('id');
    $r = app(ExportacaoContabilService::class)->lancamentos($outro, EXPC_MES);

    expect(collect($r['lancamentos'])->pluck('documento')->all())->not->toContain('ZZ-R4')
        ->and(app(ExportacaoContabilService::class)->lancamentos($this->bizId, EXPC_MES)['lancamentos'])->toHaveCount(1);
});
