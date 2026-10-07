<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — motor tributário por NCM: cascade Níveis 1→4 (override→exata→padrão→default), CST/CSOSN, cache, isolamento multi-tenant.
// @covers R-NFE-015 · R-NFE-015b · R-NFE-016 · R-NFE-017 — ICMS-ST pela MVA, FCP e DIFAL (thread 06 do playbook Fiscal).
// @covers R-NFE-018 · R-NFE-019 · R-NFE-020b · R-NFE-020c — operação + vigência (thread 07 do playbook Fiscal).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Exceptions\NcmObrigatorioException;
use Modules\NfeBrasil\Exceptions\TributacaoNaoConfiguradaException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * US-NFE-043 · MotorTributarioService cascade 4 níveis (ADR ARQ-0006).
 *
 * Pattern dual-mode (PR #486 reference):
 *   - SQLite (CI sanity): drop+create isolado em :memory:
 *   - MySQL (Pest local — gate Wagner): preserva schema real;
 *     limpa rows biz=1/99 com FK_CHECKS=0 (cascateia em links)
 *
 * Event::fake do bridge listener `SyncFiscalRuleToTaxRate` (ADR ARQ-0005)
 * pra isolar do side effect no boot do model — listener tem cobertura
 * própria em SyncFiscalRuleToTaxRateTest.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_fiscal_rules');
        Schema::dropIfExists('nfe_business_configs');

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
            // IBS/CBS (US-FISCAL-021) — espelha migration 2026_05_26_000001 (SQLite CI sanity).
            $t->char('c_class_trib', 6)->nullable();
            $t->char('cst_ibs', 3)->nullable();
            $t->char('cst_cbs', 3)->nullable();
            $t->decimal('aliquota_ibs', 7, 4)->default(0);
            $t->decimal('aliquota_cbs', 7, 4)->default(0);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('nfe_business_configs', function ($t) {
            $t->id();
            $t->unsignedInteger('business_id')->unique();
            $t->enum('regime', ['mei', 'simples', 'lucro_presumido', 'lucro_real'])->default('simples');
            $t->json('tributacao_default');
            $t->timestamps();
        });
    } else {
        if (Schema::hasTable('nfe_fiscal_rules')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
                DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', [1, 4, 5, 99, 999])->delete();
            }
            DB::table('nfe_fiscal_rules')->whereIn('business_id', [1, 4, 5, 99, 999])->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        if (Schema::hasTable('nfe_business_configs')) {
            DB::table('nfe_business_configs')->whereIn('business_id', [1, 4, 5, 99, 999])->delete();
        }
    }

    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('nfe_fiscal_rules');
        Schema::dropIfExists('nfe_business_configs');
    } else {
        if (Schema::hasTable('nfe_fiscal_rules')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
                DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', [1, 4, 5, 99, 999])->delete();
            }
            DB::table('nfe_fiscal_rules')->whereIn('business_id', [1, 4, 5, 99, 999])->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        if (Schema::hasTable('nfe_business_configs')) {
            DB::table('nfe_business_configs')->whereIn('business_id', [1, 4, 5, 99, 999])->delete();
        }
    }
});

// ── helpers ────────────────────────────────────────────────────────────────

function ctx(?string $ncm = '22021000', float $valor = 100.0, ?int $overrideId = null): ProdutoFiscalContext
{
    return new ProdutoFiscalContext(
        ncm:                     $ncm,
        valor:                   $valor,
        fiscal_rule_override_id: $overrideId,
    );
}

function regra(array $props): NfeFiscalRule
{
    return NfeFiscalRule::create(array_merge([
        'business_id'     => 1,
        'ncm'             => '22021000',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.0,
        'aliquota_pis'    => 0.0,
        'aliquota_cofins' => 0.0,
        'aliquota_ipi'    => 0.0,
    ], $props));
}

function configBusiness(int $bizId, array $defaults = []): NfeBusinessConfig
{
    return NfeBusinessConfig::create([
        'business_id'        => $bizId,
        'regime'             => 'simples',
        'tributacao_default' => array_merge([
            'cfop'             => '5102',
            'csosn'            => '102',
            'aliquota_icms'    => 0.0,
            'aliquota_pis'     => 0.0,
            'aliquota_cofins'  => 0.0,
        ], $defaults),
    ]);
}

// ── testes do cascade ─────────────────────────────────────────────────────

