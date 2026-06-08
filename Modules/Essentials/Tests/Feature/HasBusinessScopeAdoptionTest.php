<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
use Modules\Essentials\Entities\Document;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\KnowledgeBase;
use Modules\Essentials\Entities\Reminder;
use Modules\Essentials\Entities\ToDo;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Auditoria de adoção dos traits HasBusinessScope + LogsActivity (Wave 12 — sessão 2026-05-16).
 *
 * Valida que as 5 Entities core Essentials com coluna `business_id` direta usam:
 *  - `App\Concerns\HasBusinessScope` (ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL — D1 boost)
 *  - `Spatie\Activitylog\Traits\LogsActivity` (D7 LGPD audit trail append-only)
 *
 * Entidades cobertas (todas têm `business_id` em migrations):
 *  - ToDo (essentials_to_dos)
 *  - EssentialsLeave (essentials_leaves)
 *  - Reminder (essentials_reminders)
 *  - Document (essentials_documents)
 *  - KnowledgeBase (essentials_kb)
 *
 * **Por que não estende a outras Entities Essentials:**
 *  - EssentialsAttendance / EssentialsHoliday: têm business_id mas baixo D7 risco (sem PII texto-livre)
 *  - PayrollGroup / EssentialsAllowanceAndDeduction: backlog próxima onda (financeiro)
 *  - EssentialsLeaveType / Shift: lookup tables (config-like)
 *
 * @see Modules/Essentials/Entities/ToDo.php
 * @see Modules/Essentials/Config/retention.php (D7 LGPD política)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('Entities Essentials core usam HasBusinessScope (Wave 12 D1)', function () {
    $expected = [
        ToDo::class,
        EssentialsLeave::class,
        Reminder::class,
        Document::class,
        KnowledgeBase::class,
    ];

    $missing = [];
    foreach ($expected as $fqcn) {
        $traits = class_uses_recursive($fqcn);
        if (! in_array(HasBusinessScope::class, $traits, true)) {
            $missing[] = $fqcn;
        }
    }

    expect($missing)->toBeEmpty(
        "Entities Essentials sem HasBusinessScope (violação ADR 0093 Wave 12):\n  - "
            . implode("\n  - ", $missing)
    );
});

it('Entities Essentials core usam LogsActivity (Wave 12 D7 LGPD)', function () {
    $expected = [
        ToDo::class,
        EssentialsLeave::class,
        Reminder::class,
        Document::class,
        KnowledgeBase::class,
    ];

    $missing = [];
    foreach ($expected as $fqcn) {
        $traits = class_uses_recursive($fqcn);
        if (! in_array(LogsActivity::class, $traits, true)) {
            $missing[] = $fqcn;
        }
    }

    expect($missing)->toBeEmpty(
        "Entities Essentials sem LogsActivity (violação D7 LGPD audit trail Wave 12):\n  - "
            . implode("\n  - ", $missing)
    );
});

it('ScopeByBusiness está registrado como global scope nas 5 Entities (sanity check)', function () {
    foreach ([ToDo::class, EssentialsLeave::class, Reminder::class, Document::class, KnowledgeBase::class] as $fqcn) {
        $globalScopes = (new $fqcn())->getGlobalScopes();
        expect($globalScopes)->toHaveKey(ScopeByBusiness::class);
    }
});

it('config/retention.php Essentials existe e expõe entities canônicas (D7)', function () {
    $path = base_path('Modules/Essentials/Config/retention.php');

    expect(file_exists($path))->toBeTrue("Modules/Essentials/Config/retention.php ausente (D7 LGPD declaração canônica)");

    $config = require $path;

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['enabled', 'entities', 'strategy', 'notice_period_days']);

    expect($config['entities'])->toHaveKeys([
        'todo',
        'essentials_leave',
        'reminder',
        'document',
        'document_share',
        'knowledge_base',
    ]);

    // Default safe — purge job ainda não implementado (ADR 0105 sinal qualificado)
    expect($config['enabled'])->toBeFalse();

    // Strategy default anonymize (preserva agregados sem PII bruta)
    expect($config['strategy'])->toBe('anonymize');
});

it('LeaveAuditService + ReminderAuditService podem ser instanciados via container (DI sanity)', function () {
    $leaveSvc = app(\Modules\Essentials\Services\LeaveAuditService::class);
    $reminderSvc = app(\Modules\Essentials\Services\ReminderAuditService::class);

    expect($leaveSvc)->toBeInstanceOf(\Modules\Essentials\Services\LeaveAuditService::class);
    expect($reminderSvc)->toBeInstanceOf(\Modules\Essentials\Services\ReminderAuditService::class);
});

it('HasBusinessScope trait pode ser removida com withoutGlobalScope (escape valve sane)', function () {
    // Sanity: o escape valve oficial pra superadmin/jobs continua funcionando
    $query = ToDo::withoutGlobalScope(ScopeByBusiness::class)->getQuery();
    expect($query)->not->toBeNull();

    $query2 = EssentialsLeave::withoutGlobalScope(ScopeByBusiness::class)->getQuery();
    expect($query2)->not->toBeNull();
});
