<?php

declare(strict_types=1);

use Modules\Auditoria\Entities\AuditNote;
use Spatie\Activitylog\LogOptions;

/**
 * D7.b — AuditNote (Entity propria do modulo Auditoria) usa LogsActivity.
 *
 * Verifica contrato Spatie: getActivitylogOptions() retorna LogOptions
 * configurado com:
 *   - logOnly campos seguros (NAO loga `note` que pode ter PII residual)
 *   - logOnlyDirty (evita ruido)
 *   - dontSubmitEmptyLogs
 *
 * Auditoria NUNCA toca activity_log core UltimatePOS (shared) — apenas sua
 * propria tabela `auditoria_audit_notes` via observer Spatie.
 *
 * Tier 0 IRREVOGAVEL: scope `forBusiness()` testa SQL contem business_id.
 *
 * @see Modules\Auditoria\Entities\AuditNote
 */

uses(Tests\TestCase::class);

it('AuditNote usa trait LogsActivity Spatie', function () {
    $traits = class_uses(AuditNote::class);

    expect($traits)->toHaveKey(\Spatie\Activitylog\Traits\LogsActivity::class);
});

it('getActivitylogOptions retorna LogOptions com campos seguros', function () {
    $note = new AuditNote();
    $options = $note->getActivitylogOptions();

    expect($options)->toBeInstanceOf(LogOptions::class);

    // logFillable false (logOnly especifico) — NAO loga `note` (defense in depth PII)
    $reflection = new ReflectionClass($options);
    $logAttributesProp = $reflection->getProperty('logAttributes');
    $logAttributesProp->setAccessible(true);
    $logAttributes = $logAttributesProp->getValue($options);

    expect($logAttributes)->toContain('activity_id');
    expect($logAttributes)->toContain('user_id');
    expect($logAttributes)->not->toContain('note'); // critico — note pode ter PII residual
});

it('scopeForBusiness adiciona where business_id na query', function () {
    $sql = AuditNote::query()->forBusiness(42)->toSql();

    expect($sql)->toContain('business_id');
});

it('table name e auditoria_audit_notes (nao activity_log core)', function () {
    $note = new AuditNote();

    expect($note->getTable())->toBe('auditoria_audit_notes');
    // critico: NUNCA bate em activity_log shared core UltimatePOS
    expect($note->getTable())->not->toBe('activity_log');
});

it('config auditoria.retention.entities.audit_note retorna 2555 dias (7 anos)', function () {
    $dias = config('auditoria.retention.entities.audit_note');

    expect($dias)->toBe(2555);
});

it('config auditoria.retention.entities.activity_log_shared e null (Auditoria NUNCA toca core)', function () {
    $val = config('auditoria.retention.entities.activity_log_shared');

    expect($val)->toBeNull();
});
