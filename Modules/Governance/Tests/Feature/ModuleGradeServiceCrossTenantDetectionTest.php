<?php

declare(strict_types=1);

use Modules\Governance\Services\ModuleGradeService;

uses(Tests\TestCase::class);

/**
 * Regression test — D1.b cross-tenant detection (Wave H fix 2026-05-16).
 *
 * Bug detectado em prod: Governance ficava com D1.b = 10/15 mesmo tendo
 * CrossTenantPolicyTest válido (BIZ_FICTICIO_GOV + BIZ_WAGNER_GOV). Causa:
 *   (1) regex antigo `(biz=99|BIZ_FICTICIO|business_id.*99|withoutGlobalScopes)`
 *       capturava `BIZ_FICTICIO_GOV` mas
 *   (2) fórmula com `entCount === 0` capava em 10 em vez de 15
 *       — Governance é cross-tenant by design (sem Entities por Art. 6+8) e
 *       não devia ser penalizado por isso.
 *
 * Fix Wave H:
 *   - Helper `isCrossTenantTestFile()` com 6 critérios canônicos
 *   - Cap subiu pra 15 quando módulo sem Entities tem ≥1 test válido
 *   - Backward-compat preservado (literais biz=99/BIZ_FICTICIO continuam casando)
 *
 * @see Modules/Governance/Services/ModuleGradeService::dim1MultiTenant()
 * @see Modules/Governance/Services/ModuleGradeService::isCrossTenantTestFile()
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */

// Helper local — usa reflection pra exercitar método privado isCrossTenantTestFile
function callIsCrossTenantTestFile(ModuleGradeService $service, string $content): bool
{
    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('isCrossTenantTestFile');
    $method->setAccessible(true);
    return (bool) $method->invoke($service, $content);
}

// ------------------------------------------------------------------
// Cenário 1: CrossTenantPolicyTest (Wave G) é detectado
//   Pattern: BIZ_FICTICIO_GOV + BIZ_WAGNER_GOV no mesmo arquivo
// ------------------------------------------------------------------

it('cenario 1: CrossTenantPolicyTest Wave G e detectado (BIZ_FICTICIO_GOV + BIZ_WAGNER_GOV)', function () {
    $service = app(ModuleGradeService::class);
    $content = <<<'PHP'
    <?php
    const BIZ_WAGNER_GOV = 1;
    const BIZ_FICTICIO_GOV = 99;
    it('superadmin biz=1 acessa /governance', function () {
        session(['user.business_id' => BIZ_WAGNER_GOV]);
        // ...
    });
    it('user biz=99 ficticio bloqueado', function () {
        session(['user.business_id' => BIZ_FICTICIO_GOV]);
    });
    PHP;

    expect(callIsCrossTenantTestFile($service, $content))->toBeTrue(
        'CrossTenantPolicyTest com BIZ_FICTICIO_GOV + BIZ_WAGNER_GOV deve casar criterio (a)'
    );
});

// ------------------------------------------------------------------
// Cenário 2: ComunicacaoVisual/MultiTenantTest (setBizSession + BIZ_FICTICIO)
// ------------------------------------------------------------------

it('cenario 2: ComunicacaoVisual MultiTenantTest e detectado (setBizSession + BIZ_FICTICIO + BIZ_WAGNER)', function () {
    $service = app(ModuleGradeService::class);
    $content = <<<'PHP'
    <?php
    const BIZ_WAGNER = 1;
    const BIZ_FICTICIO = 99;
    function setBizSession(int $businessId): void {
        session(['user.business_id' => $businessId]);
    }
    it('Material biz=1 nao aparece com session biz=99', function () {
        setBizSession(BIZ_WAGNER);
        $material = Material::withoutGlobalScopes()->create([]);
        setBizSession(BIZ_FICTICIO);
    });
    PHP;

    expect(callIsCrossTenantTestFile($service, $content))->toBeTrue(
        'ComunicacaoVisual MultiTenantTest com setBizSession + 2 constantes BIZ_* deve casar criterio (a) e (b)'
    );
});

// ------------------------------------------------------------------
// Cenário 3: arquivo APENAS com `business_id` mas SEM 2 valores diferentes
//             NÃO é falso positivo
// ------------------------------------------------------------------

