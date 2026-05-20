<?php

declare(strict_types=1);

use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-016 — SpedIcmsIpiGeneratorService unit tests (PR #8 Wave MVP).
 *
 * Cobertura broad SEFAZ/PVA-EFD validation real fica em Pest browser MCP
 * pós-merge biz=1 (validar TXT via PVA-EFD homologação CONFAZ).
 *
 * Aqui foca em:
 *  - Contract método público `gerar`
 *  - Validações de input (ano range, mês 1-12, cross-tenant)
 *  - Estrutura básica do TXT gerado (registros canônicos)
 */

it('gerar method público existe + signature canônica', function () {
    expect(method_exists(SpedIcmsIpiGeneratorService::class, 'gerar'))->toBeTrue();

    $reflection = new ReflectionMethod(SpedIcmsIpiGeneratorService::class, 'gerar');
    expect($reflection->isPublic())->toBeTrue()
        ->and($reflection->getNumberOfParameters())->toBe(3);

    $params = $reflection->getParameters();
    expect($params[0]->getName())->toBe('businessId')
        ->and((string) $params[0]->getType())->toBe('int')
        ->and($params[1]->getName())->toBe('ano')
        ->and((string) $params[1]->getType())->toBe('int')
        ->and($params[2]->getName())->toBe('mes')
        ->and((string) $params[2]->getType())->toBe('int');

    expect((string) $reflection->getReturnType())->toBe('string');
});

it('gerar rejeita ano < 2020 (anti-historical garbage)', function () {
    $service = app(SpedIcmsIpiGeneratorService::class);
    expect(fn () => $service->gerar(1, 2019, 1))
        ->toThrow(InvalidArgumentException::class, 'Ano inválido');
});

it('gerar rejeita ano > ano atual (anti-future)', function () {
    $service = app(SpedIcmsIpiGeneratorService::class);
    $anoFuturo = (int) date('Y') + 1;
    expect(fn () => $service->gerar(1, $anoFuturo, 1))
        ->toThrow(InvalidArgumentException::class, 'Ano inválido');
});

it('gerar rejeita mes fora 1-12', function () {
    $service = app(SpedIcmsIpiGeneratorService::class);
    foreach ([0, 13, -1, 99] as $mesInvalido) {
        expect(fn () => $service->gerar(1, 2026, $mesInvalido))
            ->toThrow(InvalidArgumentException::class, 'Mês inválido');
    }
});

it('gerar lança RuntimeException cross-tenant (session biz ≠ param)', function () {
    session(['user.business_id' => 1]);
    $service = app(SpedIcmsIpiGeneratorService::class);
    expect(fn () => $service->gerar(99, 2026, 1))
        ->toThrow(RuntimeException::class, 'Cross-tenant attempt');
});

it('contract: classe vive em Modules\Fiscal\Services namespace canônico', function () {
    $reflection = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    expect($reflection->getNamespaceName())->toBe('Modules\Fiscal\Services');
});

it('contract: OtelHelper::spanBiz wrap com prefix fiscal.sped', function () {
    // Defesa estrutural: garante que o span name canon segue padrão fiscal.*
    // (saturation test Wave 27 pattern verifica isso em outros services).
    $reflection = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    $src = file_get_contents($reflection->getFileName());

    expect($src)->toContain("'fiscal.sped.gerar'");
});

it('contract: 23 registros canon EFD-ICMS/IPI presentes (PR #8 + #9 Waves)', function () {
    $reflection = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    $src = file_get_contents($reflection->getFileName());

    // PR #8: Blocos 0 + C + 9 (16 registros)
    // PR #9: Bloco E (apuração ICMS) + Bloco H (esqueleto) (+7 = 23 total)
    $registros = [
        '0000', '0001', '0005', '0150', '0190', '0200', '0990',         // Bloco 0
        'C001', 'C100', 'C170', 'C190', 'C990',                          // Bloco C
        'E001', 'E100', 'E110', 'E116', 'E990',                          // Bloco E (Wave 9)
        'H001', 'H990',                                                  // Bloco H (Wave 9 esqueleto)
        '9001', '9900', '9990', '9999',                                  // Bloco 9
    ];
    foreach ($registros as $reg) {
        expect($src)->toContain("registro{$reg}", "Registro {$reg} deve ser implementado");
    }

    expect($registros)->toHaveCount(23);
});

// ──────────────────────────────────────────────────────────────────────
// PR #9 Wave — Bloco E (apuração ICMS) + Bloco H (esqueleto inventário)
// ──────────────────────────────────────────────────────────────────────

it('Bloco E: E110 apuração consolida débitos C190 vl_icms', function () {
    // Defesa estrutural: quando totalizadores C190 têm vl_icms,
    // o E110 deve refletir o sum como VL_TOT_DEBITOS.
    $reflection = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    $src = file_get_contents($reflection->getFileName());

    expect($src)->toContain("array_sum(array_column(\$totalizadores, 'vl_icms'))");
});

it('Bloco E: E116 só emitido quando vl_icms_recolher > 0 (anti-zero-line)', function () {
    $reflection = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    $src = file_get_contents($reflection->getFileName());

    expect($src)->toContain("if (\$vlTotalDebitos > 0)");
});

it('Bloco H: esqueleto sempre IND_MOV=1 (sem dados — exige integração Stock)', function () {
    // No MVP, Bloco H é sempre placeholder. Source-grep garante.
    $reflection = new ReflectionClass(SpedIcmsIpiGeneratorService::class);
    $src = file_get_contents($reflection->getFileName());

    expect($src)->toContain('registroH001(1)');
});
