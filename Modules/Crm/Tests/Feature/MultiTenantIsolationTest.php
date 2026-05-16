<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\Campaign;
use Modules\Crm\Entities\Leaduser;
use Modules\Crm\Entities\Schedule;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 dos Models Crm.
 *
 * IMPORTANTE — Models do CRM (Schedule, Campaign) NÃO têm global scope BusinessScope
 * (padrão UltimatePOS legacy). O isolamento depende de Controllers usarem
 * `where('business_id', auth()->user()->business_id)` explicitamente. Estes testes
 * verificam o contrato mais fraco mas crítico:
 *
 *   1. Coluna `business_id` é NOT NULL e populada em todos os inserts canônicos
 *   2. Query `where('business_id', X)` isola corretamente (não vaza biz Y)
 *   3. `crm_lead_users` NÃO tem business_id direto — herda via `contacts.business_id`
 *
 * ADR 0093: business_id obrigatório em tabelas de negócio.
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) em tests.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados reais).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const CRM_BIZ_WAGNER = 1;
const CRM_BIZ_FICTICIO = 99;

beforeEach(function () {
    // SQLite guard: schema UltimatePOS (FKs contacts, business, users) só roda em MySQL real.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models Crm requerem schema MySQL UltimatePOS com FKs business/contacts (ADR 0101)');
    }
    if (! Schema::hasTable('crm_schedules')) {
        $this->markTestSkipped('crm_schedules table missing — rode Modules/Crm migrate primeiro');
    }
    if (! Schema::hasTable('crm_campaigns')) {
        $this->markTestSkipped('crm_campaigns table missing — rode Modules/Crm migrate primeiro');
    }
});

// ------------------------------------------------------------------
// Schedule (crm_schedules — tem business_id direto)
// ------------------------------------------------------------------

