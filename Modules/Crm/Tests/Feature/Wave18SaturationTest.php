<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: saturação Wave 18 Crm — asserts estáticos (reflection/source-grep) de canon móvel (Entities/Services/FormRequests) — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

use Modules\Crm\Entities\Campaign;
use Modules\Crm\Entities\CrmCallLog;
use Modules\Crm\Entities\CrmContactPersonCommission;
use Modules\Crm\Entities\CrmMarketplace;
use Modules\Crm\Entities\Leaduser;
use Modules\Crm\Entities\Proposal;
use Modules\Crm\Entities\ProposalTemplate;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Entities\ScheduleLog;
use Modules\Crm\Entities\ScheduleUser;
use Modules\Crm\Http\Requests\StoreCallLogRequest;
use Modules\Crm\Http\Requests\StoreCrmContactRequest;
use Modules\Crm\Http\Requests\StoreProposalRequest;
use Modules\Crm\Http\Requests\UpdateCallLogRequest;
use Modules\Crm\Http\Requests\UpdateProposalRequest;
use Modules\Crm\Repositories\CrmLeadRepository;
use Modules\Crm\Services\CallLogService;
use Modules\Crm\Services\CrmLeadService;
use Modules\Crm\Services\ProposalService;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 18 Crm SATURATION — D1 + D4 + D8.
 *
 * Cobre 3 frentes:
 *   D1: dataset recursivo de 10 Entities — todas DEVEM ter LogsActivity (LGPD audit trail)
 *   D4: Services novos (ProposalService, CallLogService) + Repository (CrmLeadRepository) existem e expõem API canônica
 *   D8: FormRequests novos (StoreCallLog, UpdateProposal) carregam rules() e authorize() sem hit DB
 *
 * Tier 0 (ADR 0093): NÃO chama session() — tudo é unit-level. Cross-tenant
 * MySQL-real fica em MultiTenantIsolationTest.php (esse é light Pest CI-friendly).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// ------------------------------------------------------------------
// D1 — Dataset recursivo: 10+ Entities Crm validadas em batch
// ------------------------------------------------------------------

dataset('crm_entities_com_logs_activity', [
    'Campaign'                    => [Campaign::class],
    'CrmCallLog'                  => [CrmCallLog::class],
    'CrmContactPersonCommission'  => [CrmContactPersonCommission::class],
    'CrmMarketplace'              => [CrmMarketplace::class],
    'Leaduser'                    => [Leaduser::class],
    'Proposal'                    => [Proposal::class],
    'ProposalTemplate'            => [ProposalTemplate::class],
    'Schedule'                    => [Schedule::class],
    'ScheduleLog'                 => [ScheduleLog::class],
    'ScheduleUser'                => [ScheduleUser::class],
]);

it('Entity Crm tem trait LogsActivity (D7 LGPD compliance)', function (string $entityClass) {
    expect(class_exists($entityClass))->toBeTrue("Entity {$entityClass} deve existir");
    $traits = class_uses_recursive($entityClass);
    expect($traits)->toContain(LogsActivity::class, "Entity {$entityClass} sem LogsActivity — LGPD audit trail quebrado");
})->with('crm_entities_com_logs_activity');

it('Entity Crm declara getActivitylogOptions (configuração explícita Spatie)', function (string $entityClass) {
    expect(method_exists($entityClass, 'getActivitylogOptions'))->toBeTrue(
        "Entity {$entityClass} deve declarar getActivitylogOptions() pra LogsActivity"
    );
})->with('crm_entities_com_logs_activity');

// ------------------------------------------------------------------
// D4 — Services + Repository novos
// ------------------------------------------------------------------

it('ProposalService expõe createProposal/updateProposal/defaultTemplate', function () {
    $svc = new ProposalService();
    expect(method_exists($svc, 'createProposal'))->toBeTrue();
    expect(method_exists($svc, 'updateProposal'))->toBeTrue();
    expect(method_exists($svc, 'defaultTemplate'))->toBeTrue();
    expect($svc->acceptedFields())->toContain('subject', 'body', 'contact_id', 'cc', 'bcc');
});

it('CallLogService expõe baseQuery/applyFilters/restrictToOwner', function () {
    $svc = new CallLogService();
    expect(method_exists($svc, 'baseQuery'))->toBeTrue();
    expect(method_exists($svc, 'applyFilters'))->toBeTrue();
    expect(method_exists($svc, 'restrictToOwner'))->toBeTrue();
    expect(method_exists($svc, 'totalDurationSeconds'))->toBeTrue();
});

it('CrmLeadRepository expõe paginate/findOrFail/countByLifeStage', function () {
    $repo = new CrmLeadRepository();
    expect(method_exists($repo, 'paginate'))->toBeTrue();
    expect(method_exists($repo, 'findOrFail'))->toBeTrue();
    expect(method_exists($repo, 'countByLifeStage'))->toBeTrue();
    expect(method_exists($repo, 'countBySource'))->toBeTrue();
    expect(method_exists($repo, 'count'))->toBeTrue();
});