it('Nível 1: override por produto vence sobre tudo', function () {
    // Regra exata existe (Nível 2), regra NCM existe (Nível 3), config existe (Nível 4),
    // override aponta pra regra DIFERENTE — deve usar override
    regra(['uf_destino' => 'RJ', 'cfop' => '6101', 'aliquota_icms' => 0.18]); // Nível 2
    regra(['uf_destino' => null, 'cfop' => '5102', 'aliquota_icms' => 0.10]); // Nível 3
    configBusiness(4, ['aliquota_icms' => 0.20]); // Nível 4

    $override = regra([
        'ncm'              => '99999999', // NCM diferente — só importa o ID
        'uf_origem'        => 'AC',
        'cfop'             => '7777',
        'aliquota_icms'    => 0.99,
    ]);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0, overrideId: $override->id),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
    );

    expect($tributo->nivel_usado)->toBe(1)
        ->and($tributo->cfop)->toBe('7777')
        ->and($tributo->aliquota_icms)->toBe(0.99)
        ->and($tributo->valor_icms)->toBe(99.0)
        ->and($tributo->regra_id)->toBe($override->id);
});

it('Nível 2: regra exata (uf_destino especifico) vence sobre Nível 3', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6101', 'aliquota_icms' => 0.18]); // Nível 2
    regra(['uf_destino' => null, 'cfop' => '5102', 'aliquota_icms' => 0.10]); // Nível 3
    configBusiness(4);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
    );

    expect($tributo->nivel_usado)->toBe(2)
        ->and($tributo->cfop)->toBe('6101')
        ->and($tributo->aliquota_icms)->toBe(0.18)
        ->and($tributo->valor_icms)->toBe(18.0);
});

it('Nível 3: regra padrão NCM aplica quando não há regra exata', function () {
    regra(['uf_destino' => null, 'cfop' => '5102', 'aliquota_icms' => 0.07]); // Nível 3 — só
    configBusiness(4);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 250.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'BA',
    );

    expect($tributo->nivel_usado)->toBe(3)
        ->and($tributo->cfop)->toBe('5102')
        ->and($tributo->aliquota_icms)->toBe(0.07)
        ->and($tributo->valor_icms)->toBe(17.5);
});

it('Nível 4: defaults business aplicam quando NCM não tem regra', function () {
    // configBusiness deve receber o MESMO businessId usado no calcular() abaixo,
    // senão lança TributacaoNaoConfiguradaException (Business 1 sem default).
    configBusiness(1, [
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.0,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
    ]);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('99999999', 1000.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->nivel_usado)->toBe(4)
        ->and($tributo->cfop)->toBe('5102')
        ->and($tributo->csosn)->toBe('102')
        ->and($tributo->aliquota_icms)->toBe(0.0)
        ->and($tributo->valor_pis)->toBe(6.5)      // 1000 × 0.0065 PIS
        ->and($tributo->valor_cofins)->toBe(30.0)  // 1000 × 0.03 COFINS
        ->and($tributo->regra_id)->toBeNull();
});

// ── edge cases ─────────────────────────────────────────────────────────────

it('NcmObrigatorioException quando produto sem NCM e sem override', function () {
    configBusiness(4);

    expect(fn () => (new MotorTributarioService)->calcular(
        ctx(null, 100.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    ))->toThrow(NcmObrigatorioException::class, 'sem NCM cadastrado');
});

it('TributacaoNaoConfiguradaException quando business sem default e NCM sem regra', function () {
    // Sem configBusiness — Nível 4 falha
    expect(fn () => (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    ))->toThrow(TributacaoNaoConfiguradaException::class, 'sem default tributário');
});

it('Multi-tenant: regra do business 4 não vaza pro business 5', function () {
    regra(['business_id' => 1, 'aliquota_icms' => 0.18]);
    configBusiness(5, ['aliquota_icms' => 0.05]); // Business 5 só tem default

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0),
        businessId: 5, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->nivel_usado)->toBe(4)  // Não pegou regra do business 4
        ->and($tributo->aliquota_icms)->toBe(0.05);
});

it('Override invalido (id que nao existe) cai no cascade normal', function () {
    regra(['uf_destino' => null, 'aliquota_icms' => 0.10]); // Nível 3
    configBusiness(4);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0, overrideId: 99999), // não existe
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->nivel_usado)->toBe(3)
        ->and($tributo->aliquota_icms)->toBe(0.10);
});

