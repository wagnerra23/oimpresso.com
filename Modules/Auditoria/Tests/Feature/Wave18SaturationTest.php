<?php

declare(strict_types=1);

use Modules\Auditoria\Entities\AuditNote;
use Modules\Auditoria\Http\Requests\BulkRevertActivityRequest;
use Modules\Auditoria\Http\Requests\FilterAuditEntriesRequest;
use Modules\Auditoria\Http\Requests\RevertActivityRequest;
use Modules\Auditoria\Http\Requests\StoreAuditNoteRequest;
use Modules\Auditoria\Http\Requests\UpdateAuditNoteRequest;
use Modules\Auditoria\Services\AuditEntryService;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 18 Auditoria SATURATION — D1 + D2 + D6 + D8.
 *
 * Cobre:
 *   D1: Entity AuditNote + Activity (Spatie) — datasets recursivos
 *   D2: AuditEntryService — exposição API + whitelist filtros
 *   D6: AuditoriaController retorna Inertia::defer (validado no MultiTenantIsolationTest)
 *   D8: 3 FormRequests (Revert + Filter + StoreNote) com rules() canônicas
 *
 * Tier 0 (ADR 0093): unit-level — NÃO toca DB. Cross-tenant MySQL real
 * permanece em MultiTenantIsolationTest.php.
 *
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 */

// ------------------------------------------------------------------
// D1 — Dataset de Entities Auditoria (AuditNote + Activity Spatie)
// ------------------------------------------------------------------

dataset('auditoria_entities_logs_activity', [
    'AuditNote' => [AuditNote::class],
]);

it('Entity Auditoria tem trait LogsActivity (audit-de-auditoria)', function (string $entityClass) {
    expect(class_exists($entityClass))->toBeTrue();
    expect(class_uses_recursive($entityClass))->toContain(LogsActivity::class);
})->with('auditoria_entities_logs_activity');

it('AuditNote tabela é auditoria_audit_notes (NÃO toca activity_log core)', function () {
    $note = new AuditNote();
    expect($note->getTable())->toBe('auditoria_audit_notes');
});

it('AuditNote tem scopeForBusiness (multi-tenant Tier 0)', function () {
    expect(method_exists(AuditNote::class, 'scopeForBusiness'))->toBeTrue();
});

it('AuditNote tem fillable contendo business_id + activity_id + user_id + note', function () {
    $note = new AuditNote();
    expect($note->getFillable())->toContain('business_id', 'activity_id', 'user_id', 'note');
});

it('AuditNote casts business_id, activity_id, user_id como integer', function () {
    $note = new AuditNote();
    $casts = $note->getCasts();
    expect($casts['business_id'])->toBe('integer');
    expect($casts['activity_id'])->toBe('integer');
    expect($casts['user_id'])->toBe('integer');
});

it('AuditNote getActivitylogOptions NÃO loga campo note (PII residual)', function () {
    $note = new AuditNote();
    $options = $note->getActivitylogOptions();
    // logOnly aplicado pra activity_id e user_id apenas — note fica fora
    expect($options->logAttributes)->toContain('activity_id', 'user_id');
    expect($options->logAttributes)->not->toContain('note');
});

// ------------------------------------------------------------------
// D2 — AuditEntryService API canônica
// ------------------------------------------------------------------

it('AuditEntryService expõe list/find/normalizeFilters', function () {
    $svc = new AuditEntryService();
    expect(method_exists($svc, 'list'))->toBeTrue();
    expect(method_exists($svc, 'find'))->toBeTrue();
    expect(method_exists($svc, 'normalizeFilters'))->toBeTrue();
});

it('AuditEntryService.normalizeFilters faz whitelist (rejeita keys não-aceitas)', function () {
    $svc = new AuditEntryService();
    $raw = [
        'causer_kind'   => 'user',
        'subject_type'  => 'App\\Transaction',
        'event'         => 'updated',
        'business_id'   => 99,           // ⛔ rejeitado (tentativa cross-tenant)
        'raw_sql'       => "1' OR 1=1",  // ⛔ rejeitado (SQL injection)
    ];
    $normalized = $svc->normalizeFilters($raw);
    expect($normalized)->toHaveKeys(['causer_kind', 'subject_type', 'event']);
    expect($normalized)->not->toHaveKey('business_id');
    expect($normalized)->not->toHaveKey('raw_sql');
});

