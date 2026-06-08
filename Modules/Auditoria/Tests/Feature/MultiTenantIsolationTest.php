<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Testa isolamento multi-tenant Tier 0 do Modules/Auditoria.
 *
 * Auditoria NAO tem Entity propria — espelha `activity_log` (Spatie) que
 * recebeu coluna `business_id` via migration legacy (2021_03_16) + colunas
 * causer_kind/reverted_* via US-AUDIT-005. Isolamento e enforced no
 * AuditoriaController via where('activity_log.business_id', $businessId)
 * direto na query (nao via global scope).
 *
 * Este teste valida que:
 *   - Activity criada com biz=1 NAO aparece quando filtro biz=99 e aplicado
 *   - Activity criada com biz=1 APARECE quando filtro biz=1 e aplicado
 *   - RevertService::canRevert() bloqueia cross-tenant (biz=99 user tentando
 *     reverter Activity biz=1 deve receber deny)
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa producao) — conforme
 * ADR 0101. Tests usam biz=1 (Wagner WR2) e biz=99 (ficticio).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0127-modules-auditoria-ui-undo.md
 */

uses(Tests\TestCase::class);

// Guard SQLite: activity_log com business_id + causer_kind requer schema MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: activity_log com business_id + causer_kind requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log table missing — rode migrations Spatie + 2021_03_16_add_business_id primeiro');
    }
    if (! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('coluna business_id ausente em activity_log — rode migration 2021_03_16_add_business_id_to_activity_log_table');
    }
});

// IDs usados nos testes — biz=1 (Wagner) e biz=99 (ficticio isolamento)
const AUDIT_BIZ_WAGNER = 1;
const AUDIT_BIZ_FICTICIO = 99;

// Marcadores unicos pra cleanup isolado (nao colide com producao)
const AUDIT_LOG_NAME_TEST = 'auditoria-multitenant-test';

// ------------------------------------------------------------------
// Helpers internos
// ------------------------------------------------------------------

/**
 * Simula sessao de um business sem autenticar usuario.
 */
function setAuditoriaBizSession(int $businessId): void
{
    session(['user.business_id' => $businessId]);
}

// ------------------------------------------------------------------
// Cenarios
// ------------------------------------------------------------------

it('Activity biz=1 nao aparece em query filtrada por biz=99', function () {
    // Cria Activity diretamente (Spatie nao usa global scope nessa Model)
    $activity = Activity::create([
        'log_name'    => AUDIT_LOG_NAME_TEST,
        'description' => 'criou registro de teste auditoria',
        'event'       => 'created',
        'business_id' => AUDIT_BIZ_WAGNER,
        'properties'  => ['old' => [], 'attributes' => ['nome' => 'X']],
    ]);

    // Replica filtro que AuditoriaController::index() aplica
    setAuditoriaBizSession(AUDIT_BIZ_FICTICIO);
    $resultado = Activity::query()
        ->where('activity_log.business_id', AUDIT_BIZ_FICTICIO)
        ->where('id', $activity->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Activity::where('log_name', AUDIT_LOG_NAME_TEST)
        ->where('business_id', AUDIT_BIZ_WAGNER)
        ->delete();
});

it('Activity biz=1 aparece em query filtrada por biz=1', function () {
    $activity = Activity::create([
        'log_name'    => AUDIT_LOG_NAME_TEST,
        'description' => 'atualizou registro de teste auditoria',
        'event'       => 'updated',
        'business_id' => AUDIT_BIZ_WAGNER,
        'properties'  => ['old' => ['status' => 'A'], 'attributes' => ['status' => 'B']],
    ]);

    setAuditoriaBizSession(AUDIT_BIZ_WAGNER);
    $resultado = Activity::query()
        ->where('activity_log.business_id', AUDIT_BIZ_WAGNER)
        ->where('id', $activity->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->event)->toBe('updated');
    expect((int) $resultado->first()->business_id)->toBe(AUDIT_BIZ_WAGNER);
})->afterEach(function () {
    Activity::where('log_name', AUDIT_LOG_NAME_TEST)
        ->where('business_id', AUDIT_BIZ_WAGNER)
        ->delete();
});

it('listing scoped biz=99 nao vaza Activity de outro tenant biz=1', function () {
    // Cria 3 Activities em biz=1
    for ($i = 0; $i < 3; $i++) {
        Activity::create([
            'log_name'    => AUDIT_LOG_NAME_TEST,
            'description' => "ativ vazamento {$i}",
            'event'       => 'created',
            'business_id' => AUDIT_BIZ_WAGNER,
            'properties'  => ['old' => [], 'attributes' => ['idx' => $i]],
        ]);
    }

    // Lista com filtro biz=99 — nao deve trazer nenhuma das 3
    setAuditoriaBizSession(AUDIT_BIZ_FICTICIO);
    $vazamento = Activity::query()
        ->where('activity_log.business_id', AUDIT_BIZ_FICTICIO)
        ->where('log_name', AUDIT_LOG_NAME_TEST)
        ->count();

    expect($vazamento)->toBe(0);
})->afterEach(function () {
    Activity::where('log_name', AUDIT_LOG_NAME_TEST)
        ->where('business_id', AUDIT_BIZ_WAGNER)
        ->delete();
});

it('RevertService::canRevert bloqueia cross-tenant biz=99 user tentando reverter biz=1', function () {
    if (! class_exists(\Modules\Auditoria\Services\RevertService::class)) {
        $this->markTestSkipped('RevertService nao existe (US-AUDIT-008 pendente).');
    }
    if (! class_exists(\App\User::class)) {
        $this->markTestSkipped('App\\User nao encontrado — schema UltimatePOS incompleto.');
    }

    $activity = Activity::create([
        'log_name'    => AUDIT_LOG_NAME_TEST,
        'description' => 'activity biz=1 candidata a revert',
        'event'       => 'updated',
        'business_id' => AUDIT_BIZ_WAGNER,
        'subject_type' => \App\Transaction::class,
        'subject_id'  => 1, // pode ate nao existir — deny vem antes pela checagem de biz
        'properties'  => ['old' => ['status' => 'final'], 'attributes' => ['status' => 'draft']],
    ]);

    // User com business_id=99 (ficticio) — nao deve poder reverter Activity biz=1
    $userBiz99 = new \App\User();
    $userBiz99->id = 999999;
    $userBiz99->business_id = AUDIT_BIZ_FICTICIO;

    $service = new \Modules\Auditoria\Services\RevertService();
    $check = $service->canRevert($activity, $userBiz99);

    expect($check->allowed)->toBeFalse();
    expect($check->reason)->toContain('Tier 0');
})->afterEach(function () {
    Activity::where('log_name', AUDIT_LOG_NAME_TEST)
        ->where('business_id', AUDIT_BIZ_WAGNER)
        ->delete();
});