// ------------------------------------------------------------------
// D8 — FormRequests novos (rules() + authorize() sem session)
// ------------------------------------------------------------------

it('StoreCallLogRequest tem rules() exigindo contact_id + start_time + end_time', function () {
    $req = new StoreCallLogRequest();
    $rules = $req->rules();
    expect($rules)->toHaveKeys(['contact_id', 'start_time', 'end_time']);
    expect($rules['contact_id'])->toContain('required');
    expect($rules['end_time'])->toContain('after_or_equal:start_time');
});

it('StoreCallLogRequest tem mensagens PT-BR pra erros principais', function () {
    $req = new StoreCallLogRequest();
    $msgs = $req->messages();
    expect($msgs)->toHaveKeys(['end_time.after_or_equal', 'duration.max']);
});

it('UpdateProposalRequest aceita rules() sometimes (PATCH parcial)', function () {
    $req = new UpdateProposalRequest();
    $rules = $req->rules();
    expect($rules['subject'])->toContain('sometimes');
    expect($rules['body'])->toContain('sometimes');
    expect($rules['contact_id'])->toContain('sometimes');
});

it('StoreProposalRequest tem rules() obrigando contact_id + subject + body', function () {
    $req = new StoreProposalRequest();
    $rules = $req->rules();
    expect($rules)->toHaveKeys(['contact_id', 'subject', 'body']);
    expect($rules['contact_id'])->toContain('required');
});

// ------------------------------------------------------------------
// D8 — Smoke: classes não-FormRequest também respondem
// ------------------------------------------------------------------

it('ProposalService::acceptedFields é fonte única de campos aceitos', function () {
    $svc = new ProposalService();
    $fields = $svc->acceptedFields();
    expect($fields)->toBeArray()->not->toBeEmpty();
    expect($fields)->toHaveCount(5); // subject/body/contact_id/cc/bcc
});

it('CrmLeadRepository é stateless (sem propriedades injetadas)', function () {
    $repo = new CrmLeadRepository();
    $reflection = new ReflectionClass($repo);
    $properties = $reflection->getProperties();
    expect($properties)->toBeEmpty();
});

// ------------------------------------------------------------------
// Wave 18 RETRY — Δ D4: CrmLeadService novo
// ------------------------------------------------------------------

it('CrmLeadService expõe createLead/convertToCustomer/acceptedFields/repository', function () {
    $svc = new CrmLeadService();
    expect(method_exists($svc, 'createLead'))->toBeTrue();
    expect(method_exists($svc, 'convertToCustomer'))->toBeTrue();
    expect(method_exists($svc, 'acceptedFields'))->toBeTrue();
    expect(method_exists($svc, 'repository'))->toBeTrue();
});

it('CrmLeadService.acceptedFields contém campos canônicos lead', function () {
    $svc = new CrmLeadService();
    $fields = $svc->acceptedFields();
    expect($fields)->toContain('first_name', 'email', 'mobile', 'crm_source', 'crm_life_stage');
});

it('CrmLeadService recebe Repository opcional (DI-friendly)', function () {
    $repo = new CrmLeadRepository();
    $svc = new CrmLeadService($repo);
    expect($svc->repository())->toBe($repo);
});

// ------------------------------------------------------------------
// Wave 18 RETRY — Δ D8: UpdateCallLogRequest + StoreCrmContactRequest
// ------------------------------------------------------------------

it('UpdateCallLogRequest aceita sometimes em todos os campos (PATCH parcial)', function () {
    $req = new UpdateCallLogRequest();
    $rules = $req->rules();
    expect($rules['start_time'])->toContain('sometimes');
    expect($rules['end_time'])->toContain('sometimes', 'after_or_equal:start_time');
    expect($rules['duration'])->toContain('sometimes', 'integer', 'min:0', 'max:86400');
    expect($rules['call_type'])->toContain('sometimes');
});

it('StoreCrmContactRequest exige first_name + type whitelist lead|customer', function () {
    $req = new StoreCrmContactRequest();
    $rules = $req->rules();
    expect($rules['first_name'])->toContain('required');
    expect($rules['type'])->toContain('required');
    $typeRule = collect($rules['type'])->first(fn ($r) => str_starts_with((string) $r, 'in:'));
    expect($typeRule)->toContain('lead', 'customer');
});

it('StoreCrmContactRequest mensagens PT-BR', function () {
    $req = new StoreCrmContactRequest();
    $msgs = $req->messages();
    expect($msgs)->toHaveKeys(['first_name.required', 'type.in', 'email.email']);
    expect($msgs['first_name.required'])->toContain('obrigatório');
});