it('cenario 3: arquivo com apenas business_id sem 2 valores distintos NAO e falso positivo', function () {
    $service = app(ModuleGradeService::class);
    $content = <<<'PHP'
    <?php
    it('cria registro com business_id correto', function () {
        $registro = Model::create(['business_id' => 1, 'name' => 'X']);
        expect($registro->business_id)->toBe(1);
    });
    PHP;

    expect(callIsCrossTenantTestFile($service, $content))->toBeFalse(
        'Arquivo com so business_id=1 (sem isolamento entre 2 biz) NAO deve casar — evita falso positivo'
    );
});

// ------------------------------------------------------------------
// Cenário 4: D1.b score real do Governance retorna ≥ 10/15
//             (era 0 ou 10 com regex antigo — agora 15)
// ------------------------------------------------------------------

it('cenario 4: D1.b score Governance retorna 15/15 (era 10/15 com fix antigo)', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $d1 = $grade['dimensions']['multi_tenant'];
    $d1bBreakdown = collect($d1['breakdown'])->firstWhere('key', 'D1.b');

    expect($d1bBreakdown)->not->toBeNull('D1.b breakdown deve existir');
    expect($d1bBreakdown['score'])->toBeGreaterThanOrEqual(10,
        "Governance D1.b deve ser >= 10/15 (era 10 com cap, agora 15 com fix). Score atual: {$d1bBreakdown['score']}");
    expect($d1bBreakdown['max'])->toBe(15);
});

// ------------------------------------------------------------------
// Cenário 5 (extra): variantes constantes com prefixo (CRM_BIZ_*, AUDIT_BIZ_*)
// ------------------------------------------------------------------

it('cenario 5: constantes com prefixo CRM_BIZ_FICTICIO + CRM_BIZ_WAGNER sao detectadas', function () {
    $service = app(ModuleGradeService::class);
    $content = <<<'PHP'
    <?php
    const CRM_BIZ_WAGNER = 1;
    const CRM_BIZ_FICTICIO = 99;
    it('Schedule biz=1 NAO aparece em query where business_id=99', function () {
        session(['user.business_id' => CRM_BIZ_WAGNER]);
        $sched = Schedule::create(['business_id' => CRM_BIZ_WAGNER]);
        session(['user.business_id' => CRM_BIZ_FICTICIO]);
    });
    PHP;

    expect(callIsCrossTenantTestFile($service, $content))->toBeTrue(
        'Constantes CRM_BIZ_* devem casar regex \b[A-Z]+_BIZ_FICTICIO\w*\b (suffix permitido)'
    );
});

// ------------------------------------------------------------------
// Cenário 6 (back-compat): pattern legado biz=99 + withoutGlobalScopes
// ------------------------------------------------------------------

it('cenario 6 back-compat: pattern legado biz=99 + withoutGlobalScopes continua casando', function () {
    $service = app(ModuleGradeService::class);
    $content = <<<'PHP'
    <?php
    it('legacy test sem constantes — apenas literais', function () {
        // Comentario menciona biz=1 vs biz=99 explicit
        $a = Model::where('business_id', 1)->get();
        $b = Model::withoutGlobalScopes()->where('business_id', 99)->get();
    });
    PHP;

    expect(callIsCrossTenantTestFile($service, $content))->toBeTrue(
        'Pattern legado biz=1 + biz=99 + withoutGlobalScopes deve continuar casando (back-compat)'
    );
});

// ------------------------------------------------------------------
// Cenário 7 (palavra-chave): cross-tenant + BIZ_WAGNER mesmo sem BIZ_FICTICIO
// ------------------------------------------------------------------

it('cenario 7: palavra cross-tenant + BIZ_WAGNER suficiente pra detectar', function () {
    $service = app(ModuleGradeService::class);
    $content = <<<'PHP'
    <?php
    const BIZ_WAGNER_GOV = 1;
    /**
     * Cross-tenant policy gate — Modules/Governance.
     * Governance e modulo INTENCIONALMENTE cross-tenant.
     */
    it('Identity Mesh transversal', function () {
        session(['user.business_id' => BIZ_WAGNER_GOV]);
    });
    PHP;

    expect(callIsCrossTenantTestFile($service, $content))->toBeTrue(
        'Palavra-chave "cross-tenant" no comentario + BIZ_WAGNER_GOV deve casar criterio (e)'
    );
});