it('Schedule biz=1 NÃO aparece em query where business_id=99', function () {
    // Resolve um contato existente em biz=1 pra satisfazer FK contact_id.
    $contactId = DB::table('contacts')->where('business_id', CRM_BIZ_WAGNER)->value('id');
    if (! $contactId) {
        $this->markTestSkipped('Nenhum contato biz=1 encontrado pra satisfazer FK crm_schedules.contact_id');
    }

    $sched = Schedule::create([
        'business_id'     => CRM_BIZ_WAGNER,
        'contact_id'      => $contactId,
        'title'           => 'Followup TESTE ISO 99991',
        'status'          => 'open',
        'start_datetime'  => now(),
        'end_datetime'    => now()->addHour(),
        'schedule_type'   => 'call',
        'created_by'      => 1,
    ]);

    // Cross-tenant: ninguém com biz=99 deve enxergar via filtro explícito.
    $resultado = Schedule::where('business_id', CRM_BIZ_FICTICIO)
        ->where('id', $sched->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Schedule::where('title', 'Followup TESTE ISO 99991')->delete();
});

it('Schedule biz=1 aparece em query where business_id=1', function () {
    $contactId = DB::table('contacts')->where('business_id', CRM_BIZ_WAGNER)->value('id');
    if (! $contactId) {
        $this->markTestSkipped('Nenhum contato biz=1 encontrado pra satisfazer FK crm_schedules.contact_id');
    }

    $sched = Schedule::create([
        'business_id'     => CRM_BIZ_WAGNER,
        'contact_id'      => $contactId,
        'title'           => 'Followup TESTE ISO 99992',
        'status'          => 'scheduled',
        'start_datetime'  => now(),
        'end_datetime'    => now()->addHour(),
        'schedule_type'   => 'meeting',
        'created_by'      => 1,
    ]);

    $resultado = Schedule::where('business_id', CRM_BIZ_WAGNER)
        ->where('id', $sched->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->title)->toBe('Followup TESTE ISO 99992');
    expect((int) $resultado->first()->business_id)->toBe(CRM_BIZ_WAGNER);
})->afterEach(function () {
    Schedule::where('title', 'Followup TESTE ISO 99992')->delete();
});

// ------------------------------------------------------------------
// Campaign (crm_campaigns — tem business_id direto)
// ------------------------------------------------------------------

it('Campaign biz=1 NÃO aparece em query where business_id=99', function () {
    $camp = Campaign::create([
        'business_id'   => CRM_BIZ_WAGNER,
        'name'          => 'Campanha TESTE ISO 99991',
        'campaign_type' => 'email',
        'subject'       => 'Subject ficticio',
        'email_body'    => '<p>body teste</p>',
        'contact_ids'   => json_encode([]),
        'created_by'    => 1,
    ]);

    $resultado = Campaign::where('business_id', CRM_BIZ_FICTICIO)
        ->where('id', $camp->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Campaign::where('name', 'Campanha TESTE ISO 99991')->delete();
});

it('Campaign biz=1 aparece em query where business_id=1', function () {
    $camp = Campaign::create([
        'business_id'   => CRM_BIZ_WAGNER,
        'name'          => 'Campanha TESTE ISO 99992',
        'campaign_type' => 'sms',
        'sms_body'      => 'Mensagem teste',
        'contact_ids'   => json_encode([]),
        'created_by'    => 1,
    ]);

    $resultado = Campaign::where('business_id', CRM_BIZ_WAGNER)
        ->where('id', $camp->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->name)->toBe('Campanha TESTE ISO 99992');
    expect((int) $resultado->first()->business_id)->toBe(CRM_BIZ_WAGNER);
})->afterEach(function () {
    Campaign::where('name', 'Campanha TESTE ISO 99992')->delete();
});

// ------------------------------------------------------------------
// Leaduser (crm_lead_users — pivot SEM business_id direto)
// ------------------------------------------------------------------
// Isolamento herda de contacts.business_id via FK contact_id ON DELETE CASCADE.
// Verifica que pivot NÃO vaza cross-tenant quando filtrado via join em contacts.

it('Leaduser pivot herda isolamento via contacts.business_id (cross-tenant query bloqueia)', function () {
    $contactBiz1 = DB::table('contacts')->where('business_id', CRM_BIZ_WAGNER)->value('id');
    if (! $contactBiz1) {
        $this->markTestSkipped('Nenhum contato biz=1 encontrado pra criar pivot Leaduser');
    }

    $userIdWagner = DB::table('users')->where('business_id', CRM_BIZ_WAGNER)->value('id');
    if (! $userIdWagner) {
        $this->markTestSkipped('Nenhum user biz=1 encontrado pra pivot Leaduser');
    }

    $pivot = Leaduser::create([
        'contact_id' => $contactBiz1,
        'user_id'    => $userIdWagner,
    ]);

    // Cross-tenant join: pivot só aparece via contacts cujo business_id casa.
    $vazaCrossTenant = Leaduser::join('contacts', 'crm_lead_users.contact_id', '=', 'contacts.id')
        ->where('contacts.business_id', CRM_BIZ_FICTICIO)
        ->where('crm_lead_users.id', $pivot->id)
        ->count();

    expect($vazaCrossTenant)->toBe(0);
})->afterEach(function () {
    // Cleanup defensivo — só os pivots recém criados sem afetar legacy
    DB::table('crm_lead_users')
        ->whereIn('contact_id', function ($q) {
            $q->select('id')->from('contacts')->where('business_id', CRM_BIZ_WAGNER);
        })
        ->where('user_id', DB::table('users')->where('business_id', CRM_BIZ_WAGNER)->value('id'))
        ->orderByDesc('id')
        ->limit(1)
        ->delete();
});

// ------------------------------------------------------------------
// Contract: business_id NOT NULL nas tabelas críticas do Crm
// ------------------------------------------------------------------

it('crm_schedules tem coluna business_id NOT NULL', function () {
    expect(Schema::hasColumn('crm_schedules', 'business_id'))->toBeTrue();
    $col = collect(DB::select('SHOW COLUMNS FROM crm_schedules LIKE ?', ['business_id']))->first();
    expect($col)->not->toBeNull();
    expect($col->Null)->toBe('NO');
});

it('crm_campaigns tem coluna business_id NOT NULL', function () {
    expect(Schema::hasColumn('crm_campaigns', 'business_id'))->toBeTrue();
    $col = collect(DB::select('SHOW COLUMNS FROM crm_campaigns LIKE ?', ['business_id']))->first();
    expect($col)->not->toBeNull();
    expect($col->Null)->toBe('NO');
});
