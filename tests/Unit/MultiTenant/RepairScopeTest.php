<?php

declare(strict_types=1);

/**
 * Smoke unit test multi-tenant Tier 0 — Modules/Repair.
 *
 * Garante que após PR #436 (HasBusinessScope aplicado em JobSheet,
 * RepairStatus, DeviceModel):
 *   1. Cada model usa o trait HasBusinessScope
 *   2. Defesa-em-profundidade contra os vazamentos detectados em auditoria:
 *      - JobSheetController:808 (RepairStatus::find sem filtro)
 *      - JobSheetController:1083 (JobSheet::with(...)->find similar)
 *      Com global scope ativo, mesmo find() puro filtra business_id.
 *
 * Tests Unit-only (sem DB) — modelo canônico em
 * tests/Unit/Concerns/HasBusinessScopeTest.php.
 *
 * Tests de isolamento real cross-tenant (find() em biz=1 retorna null se
 * row pertence a biz=99) ficam em Modules/Repair/Tests/Feature/ —
 * follow-up exigindo DB local com migrations core UltimatePOS
 * (regra Wagner 2026-05-09).
 *
 * @see ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
 * @see PR #436 (Repair models trait HasBusinessScope)
 */

use App\Concerns\HasBusinessScope;
use Modules\Repair\Entities\DeviceModel;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;

test('Repair JobSheet usa HasBusinessScope trait', function () {
    $traits = class_uses_recursive(JobSheet::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('Repair RepairStatus usa HasBusinessScope trait', function () {
    $traits = class_uses_recursive(RepairStatus::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('Repair DeviceModel usa HasBusinessScope trait', function () {
    $traits = class_uses_recursive(DeviceModel::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('Repair models não têm boot manual com addGlobalScope (paridade migração)', function () {
    // Após PR #436, models migraram do addGlobalScope manual pro trait.
    // Se voltar a aparecer addGlobalScope(new ScopeByBusiness) inline,
    // sinaliza regressão / duplicação de scope.
    $models = [
        JobSheet::class,
        RepairStatus::class,
        DeviceModel::class,
    ];

    foreach ($models as $model) {
        $reflection = new ReflectionClass($model);
        $contents = file_get_contents($reflection->getFileName());

        expect($contents)
            ->not->toContain('addGlobalScope(new ScopeByBusiness)', "$model ainda tem addGlobalScope manual");
    }
});
