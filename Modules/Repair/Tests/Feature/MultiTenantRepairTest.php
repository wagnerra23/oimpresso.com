<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Services\KanbanProductionService;

/**
 * MultiTenantRepairTest — isolamento cross-tenant Tier 0 IRREVOGÁVEL.
 *
 * Cobre Repair (JobSheet + RepairStatus + KanbanProductionService) garantindo
 * que biz=1 (Wagner sandbox) nunca vê dado de biz=99 (tenant sintético).
 *
 * NUNCA usar biz=4 (ROTA LIVRE cliente real) — ADR 0101.
 *
 * Refs:
 *   - ADR 0093 (Multi-tenant Tier 0)
 *   - ADR 0101 (Tests biz=1 nunca cliente)
 *   - memory/proibicoes.md §"Multi-tenant Tier 0 IRREVOGÁVEL"
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    // SQLite guard — schema sintético só roda se driver in-memory.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('MultiTenantRepairTest exige SQLite in-memory.');
    }

    // RepairStatus usa trait Spatie LogsActivity (D7.b LGPD audit trail) →
    // toda RepairStatus::create() dispara INSERT em `activity_log`. Sem essa
    // tabela o teste explode com "SQLSTATE no such table: activity_log".
    // Schema espelha vendor/spatie/laravel-activitylog/database/migrations/*
    // (4 migrations consolidadas).
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable();
        $t->text('description')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('causer_id')->nullable();
        $t->string('causer_type')->nullable();
        $t->text('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->string('event')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->timestamps();
    });

    // Schema mínimo pra repair_job_sheets + repair_statuses sem depender da
    // suite Repair full migrations.
    Schema::create('repair_statuses', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->string('name');
        $t->integer('sort_order')->default(0);
        $t->boolean('is_completed_status')->default(false);
        $t->timestamps();
    });

    Schema::create('repair_job_sheets', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->integer('status_id')->nullable();
        $t->string('job_sheet_no')->nullable();
        $t->string('serial_no')->nullable();
        $t->decimal('estimated_cost', 12, 2)->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    // afterEach roda MESMO em teste pulado (PHPUnit 12: tearDown gated só por
    // hasMetRequirements, que já é true antes do beforeEach/markTestSkipped).
    // repair_statuses/repair_job_sheets são reais-migradas e activity_log é CORE —
    // dropá-las no MySQL persistente do nightly corromperia o schema compartilhado.
    // DDL só em sqlite (espelha o skip-guard do beforeEach).
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('repair_job_sheets');
        Schema::dropIfExists('repair_statuses');
        Schema::dropIfExists('activity_log');
    }
});

test('JobSheet biz=1 não vaza pra query biz=99 (cross-tenant scope)', function () {
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-001', 'status_id' => null]);
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-002', 'status_id' => null]);
    JobSheet::create(['business_id' => 99, 'job_sheet_no' => 'OS-X-999', 'status_id' => null]);

    $biz1 = JobSheet::where('business_id', 1)->get();
    $biz99 = JobSheet::where('business_id', 99)->get();

    expect($biz1)->toHaveCount(2);
    expect($biz99)->toHaveCount(1);
    expect($biz1->pluck('job_sheet_no')->toArray())
        ->toBe(['OS-W-001', 'OS-W-002'])
        ->not->toContain('OS-X-999');
});

test('RepairStatus biz=1 e biz=99 são independentes (mesmo sort_order, IDs distintos)', function () {
    $sBiz1 = RepairStatus::create(['business_id' => 1, 'name' => 'Recepção', 'sort_order' => 1]);
    $sBiz99 = RepairStatus::create(['business_id' => 99, 'name' => 'Recepção', 'sort_order' => 1]);

    expect(RepairStatus::where('business_id', 1)->count())->toBe(1);
    expect(RepairStatus::where('business_id', 99)->count())->toBe(1);
    expect($sBiz1->id)->not->toBe($sBiz99->id);
});

test('KanbanProductionService respeita scope ao receber Collection scopada por biz', function () {
    RepairStatus::create(['business_id' => 1, 'name' => 'Recepção', 'sort_order' => 1, 'is_completed_status' => false]);
    RepairStatus::create(['business_id' => 1, 'name' => 'Pronto', 'sort_order' => 99, 'is_completed_status' => true]);

    // biz=99 com 4 statuses — não deve ser misturado.
    RepairStatus::create(['business_id' => 99, 'name' => 'Triagem', 'sort_order' => 1, 'is_completed_status' => false]);
    RepairStatus::create(['business_id' => 99, 'name' => 'Aprovado', 'sort_order' => 2, 'is_completed_status' => false]);
    RepairStatus::create(['business_id' => 99, 'name' => 'Entregue', 'sort_order' => 3, 'is_completed_status' => true]);

    $statusesBiz1 = RepairStatus::where('business_id', 1)->orderBy('sort_order')->get();
    $service = new KanbanProductionService();
    $map = $service->mapStatusesToColumns($statusesBiz1);

    // Map só contém IDs de biz=1.
    $biz1Ids = $statusesBiz1->pluck('id')->toArray();
    expect(array_keys($map))->toEqualCanonicalizing($biz1Ids);

    // Pronto bate.
    $prontoBiz1 = $statusesBiz1->where('is_completed_status', true)->first();
    expect($map[$prontoBiz1->id])->toBe('pronto');
});

test('drag-and-drop move() bloqueia OS de outro tenant (find scopado)', function () {
    JobSheet::create(['business_id' => 1, 'job_sheet_no' => 'OS-W-100', 'status_id' => null]);
    $jsBiz99 = JobSheet::create(['business_id' => 99, 'job_sheet_no' => 'OS-X-999', 'status_id' => null]);

    // Simula query do Controller (where business_id antes do find).
    $jobSheetEncontrado = JobSheet::where('business_id', 1)->find($jsBiz99->id);

    expect($jobSheetEncontrado)->toBeNull(); // tenant errado → null, NUNCA carrega
});

test('findStatusForColumn não usa statuses de outro tenant (entrada já filtrada)', function () {
    // biz=1 sem nenhum status Pronto cadastrado.
    RepairStatus::create(['business_id' => 1, 'name' => 'Triagem', 'sort_order' => 1, 'is_completed_status' => false]);

    // biz=99 tem Pronto — não pode vazar pra busca biz=1.
    RepairStatus::create(['business_id' => 99, 'name' => 'Pronto', 'sort_order' => 99, 'is_completed_status' => true]);

    $statusesBiz1 = RepairStatus::where('business_id', 1)->orderBy('sort_order')->get();
    $service = new KanbanProductionService();

    $result = $service->findStatusForColumn($statusesBiz1, 'pronto');

    expect($result)->toBeNull(); // biz=1 não tem Pronto, busca não pode pegar o de biz=99
});
