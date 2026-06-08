<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

uses(Tests\TestCase::class);

/**
 * US-NFE-010 fase 3 · Import CSV de regras tributárias.
 *
 * Pattern dual-mode (sessão 2026-05-10 — fix CI Modules Pest):
 *   - SQLite (CI sanity): schema não existe; cria isolado in-memory
 *   - MySQL (Pest local — gate Wagner): schema real; só limpa rows dos biz de teste
 *
 * Event::fake do bridge listener `SyncFiscalRuleToTaxRate` (ADR ARQ-0005)
 * porque tests de aplicar() validam contagem criadas/atualizadas, não a tax_rates
 * derivada — sem fake, listener tenta gravar em `nfe_fiscal_rule_tax_rate_links`
 * (ausente em SQLite CI) e falha → contagem aplicar() vai pra `falhas`. Listener
 * tem cobertura própria em SyncFiscalRuleToTaxRateTest.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_fiscal_rules');
        Schema::create('nfe_fiscal_rules', function ($t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->char('ncm', 8);
            $t->char('uf_origem', 2);
            $t->char('uf_destino', 2)->nullable();
            $t->char('cfop', 4);
            $t->char('csosn', 3)->nullable();
            $t->char('cst', 3)->nullable();
            $t->decimal('aliquota_icms', 7, 4)->default(0);
            $t->decimal('aliquota_pis', 7, 4)->default(0);
            $t->decimal('aliquota_cofins', 7, 4)->default(0);
            $t->decimal('aliquota_ipi', 7, 4)->default(0);
            $t->decimal('mva', 7, 4)->nullable();
            $t->decimal('fcp', 7, 4)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    } elseif (Schema::hasTable('nfe_fiscal_rules')) {
        // MySQL — schema real; limpa biz de teste (1 = Wagner WR2 ADR 0101, 99 = cross-tenant)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
            DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', [1, 99])->delete();
        }
        DB::table('nfe_fiscal_rules')->whereIn('business_id', [1, 99])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // Listener bridge não é o foco aqui — isola side effect no boot do model
    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_fiscal_rules');
    } elseif (Schema::hasTable('nfe_fiscal_rules')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
            DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', [1, 99])->delete();
        }
        DB::table('nfe_fiscal_rules')->whereIn('business_id', [1, 99])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
});

// ── helpers ──────────────────────────────────────────────────────────────

function csvHeader(): string
{
    return implode(',', ImportRegrasCsvService::COLUNAS_OBRIGATORIAS);
}

function csv(string ...$rows): string
{
    return csvHeader() . "\n" . implode("\n", $rows);
}

// ── parse() tests ────────────────────────────────────────────────────────

it('parse() retorna linhas válidas + sem erros pra CSV bem formado', function () {
    $csv = csv(
        '49019900,SP,,5102,102,,0.00,0.0065,0.03,0',
        '49019900,SP,RJ,6102,,000,0.18,0.0165,0.076,0',
    );

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toHaveCount(2)
        ->and($r['erros'])->toBe([])
        ->and($r['linhas'][0]['ncm'])->toBe('49019900')
        ->and($r['linhas'][0]['uf_destino'])->toBeNull()
        ->and($r['linhas'][0]['csosn'])->toBe('102')
        ->and($r['linhas'][0]['cst'])->toBeNull()
        ->and($r['linhas'][0]['aliquota_icms'])->toBe(0.0)
        ->and($r['linhas'][1]['uf_destino'])->toBe('RJ')
        ->and($r['linhas'][1]['cst'])->toBe('000');
});

it('parse() arquivo vazio retorna erro estrutural', function () {
    $r = (new ImportRegrasCsvService())->parse('');

    expect($r['linhas'])->toBe([])
        ->and($r['erros'])->toHaveCount(1)
        ->and($r['erros'][0]['motivo'])->toContain('vazio');
});

it('parse() cabeçalho ausente coluna obrigatória → erro estrutural', function () {
    $csv = "ncm,uf_origem,cfop\n49019900,SP,5102"; // faltam colunas

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toBe([])
        ->and($r['erros'][0]['motivo'])->toContain('Cabeçalho ausente');
});

it('parse() linha com NCM inválido → erro mas demais linhas seguem', function () {
    $csv = csv(
        '49019900,SP,,5102,102,,0,0,0,0',
        'INVALID,SP,,5102,102,,0,0,0,0',  // NCM com letra
        '22021000,SP,,5102,102,,0,0,0,0',
    );

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toHaveCount(2) // linha boa 1 + linha boa 3
        ->and($r['erros'])->toHaveCount(1)
        ->and($r['erros'][0]['linha'])->toBe(3) // linha 2+1 (header)
        ->and($r['erros'][0]['motivo'])->toContain('NCM');
});

it('parse() rejeita CSOSN e CST preenchidos juntos', function () {
    $csv = csv('49019900,SP,,5102,102,000,0,0,0,0');

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toBe([])
        ->and($r['erros'][0]['motivo'])->toContain('mutuamente exclusivos');
});

it('parse() rejeita ambos vazios (CSOSN e CST)', function () {
    $csv = csv('49019900,SP,,5102,,,0,0,0,0');

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toBe([])
        ->and($r['erros'][0]['motivo'])->toContain('Informe CSOSN');
});

it('parse() rejeita alíquota fora do range [0,1]', function () {
    $csv = csv('49019900,SP,,5102,102,,1.5,0,0,0'); // ICMS 150%

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toBe([])
        ->and($r['erros'][0]['motivo'])->toContain('aliquota_icms');
});

it('parse() rejeita UF inválida', function () {
    $csv = csv('49019900,XX,,5102,102,,0,0,0,0');

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toBe([])
        ->and($r['erros'][0]['motivo'])->toContain('UF origem');
});

it('parse() suporta CRLF + remove BOM UTF-8', function () {
    $bom = "\xEF\xBB\xBF";
    $csv = $bom . csvHeader() . "\r\n49019900,SP,,5102,102,,0,0,0,0\r\n";

    $r = (new ImportRegrasCsvService())->parse($csv);

    expect($r['linhas'])->toHaveCount(1)
        ->and($r['erros'])->toBe([]);
});

// ── aplicar() tests ──────────────────────────────────────────────────────

it('aplicar() cria regras novas (idempotente count=criadas)', function () {
    $csv = csv(
        '49019900,SP,,5102,102,,0,0,0,0',
        '22021000,SP,,5102,102,,0,0,0,0',
    );

    $svc = new ImportRegrasCsvService();
    $r = $svc->parse($csv);

    $resumo = $svc->aplicar(1, $r['linhas']);

    expect($resumo)->toBe(['criadas' => 2, 'atualizadas' => 0, 'falhas' => 0]);
    expect(NfeFiscalRule::where('business_id', 1)->count())->toBe(2);
});

it('aplicar() atualiza existentes pela chave (NCM + UF origem + UF destino)', function () {
    NfeFiscalRule::create([
        'business_id'     => 1,
        'ncm'             => '49019900',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.0,
        'aliquota_pis'    => 0.0,
        'aliquota_cofins' => 0.0,
        'aliquota_ipi'    => 0.0,
    ]);

    $csv = csv('49019900,SP,,5102,102,,0.18,0.0065,0.03,0'); // ICMS 18% novo
    $svc = new ImportRegrasCsvService();
    $r = $svc->parse($csv);

    $resumo = $svc->aplicar(1, $r['linhas']);

    expect($resumo)->toBe(['criadas' => 0, 'atualizadas' => 1, 'falhas' => 0]);
    expect(NfeFiscalRule::where('business_id', 1)->count())->toBe(1);
    expect((float) NfeFiscalRule::where('business_id', 1)->first()->aliquota_icms)->toBe(0.18);
});

it('aplicar() multi-tenant: import biz=1 não afeta biz=99', function () {
    NfeFiscalRule::create([
        'business_id'     => 99,
        'ncm'             => '49019900',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.99, // valor "secreto" do biz 99
        'aliquota_pis'    => 0,
        'aliquota_cofins' => 0,
        'aliquota_ipi'    => 0,
    ]);

    $csv = csv('49019900,SP,,5102,102,,0.18,0,0,0');
    $svc = new ImportRegrasCsvService();
    $resumo = $svc->aplicar(1, $svc->parse($csv)['linhas']);

    expect($resumo)->toBe(['criadas' => 1, 'atualizadas' => 0, 'falhas' => 0]);
    // biz 99 preservado
    expect((float) NfeFiscalRule::where('business_id', 99)->first()->aliquota_icms)->toBe(0.99);
});
