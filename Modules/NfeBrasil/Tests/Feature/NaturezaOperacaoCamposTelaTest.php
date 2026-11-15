<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — naturezas de operação, onda 2 (RUNBOOK-tributacao §Naturezas).
// Contrato: as colunas novas nascem com o comportamento de hoje, e o motor ainda não as lê.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeOperacaoFiscal;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * MySQL-only · tenant fictício 98 (`seededTenant`) — nunca biz=1 nem biz=4 (ADR 0358).
 * Os casos vêm do plano do RUNBOOK (onda 2 não muda valor), não do código.
 */

const NOC_NCM = '77071003';

function nocBiz(): int
{
    return test()->seededTenant()->id;
}

function nocLimpar(): void
{
    DB::table('nfe_operacoes_fiscais')->where('business_id', nocBiz())->where('slug', 'like', 'noc-%')->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: nfe_operacoes_fiscais vem da migração 2026_10_07_000001.');
    }
    if (! Schema::hasColumn('nfe_operacoes_fiscais', 'cfop_interestadual')) {
        $this->markTestSkipped('Migração 2026_11_15_000001 não rodou — rode as migrations do módulo.');
    }
    NfeFiscalRule::esquecerVersionamento();
    nocLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_operacoes_fiscais')) {
        return;
    }
    nocLimpar();
});

it('as colunas novas nascem com o comportamento de hoje', function () {
    $id = (int) DB::table('nfe_operacoes_fiscais')->insertGetId([
        'business_id' => nocBiz(), 'slug' => 'noc-sem-campos', 'nome' => 'Inserida como antes da onda 2',
        'finalidade' => 1, 'cfop' => '?102', 'padrao' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $op = NfeOperacaoFiscal::withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: teste lê por id do tenant 98

    expect($op->ativa)->toBeTrue()
        ->and($op->pede_nota_referenciada)->toBeFalse()
        ->and($op->tipo)->toBeNull()
        ->and($op->cfop_interestadual)->toBeNull()
        ->and($op->cfop_exterior)->toBeNull()
        ->and($op->cfop)->toBe('?102');
});

it('preencher os campos novos não muda o cálculo (o motor só passa a ler na onda 4)', function () {
    // Regra geral da natureza: CFOP ?102, CSOSN 102, ICMS 1,86%, PIS 0,65%, COFINS 3%.
    $geral = ['cfop' => '?102', 'csosn' => '102', 'aliquota_icms' => 0.0186, 'aliquota_pis' => 0.0065, 'aliquota_cofins' => 0.03];
    $id = (int) DB::table('nfe_operacoes_fiscais')->insertGetId([
        'business_id' => nocBiz(), 'slug' => 'noc-revenda', 'nome' => 'Revenda',
        'finalidade' => 1, 'cfop' => '?102', 'regra_geral' => json_encode($geral), 'padrao' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $ctx = new ProdutoFiscalContext(ncm: NOC_NCM, valor: 1000.0);

    $antesMesmaUf = (new MotorTributarioService())->calcularComDestino($ctx, nocBiz(), 'SC', 'SC', operacaoId: $id);
    $antesOutraUf = (new MotorTributarioService())->calcularComDestino($ctx, nocBiz(), 'SC', 'PR', operacaoId: $id);

    DB::table('nfe_operacoes_fiscais')->where('id', $id)->update([
        'tipo' => 'saida', 'pede_nota_referenciada' => true,
        'cfop_interestadual' => '6404', 'cfop_exterior' => '7102',
    ]);

    $depoisMesmaUf = (new MotorTributarioService())->calcularComDestino($ctx, nocBiz(), 'SC', 'SC', operacaoId: $id);
    $depoisOutraUf = (new MotorTributarioService())->calcularComDestino($ctx, nocBiz(), 'SC', 'PR', operacaoId: $id);

    // Campo a campo igual. Conta à mão: ICMS 1.000 × 0,0186 = 18,60 · PIS 6,50 · COFINS 30,00.
    // O CFOP fora do estado segue o "?" (6102), não o 6404 gravado: a onda 2 é inerte.
    expect(get_object_vars($depoisMesmaUf))->toBe(get_object_vars($antesMesmaUf))
        ->and(get_object_vars($depoisOutraUf))->toBe(get_object_vars($antesOutraUf))
        ->and($depoisMesmaUf->cfop)->toBe('5102')
        ->and($depoisOutraUf->cfop)->toBe('6102')
        ->and($depoisMesmaUf->valor_icms)->toBe(18.6)
        ->and($depoisMesmaUf->valor_pis)->toBe(6.5)
        ->and($depoisMesmaUf->valor_cofins)->toBe(30.0);
});
