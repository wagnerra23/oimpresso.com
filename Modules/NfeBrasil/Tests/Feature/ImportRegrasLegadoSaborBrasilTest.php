<?php

declare(strict_types=1);

// @covers UC-NFIM-05 · UC-NFIM-06 — carga das regras tributárias do legado por CSV versionado (Sabor Brasil).

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * O CSV é o arquivo real gerado do Firebird do Sabor Brasil (extract-regras-fiscais.py, natureza 998
 * no Regime Normal e 999 no Simples). Os números esperados vêm do próprio Delphi, por dois caminhos:
 *   (a) a configuração: redução 61,11% / 33,33%, ICMS 18%;
 *   (b) as notas emitidas desde 2024: base ÷ valor = 0,3889 em 689 itens CST 20 e 0,6667 em 444
 *       itens CST 10; ICMS ÷ base = 0,18 nos dois.
 * Valor 1.000,00 → CST 20: base 388,90 e ICMS 70,00 · CST 10: base 666,70 e ICMS 120,01.
 * MySQL-only · tenant fictício 98 (ADR 0358).
 */
const SB_CSV = __DIR__ . '/../../../../scripts/legacy-migration/data/sabor-brasil-regras-fiscais.csv';
const SB_NCMS = ['07032090', '07129090', '21039021', '21039029'];

function sbLimpar(int $biz): void
{
    DB::table('nfe_fiscal_rules')->where('business_id', $biz)->whereIn('ncm', SB_NCMS)->delete();
}

function sbMotor(string $ncm, int $crt)
{
    return (new MotorTributarioService())->calcularComRegimeDoLocal(
        new ProdutoFiscalContext(ncm: $ncm, valor: 1000.00), businessId: test()->seededTenant()->id,
        ufOrigem: 'SP', ufDestino: 'SP', crtEmitente: $crt,
        aliquotaInternaDestino: 0.18, destinatarioContribuinte: true,
    );
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: nfe_fiscal_rules com crt/reducao_base.');
    }
    if (! Schema::hasColumn('nfe_fiscal_rules', 'crt') || ! Schema::hasColumn('nfe_fiscal_rules', 'reducao_base')) {
        $this->markTestSkipped('Migrações de crt / reducao_base não rodaram.');
    }
    NfeFiscalRule::esquecerVersionamento();
    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
    sbLimpar(test()->seededTenant()->id);
});

afterEach(fn () => sbLimpar(test()->seededTenant()->id));

it('UC-NFIM-06 · sem --apply o comando confere as 8 regras e não grava nenhuma', function () {
    $biz = test()->seededTenant()->id;

    $codigo = Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => SB_CSV]);

    expect($codigo)->toBe(0);
    expect(Artisan::output())->toContain('DRY-RUN');
    expect(DB::table('nfe_fiscal_rules')->where('business_id', $biz)->whereIn('ncm', SB_NCMS)->count())->toBe(0);
});

it('UC-NFIM-06 · com --apply grava 8 regras; rodar de novo não duplica', function () {
    $biz = test()->seededTenant()->id;

    Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => SB_CSV, '--apply' => true]);
    Artisan::call('nfe:importar-regras-csv', ['business' => $biz, 'arquivo' => SB_CSV, '--apply' => true]);

    expect(Artisan::output())->toContain('criadas 0 · atualizadas 8 · falhas 0');
    expect(DB::table('nfe_fiscal_rules')->where('business_id', $biz)->whereIn('ncm', SB_NCMS)->count())->toBe(8);
    expect(DB::table('nfe_fiscal_rules')->where('business_id', '<>', $biz)->whereIn('ncm', SB_NCMS)->count())->toBe(0);
});

it('UC-NFIM-05 · mesmo NCM, uma regra por regime: CST 20 com redução no Normal, CSOSN 400 no Simples', function () {
    Artisan::call('nfe:importar-regras-csv', ['business' => test()->seededTenant()->id, 'arquivo' => SB_CSV, '--apply' => true]);

    $normal = sbMotor('07129090', 3);
    expect($normal->cst)->toBe('20');
    expect($normal->cfop)->toBe('5102');
    expect($normal->base_icms)->toBe(388.9);
    expect($normal->valor_icms)->toBe(70.0);

    $simples = sbMotor('07129090', 1);
    expect($simples->csosn)->toBe('400');
    expect($simples->valor_icms)->toBe(0.0);
});

it('UC-NFIM-05 · condimento no Normal sai como o Delphi emite hoje: CST 10, base reduzida 33,33%, sem ST', function () {
    Artisan::call('nfe:importar-regras-csv', ['business' => test()->seededTenant()->id, 'arquivo' => SB_CSV, '--apply' => true]);

    $t = sbMotor('21039021', 3);

    expect($t->cst)->toBe('10');
    expect($t->cfop)->toBe('5405');
    expect($t->base_icms)->toBe(666.7);
    expect($t->valor_icms)->toBe(120.01);
    expect($t->valor_st)->toBe(0.0);
});
