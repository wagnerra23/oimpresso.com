<?php

declare(strict_types=1);

use Modules\Essentials\Entities\EssentialsAllowanceAndDeduction;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\EssentialsMessage;
use Modules\Essentials\Entities\PayrollGroup;
use Modules\Essentials\Entities\Shift;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 27 POLISH FINAL Essentials — D7 LogsActivity SATURATION em Models RH sensíveis.
 *
 * Cobre:
 *   - 4 Models novos com LogsActivity (D7 LGPD Wave 27):
 *     EssentialsAttendance, EssentialsAllowanceAndDeduction, PayrollGroup, Shift
 *   - EssentialsMessage (já Wave 18, sentinel preservation)
 *   - getActivitylogOptions retorna LogOptions configurado com useLogName per-entity
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO toca DB real. Reflection-only.
 * Tests biz=1 quando aplicável (ADR 0101) — NUNCA biz=4 ROTA LIVRE.
 *
 * @see Modules/Essentials/CHANGELOG.md (Wave 27)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

dataset('models_logs_activity_wave27', [
    'EssentialsAttendance'           => [EssentialsAttendance::class, 'essentials.attendance'],
    'EssentialsAllowanceAndDeduction'=> [EssentialsAllowanceAndDeduction::class, 'essentials.allowance_deduction'],
    'PayrollGroup'                   => [PayrollGroup::class, 'essentials.payroll_group'],
    'Shift'                          => [Shift::class, 'essentials.shift'],
    'EssentialsMessage (W18 sentinel)'=> [EssentialsMessage::class, 'essentials.message'],
]);

it('Wave 27 — Models RH sensíveis usam LogsActivity (D7 LGPD)', function (string $fqcn) {
    $traits = class_uses_recursive($fqcn);
    expect($traits)->toContain(LogsActivity::class);
})->with('models_logs_activity_wave27');

it('Wave 27 — getActivitylogOptions retorna LogOptions com useLogName per-entity', function (string $fqcn, string $expectedLogName) {
    $instance = new $fqcn();
    $opts = $instance->getActivitylogOptions();

    expect($opts)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
    expect($opts->logName)->toBe($expectedLogName);
})->with('models_logs_activity_wave27');

it('Wave 27 — getActivitylogOptions usa logOnlyDirty + dontSubmitEmptyLogs (anti-spam)', function (string $fqcn) {
    $instance = new $fqcn();
    $opts = $instance->getActivitylogOptions();

    // logOnlyDirty evita logar quando nada mudou; dontSubmitEmptyLogs evita activity_log vazio
    expect($opts->logOnlyDirty)->toBeTrue();
    expect($opts->submitEmptyLogs)->toBeFalse();
})->with('models_logs_activity_wave27');

it('Wave 27 — D9 spans: TodoService::listPaginated permanece instrumentado', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/TodoService.php');
    expect($source)->toContain("OtelHelper::spanBiz('essentials.todo.list_paginated'");
    expect($source)->toContain('use App\Util\OtelHelper');
});

it('Wave 27 — D9 spans: LeaveAuditService::recordStatusChange permanece instrumentado', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/LeaveAuditService.php');
    expect($source)->toContain("OtelHelper::spanBiz('essentials.leave.status_change'");
});

it('Wave 27 — D9 spans: ReminderAuditService cobre create/update/delete', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/ReminderAuditService.php');
    expect($source)->toContain("OtelHelper::spanBiz('essentials.reminder.created'");
    expect($source)->toContain("OtelHelper::spanBiz('essentials.reminder.updated'");
    expect($source)->toContain("OtelHelper::spanBiz('essentials.reminder.deleted'");
});

it('Wave 27 — D1 sentinel multi-tenant: HasBusinessScope adoption count >= 13 entries (W18 preserved)', function () {
    // Lock-in: regressão se alguém remover HasBusinessScope de Entities (ADR 0093 Tier 0 IRREVOGÁVEL).
    // W18 saturou 7 diretas + 6 via parent = 13 entries.
    $entitiesDir = __DIR__ . '/../../Entities';
    $files = glob($entitiesDir . '/*.php') ?: [];

    $countHasBusinessScope = 0;
    $countBelongsToBusinessViaParent = 0;
    foreach ($files as $file) {
        $src = file_get_contents($file);
        if (str_contains($src, 'use HasBusinessScope') && str_contains($src, 'use App\\Concerns\\HasBusinessScope')) {
            $countHasBusinessScope++;
        }
        if (str_contains($src, 'use BelongsToBusinessViaParent') && str_contains($src, 'use App\\Concerns\\BelongsToBusinessViaParent')) {
            $countBelongsToBusinessViaParent++;
        }
    }

    expect($countHasBusinessScope)->toBeGreaterThanOrEqual(7, 'Pelo menos 7 entities com business_id direto (W18 SATURATION)');
    expect($countBelongsToBusinessViaParent)->toBeGreaterThanOrEqual(6, 'Pelo menos 6 entities filhas via parent (W18 SATURATION)');
});
