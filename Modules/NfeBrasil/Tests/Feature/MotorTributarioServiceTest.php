<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — motor tributário por NCM: cascade Níveis 1→4 (override→exata→padrão→default), CST/CSOSN, cache, isolamento multi-tenant.
// @covers R-NFE-015 · R-NFE-015b · R-NFE-016 · R-NFE-017 — ICMS-ST pela MVA, FCP e DIFAL (thread 06 do playbook Fiscal).

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

    $t = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);

    // 1000 × 1,40 = 1400,00 · 1400 × 0,20 = 280,00 − ICMS próprio 120,00 = 160,00
    expect($t->valor_icms)->toBe(120.0)
        ->and($t->base_st)->toBe(1400.0)
        ->and($t->valor_st)->toBe(160.0)
        ->and($t->valor_difal)->toBe(0.0);   // contribuinte: sem DIFAL

    // Com IPI 10%: (1000 + 100) × 1,40 = 1540,00 · 1540 × 0,20 = 308,00 − 120,00 = 188,00
    regra(['ncm' => '22029900', 'uf_destino' => 'RJ', 'cfop' => '6401', 'csosn' => '201',
        'aliquota_icms' => 0.12, 'aliquota_ipi' => 0.10, 'mva' => 0.40]);
    $ipi = $motor->calcular(ctx('22029900', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);
    expect($ipi->valor_ipi)->toBe(100.0)
        ->and($ipi->base_st)->toBe(1540.0)
        ->and($ipi->valor_st)->toBe(188.0);
});

it('R-NFE-015 · controle: regra sem MVA, ou sem interna informada, devolve ST 0 e o resto igual a hoje', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6102', 'csosn' => '201', 'aliquota_icms' => 0.12,
        'aliquota_pis' => 0.0165, 'aliquota_cofins' => 0.076, 'mva' => null]);
    $motor = new MotorTributarioService;

    $semMva = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
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

    $retida = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);
    $calcula = $motor->calcular(ctx('22029900', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);

    expect($retida->valor_st)->toBe(0.0)->and($retida->base_st)->toBe(0.0)
        ->and($calcula->valor_st)->toBe(160.0);
});

it('R-NFE-017 · DIFAL só não contribuinte interestadual (controle: com IE, ou mesma UF, = 0)', function () {
    regra(['uf_destino' => 'RJ', 'cfop' => '6108', 'csosn' => '102', 'aliquota_icms' => 0.12]);
    regra(['uf_origem' => 'RJ', 'uf_destino' => 'RJ', 'cfop' => '5102', 'csosn' => '102', 'aliquota_icms' => 0.12]);
    $motor = new MotorTributarioService;

    // 1000 × (0,20 − 0,12) = 80,00
    $consumidor = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: false);
    $comIe = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: true);
    $mesmaUf = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'RJ', ufDestino: 'RJ',
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
    $comFcp = $motor->calcular(ctx('22021000', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: false);
    $semFcp = $motor->calcular(ctx('22029900', 1000.0), businessId: 1, ufOrigem: 'SP', ufDestino: 'RJ',
        aliquotaInternaDestino: 0.20, destinatarioContribuinte: false);

    expect($comFcp->valor_fcp)->toBe(20.0)
        ->and($comFcp->valor_difal)->toBe(80.0)
        ->and($semFcp->valor_fcp)->toBe(0.0);
});