it('Override de outro business é ignorado (multi-tenant)', function () {
    $overrideOutroBiz = regra(['business_id' => 999, 'aliquota_icms' => 0.99]);
    regra(['business_id' => 1, 'uf_destino' => null, 'aliquota_icms' => 0.10]);
    configBusiness(4);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0, overrideId: $overrideOutroBiz->id),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->nivel_usado)->toBe(3) // Não pegou override do biz 999
        ->and($tributo->aliquota_icms)->toBe(0.10);
});

it('Cache em memoria: mesma chave é consultada 1x por instância', function () {
    regra(['uf_destino' => null, 'aliquota_icms' => 0.10]);
    configBusiness(4);
    $svc = new MotorTributarioService;

    // Primeira chamada — query
    $t1 = $svc->calcular(ctx('22021000', 100.0), 1, 'SP', 'SP');
    // Segunda — deve vir do cache (mesma chave)
    $t2 = $svc->calcular(ctx('22021000', 200.0), 1, 'SP', 'SP');

    expect($t1->aliquota_icms)->toBe(0.10)
        ->and($t2->aliquota_icms)->toBe(0.10)
        ->and($t1->valor_icms)->toBe(10.0)
        ->and($t2->valor_icms)->toBe(20.0); // valor diferente, mas alíquota cacheada
});

it('CST aplicado quando regra é Regime Normal (sem CSOSN)', function () {
    regra([
        'uf_destino' => null,
        'csosn' => null,
        'cst' => '000',
        'aliquota_icms' => 0.18,
    ]);
    configBusiness(4);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->cst)->toBe('000')
        ->and($tributo->csosn)->toBeNull();
});

// ── IBS/CBS (Reforma Tributária NT 2025.002 · US-FISCAL-021 / ADR 0321) ────────
// Motor calcula os campos IBS/CBS do TributoCalculado a partir das colunas de
// nfe_fiscal_rules / tributacao_default. Fallback Simples/legado = null/0 (inerte).
// A serialização XML (grupo UB) é gated por flag (PR-D) — aqui só o cálculo.

it('IBS/CBS fallback Simples: regra sem IBS/CBS → valor 0 e cClassTrib null', function () {
    // Caso biz=1/biz=4 hoje: regra tradicional, sem colunas IBS/CBS preenchidas.
    regra(['uf_destino' => null, 'cfop' => '5102', 'csosn' => '102', 'aliquota_icms' => 0.0]);
    configBusiness(4);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 1000.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->c_class_trib)->toBeNull()
        ->and($tributo->cst_ibs)->toBeNull()
        ->and($tributo->cst_cbs)->toBeNull()
        ->and($tributo->aliquota_ibs)->toBe(0.0)
        ->and($tributo->aliquota_cbs)->toBe(0.0)
        ->and($tributo->valor_ibs)->toBe(0.0)
        ->and($tributo->valor_cbs)->toBe(0.0);
});

it('IBS/CBS Nível 2 (regra exata) Regime Normal: calcula valores com cross-check numérico', function () {
    // Fase-teste 2026: IBS 0,1% (0.001) + CBS 0,9% (0.009). Base 1.000.
    // Dupla confirmação (regra-mestre valor): 1000*0.001=1.00 IBS ; 1000*0.009=9.00 CBS.
    regra([
        'uf_destino'   => 'RJ',
        'cfop'         => '6101',
        'csosn'        => null,
        'cst'          => '000',
        'c_class_trib' => '000001',
        'cst_ibs'      => '000',
        'cst_cbs'      => '000',
        'aliquota_ibs' => 0.001,
        'aliquota_cbs' => 0.009,
    ]);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 1000.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
    );

    expect($tributo->nivel_usado)->toBe(2)
        ->and($tributo->c_class_trib)->toBe('000001')
        ->and($tributo->cst_ibs)->toBe('000')
        ->and($tributo->cst_cbs)->toBe('000')
        ->and($tributo->aliquota_ibs)->toBe(0.001)
        ->and($tributo->aliquota_cbs)->toBe(0.009)
        ->and($tributo->valor_ibs)->toBe(1.0)   // 1000 * 0.001
        ->and($tributo->valor_cbs)->toBe(9.0);  // 1000 * 0.009
});

