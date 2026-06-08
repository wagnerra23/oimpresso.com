<?php

declare(strict_types=1);

use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;
use Modules\NfeBrasil\Exceptions\NcmObrigatorioException;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-020 / GAP-FISCAL-003 — Integração SpedIcmsIpiGeneratorService ↔
 * MotorTributarioService (Onda CONSOLIDAR 2026-05-25).
 *
 * Tests focados em (1) refactor eliminou 6 hardcodes Tier-0 espalhados no
 * source, (2) MotorTributarioService DI funciona quando configurado,
 * (3) fallback safe Simples Nacional quando motor lança exception conhecida,
 * (4) CFOP correto interno vs interestadual (audit caso R1 multa fiscal).
 *
 * Estes tests rodam em SQLite (zero hit DB — reflection + structural asserts).
 */

it('refactor elimina hardcodes ESPALHADOS — apenas constantes private FALLBACK_*', function () {
    $src = file_get_contents(
        (new ReflectionClass(SpedIcmsIpiGeneratorService::class))->getFileName(),
    );

    // Hardcodes Tier-0 (audit sênior §"Surpresa estratégica") devem aparecer
    // APENAS em constantes private FALLBACK_* (centralizadas) — não espalhados
    // como literais em vários métodos.
    //
    // Espera-se 1 ocorrência cada (definição da constante).
    // Pré-refactor: 3-5 ocorrências cada (espalhadas).
    expect(substr_count($src, "'102'"))->toBeLessThanOrEqual(2, "CST '102' não deve estar espalhado — só na constante FALLBACK_CST")
        ->and(substr_count($src, "'5102'"))->toBeLessThanOrEqual(2, "CFOP '5102' não deve estar espalhado — só na constante FALLBACK_CFOP")
        ->and(substr_count($src, "'00000000'"))->toBeLessThanOrEqual(2, "NCM '00000000' não deve estar espalhado — só na constante FALLBACK_NCM");
});

it('refactor define constantes FALLBACK_* centralizadas (audit sênior GAP-3)', function () {
    $ref = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    $constants = $ref->getConstants();

    expect($constants)->toHaveKey('FALLBACK_NCM_SEM_CADASTRO')
        ->and($constants)->toHaveKey('FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO')
        ->and($constants)->toHaveKey('FALLBACK_CFOP_VENDA_INTERNA_SIMPLES')
        ->and($constants)->toHaveKey('FALLBACK_CFOP_VENDA_INTERESTADUAL_SIMPLES')
        ->and($constants)->toHaveKey('FALLBACK_ALIQ_ICMS_SIMPLES')
        ->and($constants['FALLBACK_NCM_SEM_CADASTRO'])->toBe('00000000')
        ->and($constants['FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO'])->toBe('102')
        ->and($constants['FALLBACK_CFOP_VENDA_INTERNA_SIMPLES'])->toBe('5102')
        ->and($constants['FALLBACK_CFOP_VENDA_INTERESTADUAL_SIMPLES'])->toBe('6102')
        ->and($constants['FALLBACK_ALIQ_ICMS_SIMPLES'])->toBe(0.0);
});

it('constructor aceita MotorTributarioService DI opcional (back-compat)', function () {
    $ref = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    expect(count($params))->toBe(1, 'constructor deve aceitar exatamente 1 parâmetro (motor)')
        ->and($params[0]->getName())->toBe('motor')
        ->and($params[0]->allowsNull())->toBeTrue('motor deve ser nullable pra back-compat sem DI');

    // Verifica que o tipo é MotorTributarioService
    $type = $params[0]->getType();
    expect($type)->not->toBeNull();
    expect((string) $type)->toContain('MotorTributarioService');
});

it('instanciação sem motor (legado) ainda funciona — usa fallback Simples Nacional', function () {
    $service = new SpedIcmsIpiGeneratorService;
    expect($service)->toBeInstanceOf(SpedIcmsIpiGeneratorService::class);
});

it('container resolve service e MotorTributarioService é injetável', function () {
    // Container Laravel passa null pra parâmetros nullable com default null.
    // Pra forçar DI do MotorTributarioService, callsite registra binding ou
    // instancia explicitamente — esta wave foca refactor de hardcodes; binding
    // automático é audit GAP-7 (Strategy Pattern por regime).
    $service = app(SpedIcmsIpiGeneratorService::class);
    expect($service)->toBeInstanceOf(SpedIcmsIpiGeneratorService::class);

    // Verifica que motor PODE ser passado explicitamente
    $motor = app(MotorTributarioService::class);
    $serviceComMotor = new SpedIcmsIpiGeneratorService($motor);

    $reflProp = new ReflectionProperty($serviceComMotor, 'motor');
    $reflProp->setAccessible(true);
    expect($reflProp->getValue($serviceComMotor))->toBeInstanceOf(MotorTributarioService::class);
});

it('fallback Simples Nacional retorna CFOP 5102 (interno) quando UF origem = UF destino', function () {
    $service = new SpedIcmsIpiGeneratorService;

    $reflMethod = new ReflectionMethod($service, 'fallbackSimplesNacional');
    $reflMethod->setAccessible(true);

    $result = $reflMethod->invoke($service, 'SP', 'SP', null);

    expect($result['cfop'])->toBe('5102', 'CFOP interno Simples Nacional')
        ->and($result['cst'])->toBe('102')
        ->and($result['aliq_icms'])->toBe(0.0)
        ->and($result['vl_icms'])->toBe(0.0)
        ->and($result['ncm'])->toBe('00000000');
});

