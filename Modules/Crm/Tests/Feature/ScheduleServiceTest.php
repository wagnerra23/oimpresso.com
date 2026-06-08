<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Services\ScheduleService;

uses(Tests\TestCase::class);

/**
 * Smoke test do ScheduleService — Wave J D4.a boost (2026-05-16).
 *
 * Testa o Service thin extraído de ScheduleController:
 *   1. `scopedQuery()` retorna Builder com filtro business_id correto (cross-tenant)
 *   2. `scopedQuery()` aplica filtro `access_own_schedule` (where created_by)
 *   3. Service não vaza follow-up biz=1 quando query escopada por biz=99
 *
 * ADR 0093: business_id Tier 0 — todo Service que toca dados de negócio recebe
 *           `$businessId` no método (não em session — back-pressure pra Job assíncrono).
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa PRODUÇÃO) em tests.
 *           Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados reais).
 *
 * @see Modules\Crm\Services\ScheduleService
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const SCHED_BIZ_WAGNER   = 1;
const SCHED_BIZ_FICTICIO = 99;

beforeEach(function () {
    // SQLite guard: schema UltimatePOS (FKs contacts, business, users) só roda em MySQL real.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: ScheduleService requer schema MySQL UltimatePOS com FKs business/contacts/users (ADR 0101)');
    }
    if (! Schema::hasTable('crm_schedules')) {
        $this->markTestSkipped('crm_schedules table missing — rode Modules/Crm migrate primeiro');
    }
});

it('scopedQuery filtra por business_id (biz=1 não vaza pra query biz=99)', function () {
    $contactId = DB::table('contacts')->where('business_id', SCHED_BIZ_WAGNER)->value('id');
    if (! $contactId) {
        $this->markTestSkipped('Nenhum contato biz=1 encontrado pra satisfazer FK crm_schedules.contact_id');
    }

    $sched = Schedule::create([
        'business_id'    => SCHED_BIZ_WAGNER,
        'contact_id'     => $contactId,
        'title'          => 'SVC-TEST Followup BIZ1 11111',
        'status'         => 'open',
        'start_datetime' => now(),
        'end_datetime'   => now()->addHour(),
        'schedule_type'  => 'call',
        'created_by'     => 1,
    ]);

    $service = app(ScheduleService::class);

    // Query escopada por biz=99 não deve enxergar follow-up de biz=1.
    $resultado = $service
        ->scopedQuery(SCHED_BIZ_FICTICIO, authUserId: 1, canAccessAll: true, canAccessOwn: false)
        ->where('id', $sched->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Schedule::where('title', 'SVC-TEST Followup BIZ1 11111')->delete();
});

it('scopedQuery encontra follow-up biz=1 quando query escopada por biz=1', function () {
    $contactId = DB::table('contacts')->where('business_id', SCHED_BIZ_WAGNER)->value('id');
    if (! $contactId) {
        $this->markTestSkipped('Nenhum contato biz=1 encontrado pra satisfazer FK crm_schedules.contact_id');
    }

    $sched = Schedule::create([
        'business_id'    => SCHED_BIZ_WAGNER,
        'contact_id'     => $contactId,
        'title'          => 'SVC-TEST Followup BIZ1 22222',
        'status'         => 'scheduled',
        'start_datetime' => now(),
        'end_datetime'   => now()->addHour(),
        'schedule_type'  => 'meeting',
        'created_by'     => 1,
    ]);

    $service = app(ScheduleService::class);

    $resultado = $service
        ->scopedQuery(SCHED_BIZ_WAGNER, authUserId: 1, canAccessAll: true, canAccessOwn: false)
        ->where('id', $sched->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->title)->toBe('SVC-TEST Followup BIZ1 22222');
    expect((int) $resultado->first()->business_id)->toBe(SCHED_BIZ_WAGNER);
})->afterEach(function () {
    Schedule::where('title', 'SVC-TEST Followup BIZ1 22222')->delete();
});

it('scopedQuery aplica filtro access_own quando canAccessAll=false', function () {
    $contactId = DB::table('contacts')->where('business_id', SCHED_BIZ_WAGNER)->value('id');
    if (! $contactId) {
        $this->markTestSkipped('Nenhum contato biz=1 encontrado pra satisfazer FK crm_schedules.contact_id');
    }

    // Follow-up criado por user 1.
    $sched = Schedule::create([
        'business_id'    => SCHED_BIZ_WAGNER,
        'contact_id'     => $contactId,
        'title'          => 'SVC-TEST Followup OWN 33333',
        'status'         => 'open',
        'start_datetime' => now(),
        'end_datetime'   => now()->addHour(),
        'schedule_type'  => 'call',
        'created_by'     => 1,
    ]);

    $service = app(ScheduleService::class);

    // User 999 sem assignment NÃO deve enxergar (created_by=1 ≠ 999, e sem pivot).
    $resultadoOutroUser = $service
        ->scopedQuery(SCHED_BIZ_WAGNER, authUserId: 999, canAccessAll: false, canAccessOwn: true)
        ->where('id', $sched->id)
        ->get();

    expect($resultadoOutroUser)->toHaveCount(0);

    // User 1 (criador) enxerga porque created_by casa.
    $resultadoCriador = $service
        ->scopedQuery(SCHED_BIZ_WAGNER, authUserId: 1, canAccessAll: false, canAccessOwn: true)
        ->where('id', $sched->id)
        ->get();

    expect($resultadoCriador)->toHaveCount(1);
})->afterEach(function () {
    Schedule::where('title', 'SVC-TEST Followup OWN 33333')->delete();
});