it('IBS/CBS Nível 3 (regra NCM padrão) carrega os campos', function () {
    regra([
        'uf_destino'   => null,
        'cfop'         => '5102',
        'cst'          => '000',
        'c_class_trib' => '000002',
        'aliquota_ibs' => 0.001,
        'aliquota_cbs' => 0.009,
    ]);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 500.0),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'MG',
    );

    expect($tributo->nivel_usado)->toBe(3)
        ->and($tributo->c_class_trib)->toBe('000002')
        ->and($tributo->valor_ibs)->toBe(0.5)   // 500 * 0.001
        ->and($tributo->valor_cbs)->toBe(4.5);  // 500 * 0.009
});

it('IBS/CBS Nível 4 (business default) extrai do tributacao_default JSON', function () {
    // Sem regra → cai no default do business. IBS/CBS vêm do JSON tributacao_default.
    configBusiness(1, [
        'c_class_trib' => '000003',
        'cst_ibs'      => '000',
        'cst_cbs'      => '000',
        'aliquota_ibs' => 0.001,
        'aliquota_cbs' => 0.009,
    ]);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('88888888', 2000.0), // NCM sem regra → Nível 4
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->nivel_usado)->toBe(4)
        ->and($tributo->c_class_trib)->toBe('000003')
        ->and($tributo->aliquota_ibs)->toBe(0.001)
        ->and($tributo->valor_ibs)->toBe(2.0)    // 2000 * 0.001
        ->and($tributo->valor_cbs)->toBe(18.0);  // 2000 * 0.009
});

it('IBS/CBS Nível 1 (override) carrega os campos da regra override', function () {
    regra(['uf_destino' => null, 'cfop' => '5102', 'aliquota_ibs' => 0.0]); // Nível 3 sem IBS
    $override = regra([
        'ncm'          => '99999999',
        'uf_origem'    => 'AC',
        'cfop'         => '7777',
        'c_class_trib' => '000009',
        'aliquota_ibs' => 0.001,
        'aliquota_cbs' => 0.009,
    ]);

    $tributo = (new MotorTributarioService)->calcular(
        ctx('22021000', 100.0, overrideId: $override->id),
        businessId: 1, ufOrigem: 'SP', ufDestino: 'SP',
    );

    expect($tributo->nivel_usado)->toBe(1)
        ->and($tributo->c_class_trib)->toBe('000009')
        ->and($tributo->valor_ibs)->toBe(0.1)   // 100 * 0.001
        ->and($tributo->valor_cbs)->toBe(0.9);  // 100 * 0.009
});

// ── ICMS-ST · FCP · DIFAL (thread 06 do playbook Fiscal) ──────────────────────
// Alíquotas de teste: interestadual SP→RJ 12% (regra) e interna RJ 20% — números de
// fixture, não de lei; o motor recebe a interna do caller (tabela por UF = thread 09).
// Valores esperados calculados à mão em cada caso.

it('R-NFE-015 · ICMS-ST pela MVA (base_st = (valor+IPI)×(1+MVA); st = base_st×interna − ICMS próprio)', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6401', 'csosn' => '201', 'aliquota_icms' => 0.12, 'mva' => 0.40]);
    $motor = new MotorTributarioService;

    $t = $motor->calcularComDestino(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);

    // 1000 × 1,40 = 1400,00 · 1400 × 0,20 = 280,00 − ICMS próprio 120,00 = 160,00
    expect($t->valor_icms)->toBe(120.0)
        ->and($t->base_st)->toBe(1400.0)
        ->and($t->valor_st)->toBe(160.0)
        ->and($t->valor_difal)->toBe(0.0);   // contribuinte: sem DIFAL

    // Com IPI 10%: (1000 + 100) × 1,40 = 1540,00 · 1540 × 0,20 = 308,00 − 120,00 = 188,00
    regra(['ncm' => '22029900', 'uf_destino' => 'RJ', 'cfop' => '6401', 'csosn' => '201',
        'aliquota_icms' => 0.12, 'aliquota_ipi' => 0.10, 'mva' => 0.40]);
    $ipi = $motor->calcularComDestino(ctx('22029900', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);
    expect($ipi->valor_ipi)->toBe(100.0)
        ->and($ipi->base_st)->toBe(1540.0)
        ->and($ipi->valor_st)->toBe(188.0);
});