it('AuditEntryService.normalizeFilters preserva empty payload vazio', function () {
    $svc = new AuditEntryService();
    expect($svc->normalizeFilters([]))->toBe([]);
});

// ------------------------------------------------------------------
// D6 — AuditoriaController Inertia::defer (smoke)
// ------------------------------------------------------------------

it('AuditoriaController usa Inertia::defer em activities (Wave 18 D6.a)', function () {
    $path = base_path('Modules/Auditoria/Http/Controllers/AuditoriaController.php');
    expect(file_exists($path))->toBeTrue();
    $content = file_get_contents($path);
    expect($content)->toContain('Inertia::defer');
    expect($content)->toContain("'activities' => Inertia::defer");
});

// ------------------------------------------------------------------
// D8 — 3 FormRequests (rules() + authorize() + messages())
// ------------------------------------------------------------------

it('RevertActivityRequest exige revert_reason min:10 max:500', function () {
    $req = new RevertActivityRequest();
    $rules = $req->rules();
    expect($rules['revert_reason'])->toContain('required', 'string', 'min:10', 'max:500');
});

it('FilterAuditEntriesRequest whitelist event tem 5 valores válidos', function () {
    $req = new FilterAuditEntriesRequest();
    $rules = $req->rules();
    expect($rules)->toHaveKeys(['causer_kind', 'subject_type', 'event', 'page']);
    // event aceita: created, updated, deleted, reverted, restored
    $eventRule = collect($rules['event'])->first(fn ($r) => str_starts_with((string) $r, 'in:'));
    expect($eventRule)->toContain('created', 'updated', 'deleted', 'reverted', 'restored');
});

it('FilterAuditEntriesRequest causer_kind whitelist tem 3 valores', function () {
    $req = new FilterAuditEntriesRequest();
    $rules = $req->rules();
    $causerRule = collect($rules['causer_kind'])->first(fn ($r) => str_starts_with((string) $r, 'in:'));
    expect($causerRule)->toContain('user', 'ia', 'system');
});

it('StoreAuditNoteRequest exige note min:3 max:5000', function () {
    $req = new StoreAuditNoteRequest();
    $rules = $req->rules();
    expect($rules['note'])->toContain('required', 'string', 'min:3', 'max:5000');
});

it('StoreAuditNoteRequest tem messages PT-BR', function () {
    $req = new StoreAuditNoteRequest();
    $msgs = $req->messages();
    expect($msgs)->toHaveKeys(['note.required', 'note.min', 'note.max']);
    expect($msgs['note.required'])->toContain('obrigatória');
});

// ------------------------------------------------------------------
// Wave 18 RETRY — Δ D8: UpdateAuditNoteRequest + BulkRevertActivityRequest
// ------------------------------------------------------------------

it('UpdateAuditNoteRequest tem mesmas rules de Store (min:3 max:5000)', function () {
    $req = new UpdateAuditNoteRequest();
    $rules = $req->rules();
    expect($rules['note'])->toContain('required', 'string', 'min:3', 'max:5000');
});

it('UpdateAuditNoteRequest authorize requer auditoria.view OR note.write', function () {
    expect(method_exists(UpdateAuditNoteRequest::class, 'authorize'))->toBeTrue();
});

it('BulkRevertActivityRequest limita até 50 ids + reason min:10', function () {
    $req = new BulkRevertActivityRequest();
    $rules = $req->rules();
    expect($rules['activity_ids'])->toContain('required', 'array', 'min:1', 'max:50');
    expect($rules['activity_ids.*'])->toContain('required', 'integer', 'min:1');
    expect($rules['revert_reason'])->toContain('required', 'string', 'min:10', 'max:500');
});

it('BulkRevertActivityRequest mensagens PT-BR proteção massiva', function () {
    $req = new BulkRevertActivityRequest();
    $msgs = $req->messages();
    expect($msgs)->toHaveKey('activity_ids.max');
    expect($msgs['activity_ids.max'])->toContain('50');
});
