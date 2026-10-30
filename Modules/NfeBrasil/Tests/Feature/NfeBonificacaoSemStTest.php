<?php

declare(strict_types=1);

// @covers UC-FNFE-59 · UC-FNFE-60 — bonificação com a regra da operação fiscal dela (Sabor Brasil, item 2).

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * Delphi do Sabor Brasil, medido 2026-10-29 (Firebird, só leitura): "BONIFICAÇÃO SEM ST" é a
 * natureza 992 — condimento (grupo 14) sai CST 10 / CFOP 5910 sem ICMS e sem ST (52 itens, 0 com
 * ICMS); desidratado (grupo 9) sai CST 20 / 5910 com redução de 61,11% e ICMS 18%. A venda do mesmo
 * condimento sai CST 10 / 5405 com ICMS sobre base reduzida de 33,33% (natureza 998).
 * Valor 1.000,00 → bonificação do condimento: ICMS 0 · venda: base 666,70 e ICMS 120,01.
 * MySQL-only · tenant fictício 98 (ADR 0358).
 */
const SBB_DIR = __DIR__ . '/../../../../scripts/legacy-migration/data/';
const SBB_NCMS = ['07032090', '07129090', '21039021', '21039029'];

function sbbLimpar(): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('nfe_fiscal_rules', 'operacao_id')) {
        return;
    }
    $biz = test()->seededTenant()->id;
    DB::table('nfe_fiscal_rules')->where('business_id', $biz)->whereIn('ncm', SBB_NCMS)->delete();
    DB::table('nfe_operacoes_fiscais')->where('business_id', $biz)->where('slug', 'bonificacao')->delete();
}

function sbbCarregar(bool $comOperacao = true): ?int
{
    $biz = test()->seededTenant()->id;
    $id = null;
    if ($comOperacao) {
        $id = (int) DB::table('nfe_operacoes_fiscais')->insertGetId([
            'business_id' => $biz, 'slug' => 'bonificacao', 'nome' => 'Bonificação', 'finalidade' => 1,
            'cfop' => '?910', 'padrao' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => SBB_DIR . 'sabor-brasil-regras-fiscais.csv', '--apply' => true]);
    Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => SBB_DIR . 'sabor-brasil-regras-fiscais-bonificacao.csv', '--apply' => true]);

    return $id;
}

function sbbItens(?int $operacaoId, int $crt = 3, string $ncm = '21039021'): array
{
    return (new NfeService(\Mockery::mock(CertificadoService::class)))->montarItensNfe(
        linhas: [['sell_line_id' => 1, 'product_id' => 9592, 'cprod' => 'TEMPERO', 'xprod' => 'Tempero',
            'ncm' => $ncm, 'unidade' => 'UN', 'quantidade' => 1.0, 'valor_unitario' => 1000.00]],
        valorNota: 1000.00, frete: 0, ncmDefault: '49111090', motor: new MotorTributarioService(),
        businessId: test()->seededTenant()->id, ufOrigem: 'SP', ufDestino: 'SP', internaDestino: 0.18,
        destinatarioContribuinte: true, crtEmitente: $crt, operacaoId: $operacaoId,
    );
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: nfe_fiscal_rules com operacao_id/crt e nfe_operacoes_fiscais.');
    }
    if (! Schema::hasColumn('nfe_fiscal_rules', 'operacao_id') || ! Schema::hasColumn('nfe_fiscal_rules', 'crt')) {
        $this->markTestSkipped('Migrações de operação / crt não rodaram.');
    }
    NfeFiscalRule::esquecerVersionamento();
    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
    sbbLimpar();
});

afterEach(function () {
    sbbLimpar();
    \Mockery::close();
});

it('UC-FNFE-59 · bonificação do condimento sai como o Delphi: CST 10, sem ICMS e sem ST; a venda do mesmo item leva ICMS 120,01', function () {
    $op = sbbCarregar();

    $bonif = sbbItens($op);
    $venda = sbbItens(null);

    expect($bonif['dets'][0]['icms']['cst_csosn'])->toBe('10');
    expect($bonif['total']['v_icms'])->toBe(0.0);
    expect($bonif['total']['v_st'])->toBe(0.0);
    expect($venda['dets'][0]['icms']['cst_csosn'])->toBe('10');
    expect($venda['total']['v_icms'])->toBe(120.01);
});

it('UC-FNFE-59 · bonificação do desidratado mantém a redução: CST 20, base 388,90, ICMS 70,00', function () {
    $op = sbbCarregar();

    $r = sbbItens($op, 3, '07129090');

    expect($r['dets'][0]['icms']['cst_csosn'])->toBe('20');
    expect($r['dets'][0]['icms']['vbc'])->toBe(388.9);
    expect($r['total']['v_icms'])->toBe(70.0);
});

it('UC-FNFE-60 · sem regra na operação (Simples), a bonificação tributa como a venda: CSOSN 400', function () {
    $op = sbbCarregar();

    $r = sbbItens($op, 1, '07129090');

    expect($r['dets'][0]['icms']['cst_csosn'])->toBe('400');
    expect($r['total']['v_icms'])->toBe(0.0);
});

it('UC-FNFE-60 · CSV com operação que a empresa não cadastrou falha a linha e não grava regra órfã', function () {
    sbbCarregar(comOperacao: false);
    $biz = test()->seededTenant()->id;

    expect(Artisan::output())->toContain('falhas 4');
    expect(DB::table('nfe_fiscal_rules')->where('business_id', $biz)->whereIn('ncm', SBB_NCMS)->whereNotNull('operacao_id')->count())->toBe(0);
});