it('R-NFE-015 · controle: regra sem MVA, ou sem interna informada, devolve ST 0 e o resto igual a hoje', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6102', 'csosn' => '201', 'aliquota_icms' => 0.12,
        'aliquota_pis' => 0.0165, 'aliquota_cofins' => 0.076, 'mva' => null]);
    $motor = new MotorTributarioService;

    $semMva = $motor->calcularComDestino(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);
    $chamadaAntiga = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ');

    expect($semMva->base_st)->toBe(0.0)->and($semMva->valor_st)->toBe(0.0)
        ->and($semMva->valor_icms)->toBe(120.0)
        ->and($semMva->valor_pis)->toBe(16.5)
        ->and($semMva->valor_cofins)->toBe(76.0)
        ->and($chamadaAntiga)->toEqual($semMva);   // chamada sem os parâmetros novos = idêntica

    // MVA preenchida mas a interna não veio: não inventa — ST 0.
    regra(['ncm' => '22029900', 'uf_destino' => 'RJ', 'cfop' => '6401', 'csosn' => '201',
        'aliquota_icms' => 0.12, 'mva' => 0.40]);
    $semInterna = $motor->calcular(ctx('22029900', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ');
    expect($semInterna->valor_st)->toBe(0.0)->and($semInterna->valor_icms)->toBe(120.0);
});

it('R-NFE-016 · CSOSN 500 não recalcula ST (controle: CSOSN 201 calcula)', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6405', 'csosn' => '500', 'aliquota_icms' => 0.12, 'mva' => 0.40]);
    regra(['ncm' => '22029900', 'uf_destino' => 'RJ', 'cfop' => '6401', 'csosn' => '201', 'aliquota_icms' => 0.12, 'mva' => 0.40]);
    $motor = new MotorTributarioService;

    $retida = $motor->calcularComDestino(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);
    $calcula = $motor->calcularComDestino(ctx('22029900', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);

    expect($retida->valor_st)->toBe(0.0)->and($retida->base_st)->toBe(0.0)
        ->and($calcula->valor_st)->toBe(160.0);
});

it('R-NFE-017 · DIFAL só não contribuinte interestadual (controle: com IE, ou mesma UF, = 0)', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6108', 'csosn' => '102', 'aliquota_icms' => 0.12]);
    regra(['uf_origem' => 'RJ', 'uf_destino' => 'RJ', 'cfop' => '5102', 'csosn' => '102', 'aliquota_icms' => 0.12]);
    $motor = new MotorTributarioService;

    // 1000 × (0,20 − 0,12) = 80,00
    $consumidor = $motor->calcularComDestino(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: false);
    $comIe = $motor->calcularComDestino(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);
    $mesmaUf = $motor->calcularComDestino(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'RJ', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: false);

    expect($consumidor->valor_difal)->toBe(80.0)
        ->and($comIe->valor_difal)->toBe(0.0)
        ->and($mesmaUf->valor_difal)->toBe(0.0);
});

it('R-NFE-015b · FCP pela regra (controle: fcp nulo = 0)', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6108', 'csosn' => '102', 'aliquota_icms' => 0.12, 'fcp' => 0.02]);
    regra(['ncm' => '22029900', 'uf_destino' => 'RJ', 'cfop' => '6108', 'csosn' => '102', 'aliquota_icms' => 0.12, 'fcp' => null]);
    $motor = new MotorTributarioService;

    // 1000 × 0,02 = 20,00 — em DIFAL é o FCP da UF de destino, ao lado dos 80,00 de DIFAL
    $comFcp = $motor->calcularComDestino(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: false);
    $semFcp = $motor->calcularComDestino(ctx('22029900', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: false);

    expect($comFcp->valor_fcp)->toBe(20.0)
        ->and($comFcp->valor_difal)->toBe(80.0)
        ->and($semFcp->valor_fcp)->toBe(0.0);
});


// ── thread 07 do playbook Fiscal: operação + vigência (R-NFE-018 · 019 · 020b · 020c) ──────────
// MySQL-only (as colunas novas vêm da migração 2026_10_07_000001; o schema SQLite deste arquivo é
// montado à mão e segue sem elas — ali o motor faz o de antes, e os casos acima provam isso).
// Tenant fictício 98 (ADR 0358). Os valores esperados derivam do aceite do playbook, com conta à mão
// no comentário de cada caso (regra mestre de valor: dois caminhos — a conta e o motor).