it('fallback Simples Nacional retorna CFOP 6102 (interestadual) quando UF origem ≠ UF destino (audit R1)', function () {
    // Caso real audit sênior 2026-05-25: Larissa biz=4 vestuário SC vendendo
    // pra RS (CFOP 6102). Pré-refactor o hardcode '5102' gerava SPED inválido
    // e disparava multa fiscal (R1 risk register).
    $service = new SpedIcmsIpiGeneratorService;

    $reflMethod = new ReflectionMethod($service, 'fallbackSimplesNacional');
    $reflMethod->setAccessible(true);

    $result = $reflMethod->invoke($service, 'SC', 'RS', '61091000');

    expect($result['cfop'])->toBe('6102', 'CFOP interestadual Simples Nacional — eliminado hardcode 5102')
        ->and($result['cst'])->toBe('102')
        ->and($result['ncm'])->toBe('61091000', 'NCM real preservado quando informado (não fallback 00000000)');
});

it('resolverTributoItem com motor configurado retornando CST 00 + CFOP 6102 + aliq 18% (Lucro Presumido)', function () {
    // Mock motor que retorna Lucro Presumido CST 00 (tributada integral) CFOP 6102 ICMS 18%
    $motorMock = new class extends MotorTributarioService
    {
        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            return new TributoCalculado(
                cfop: '6102',
                csosn: null,
                cst: '00',
                aliquota_icms: 0.18,
                aliquota_pis: 0.0165,
                aliquota_cofins: 0.076,
                aliquota_ipi: 0.0,
                valor_icms: $produto->valor * 0.18,
                valor_pis: 0.0,
                valor_cofins: 0.0,
                valor_ipi: 0.0,
                nivel_usado: 2,
            );
        }
    };

    $service = new SpedIcmsIpiGeneratorService($motorMock);

    // Cria stub NfeEmissao com metadata NCM válido
    $emissao = new \Modules\NfeBrasil\Models\NfeEmissao;
    $emissao->id = 1;
    $emissao->business_id = 1;
    $emissao->valor_total = 1000.00;
    $emissao->metadata = ['ncm' => '61091000', 'dest_uf' => 'RS'];

    $reflMethod = new ReflectionMethod($service, 'resolverTributoItem');
    $reflMethod->setAccessible(true);

    $tributo = $reflMethod->invoke($service, $emissao, 'SC', 'RS');

    expect($tributo['cst'])->toBe('00', 'CST do motor (Lucro Presumido tributada integral)')
        ->and($tributo['cfop'])->toBe('6102', 'CFOP interestadual')
        ->and($tributo['aliq_icms'])->toBe(0.18, 'alíquota ICMS 18%')
        ->and($tributo['vl_icms'])->toBe(180.0, 'valor ICMS = R$ [redacted Tier 0] × 18%')
        ->and($tributo['ncm'])->toBe('61091000');
});

it('resolverTributoItem fallback quando motor lança NcmObrigatorioException', function () {
    $motorMock = new class extends MotorTributarioService
    {
        public function calcular(ProdutoFiscalContext $produto, int $businessId, string $ufOrigem, string $ufDestino): TributoCalculado
        {
            throw new NcmObrigatorioException('Produto sem NCM');
        }
    };

    $service = new SpedIcmsIpiGeneratorService($motorMock);

    $emissao = new \Modules\NfeBrasil\Models\NfeEmissao;
    $emissao->id = 1;
    $emissao->business_id = 1;
    $emissao->valor_total = 500.00;
    $emissao->metadata = ['ncm' => '61091000'];

    $reflMethod = new ReflectionMethod($service, 'resolverTributoItem');
    $reflMethod->setAccessible(true);

    $tributo = $reflMethod->invoke($service, $emissao, 'SP', 'SP');

    expect($tributo['cst'])->toBe('102', 'fallback Simples Nacional quando motor falha')
        ->and($tributo['cfop'])->toBe('5102', 'fallback CFOP interno');
});

it('keyTotalizadorC190 não retorna mais hardcode "102" — chave composta CST|CFOP|ALIQ', function () {
    $service = new SpedIcmsIpiGeneratorService;
    $emissao = new \Modules\NfeBrasil\Models\NfeEmissao;
    $emissao->id = 1;
    $emissao->business_id = 1;

    $tributo = [
        'cst'       => '00',
        'cfop'      => '6102',
        'aliq_icms' => 0.18,
        'vl_icms'   => 180.0,
        'ncm'       => '61091000',
    ];

    $reflMethod = new ReflectionMethod($service, 'keyTotalizadorC190');
    $reflMethod->setAccessible(true);

    $key = $reflMethod->invoke($service, $emissao, $tributo);

    expect($key)->not->toBe('102', 'key totalizador NÃO pode ser hardcode "102" — refactor GAP-3')
        ->and(str_contains($key, '00'))->toBeTrue('contém CST')
        ->and(str_contains($key, '6102'))->toBeTrue('contém CFOP')
        ->and(str_contains($key, '|'))->toBeTrue('chave composta com separator');
});