function t07Biz(): int
{
    return test()->seededTenant()->id;
}

/** NCMs reservados a estes casos — nada de produção usa a faixa 7707xxxx. */
const T07_NCMS = ['77070001', '77070002', '77070003', '77070004'];

function t07Limpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        $ids = DB::table('nfe_fiscal_rules')->where('business_id', t07Biz())->whereIn('ncm', T07_NCMS)->pluck('id');
        DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('fiscal_rule_id', $ids)->delete();
    }
    DB::table('nfe_fiscal_rules')->where('business_id', t07Biz())->whereIn('ncm', T07_NCMS)->delete();
    DB::table('nfe_operacoes_fiscais')->where('business_id', t07Biz())->where('slug', 'like', 't07-%')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function t07Preparar(): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('nfe_fiscal_rules', 'valida_de')) {
        test()->markTestSkipped('MySQL-only: precisa da migração 2026_10_07_000001 (operação + vigência).');
    }
    NfeFiscalRule::esquecerVersionamento();
    t07Limpar();
}

function t07Regra(array $props): NfeFiscalRule
{
    return NfeFiscalRule::create(array_merge([
        'business_id'     => t07Biz(),
        'ncm'             => '77070001',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.0,
        'aliquota_pis'    => 0.0,
        'aliquota_cofins' => 0.0,
        'aliquota_ipi'    => 0.0,
    ], $props));
}

function t07Operacao(string $slug, array $props = []): int
{
    return (int) DB::table('nfe_operacoes_fiscais')->insertGetId(array_merge([
        'business_id' => t07Biz(),
        'slug'        => $slug,
        'nome'        => $slug,
        'finalidade'  => 1,
        'cfop'        => null,
        'regra_geral' => null,
        'padrao'      => false,
        'created_at'  => now(),
        'updated_at'  => now(),
    ], $props));
}

it('R-NFE-018 · versão vigente na data', function () {
    t07Preparar();

    // Versão A até 31/12/2026 com ICMS 12%; versão B a partir de 01/01/2027 com ICMS 18%.
    t07Regra(['aliquota_icms' => 0.12, 'valida_ate' => '2026-12-31']);
    t07Regra(['aliquota_icms' => 0.18, 'valida_de' => '2027-01-01']);

    $motor = new MotorTributarioService();
    $antes  = $motor->calcularComDestino(ctx('77070001', 1000.0), t07Biz(), 'SP', 'SP', dataEmissao: '2026-12-31');
    $depois = $motor->calcularComDestino(ctx('77070001', 1000.0), t07Biz(), 'SP', 'SP', dataEmissao: '2027-01-01');

    // Conta à mão: 1.000 × 0,12 = 120,00 · 1.000 × 0,18 = 180,00.
    expect($antes->aliquota_icms)->toBe(0.12)
        ->and($antes->valor_icms)->toBe(120.0)
        ->and($depois->aliquota_icms)->toBe(0.18)
        ->and($depois->valor_icms)->toBe(180.0);

    // CONTROLE POSITIVO — regra sem vigência cadastrada vale em qualquer data, como antes.
    t07Regra(['ncm' => '77070002', 'aliquota_icms' => 0.07]);
    expect($motor->calcularComDestino(ctx('77070002', 1000.0), t07Biz(), 'SP', 'SP', dataEmissao: '2020-01-01')->valor_icms)
        ->toBe(70.0)
        ->and($motor->calcular(ctx('77070002', 1000.0), t07Biz(), 'SP', 'SP')->valor_icms)->toBe(70.0);

    t07Limpar();
});

it('R-NFE-019 · editar gera versão, append-only', function () {
    t07Preparar();

    $antiga = t07Regra(['aliquota_icms' => 0.12]);
    $fotoAntes = (array) DB::table('nfe_fiscal_rules')->where('id', $antiga->id)->first();

    $nova = $antiga->fresh()->novaVersao(['aliquota_icms' => 0.18], '2026-10-07');

    $fotoDepois = (array) DB::table('nfe_fiscal_rules')->where('id', $antiga->id)->first();

    // A antiga só ganhou valida_ate = ontem; nenhuma outra coluna mudou.
    expect($fotoDepois['valida_ate'])->toBe('2026-10-06');
    $semVigencia = fn (array $l) => array_diff_key($l, ['valida_ate' => 1, 'updated_at' => 1]);
    expect($semVigencia($fotoDepois))->toBe($semVigencia($fotoAntes));

    expect($nova->id)->not->toBe($antiga->id)
        ->and($nova->valida_de->format('Y-m-d'))->toBe('2026-10-07')
        ->and((int) $nova->versao_origem_id)->toBe($antiga->id);

    // A nota antiga, recalculada com a data dela, dá o mesmo valor; a de hoje usa a versão nova.
    // Conta: 1.000 × 0,12 = 120,00 · 1.000 × 0,18 = 180,00.
    $motor = new MotorTributarioService();
    expect($motor->calcularComDestino(ctx('77070001', 1000.0), t07Biz(), 'SP', 'SP', dataEmissao: '2026-10-06')->valor_icms)
        ->toBe(120.0)
        ->and($motor->calcularComDestino(ctx('77070001', 1000.0), t07Biz(), 'SP', 'SP', dataEmissao: '2026-10-07')->valor_icms)
        ->toBe(180.0);

    // O override por produto apontava o id antigo e segue a cadeia até a versão vigente.
    $porOverride = (new MotorTributarioService())
        ->calcularComDestino(ctx('77070001', 1000.0, $antiga->id), t07Biz(), 'SP', 'SP', dataEmissao: '2026-10-07');
    expect($porOverride->nivel_usado)->toBe(1)
        ->and($porOverride->regra_id)->toBe($nova->id)
        ->and($porOverride->valor_icms)->toBe(180.0);

    t07Limpar();
});

it('R-NFE-020b · exceção restrita à operação', function () {
    t07Preparar();

    // Exceção de NCM da operação padrão (Venda): regra sem operacao_id, ICMS 12%.
    t07Regra(['aliquota_icms' => 0.12]);
    $devolucao = t07Operacao('t07-devolucao', [
        'finalidade'  => 4,
        'regra_geral' => json_encode(['cfop' => '1202', 'csosn' => '900', 'aliquota_icms' => 0.0]),
    ]);

    $motor = new MotorTributarioService();
    $dev = $motor->calcularComDestino(ctx('77070001', 1000.0), t07Biz(), 'SP', 'SP', operacaoId: $devolucao);

    // Devolução: a exceção de venda NÃO entra — cai na regra geral da devolução (Nível 4).
    expect($dev->nivel_usado)->toBe(4)
        ->and($dev->cfop)->toBe('1202')
        ->and($dev->csosn)->toBe('900')
        ->and($dev->valor_icms)->toBe(0.0);

    // CONTROLE POSITIVO — na venda (padrão), a exceção é usada: 1.000 × 0,12 = 120,00.
    $venda = $motor->calcular(ctx('77070001', 1000.0), t07Biz(), 'SP', 'SP');
    expect($venda->nivel_usado)->toBe(3)
        ->and($venda->valor_icms)->toBe(120.0);

    // Operação sem regra geral não cai no default de venda: é erro de configuração.
    $semGeral = t07Operacao('t07-sem-geral');
    expect(fn () => (new MotorTributarioService())
        ->calcularComDestino(ctx('77070003', 1000.0), t07Biz(), 'SP', 'SP', operacaoId: $semGeral))
        ->toThrow(TributacaoNaoConfiguradaException::class);

    t07Limpar();
});

it('R-NFE-020c · CFOP ? por destino', function () {
    t07Preparar();

    $geral = json_encode(['cfop' => '5102', 'csosn' => '102', 'aliquota_icms' => 0.0]);
    $op = t07Operacao('t07-venda-uf', ['cfop' => '?102', 'regra_geral' => $geral]);

    $motor = new MotorTributarioService();
    $cfop = fn (string $ufDestino) => $motor
        ->calcularComDestino(ctx('77070004', 100.0), t07Biz(), 'SP', $ufDestino, operacaoId: $op)->cfop;

    expect($cfop('SP'))->toBe('5102')
        ->and($cfop('RJ'))->toBe('6102')
        ->and($cfop('EX'))->toBe('7102');

    // CONTROLE POSITIVO — CFOP sem "?" não é alterado.
    $importacao = t07Operacao('t07-importacao', ['cfop' => '3102', 'regra_geral' => $geral]);
    expect($motor->calcularComDestino(ctx('77070004', 100.0), t07Biz(), 'SP', 'RJ', operacaoId: $importacao)->cfop)
        ->toBe('3102');

    t07Limpar();
});
