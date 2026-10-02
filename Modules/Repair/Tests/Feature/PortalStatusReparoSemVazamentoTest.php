<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portal público de status do reparo (`POST /post-repair-status`) — não pode
 * devolver OS sem um critério de busca válido. Tier 0 (ADR 0093).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * O DEFEITO QUE ESTE TESTE TRAVA (medido no main 13bc079894, 2026-10-02)
 * ─────────────────────────────────────────────────────────────────────────────
 * A rota é pública (só `throttle:30,1`, sem `auth`). O `JobSheet` tem o
 * `ScopeByBusiness`, mas ele sai na primeira linha quando não há usuário logado
 * (`if (! auth()->check()) return;`). E o controller só aplicava `where` quando
 * `search_type` era um dos três conhecidos: com `search_type` vazio ou qualquer
 * outro valor a consulta saía SEM filtro, devolvendo todas as OS de todas as
 * empresas, com o histórico de atividades (quem mudou o status e a nota).
 *
 * Antes → depois, com uma OS na empresa 1 e outra na empresa 2:
 *     search_type vazio      antes: success=true, as 2 OS na resposta · depois: success=false, nenhuma
 *     search_type desconhecido antes: idem                            · depois: idem
 *     job_sheet_no = OS-A    antes e depois: success=true, só a OS-A (controle positivo)
 *
 * O que este teste NÃO cobre: a busca por um número válido continua varrendo
 * todas as empresas (o portal não sabe de qual empresa é o cliente). Resolver
 * isso muda a URL do portal — decisão do dono, fora deste conserto.
 *
 * Schema mínimo em SQLite, no mesmo idioma do MultiTenantRepairTest (a lane
 * modules-pest roda Repair em SQLite in-memory).
 */
uses(Tests\TestCase::class);

const PORTAL_TABELAS = [
    'activity_log', 'repair_job_sheets', 'transactions', 'contacts',
    'repair_statuses', 'brands', 'repair_device_models', 'categories',
];

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('PortalStatusReparoSemVazamentoTest cria schema mínimo — só em SQLite in-memory.');
    }

    foreach (PORTAL_TABELAS as $tabela) {
        Schema::dropIfExists($tabela);
    }

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
    Schema::create('repair_job_sheets', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->integer('contact_id');
        $t->integer('status_id')->nullable();
        $t->integer('brand_id')->nullable();
        $t->integer('device_model_id')->nullable();
        $t->integer('device_id')->nullable();
        $t->string('job_sheet_no')->nullable();
        $t->string('serial_no')->nullable();
        $t->dateTime('delivery_date')->nullable();
        $t->timestamps();
    });
    Schema::create('transactions', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->integer('repair_job_sheet_id')->nullable();
        $t->string('invoice_no')->nullable();
        $t->timestamps();
    });
    Schema::create('contacts', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->string('name')->nullable();
        $t->string('mobile')->nullable();
        $t->timestamps();
    });
    Schema::create('repair_statuses', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->string('name');
        $t->string('color')->nullable();
        $t->timestamps();
    });
    foreach (['brands', 'repair_device_models', 'categories'] as $tabela) {
        Schema::create($tabela, function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }

    $agora = now();
    foreach ([[1, 'OS-A', '48999990001'], [2, 'OS-B', '48999990002']] as [$biz, $os, $celular]) {
        $contato = DB::table('contacts')->insertGetId([
            'business_id' => $biz, 'name' => "Cliente {$os}", 'mobile' => $celular,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        DB::table('repair_job_sheets')->insert([
            'business_id' => $biz, 'contact_id' => $contato, 'job_sheet_no' => $os,
            'serial_no' => 'SERIE-COMUM', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (PORTAL_TABELAS as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
});

function portalConsultar(array $dados): array
{
    return test()->post('/post-repair-status', $dados, [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->assertOk()->json();
}

it('controle positivo: busca pelo nº da OS devolve só aquela OS', function () {
    $resposta = portalConsultar(['search_type' => 'job_sheet_no', 'search_number' => 'OS-A']);

    expect($resposta['success'] ?? null)->toBeTrue(
        'A busca válida não achou a OS-A — setup inválido, os contratos abaixo não medem nada.'
    );
    expect($resposta['repair_html'] ?? '')->toContain('OS-A');
    expect($resposta['repair_html'] ?? '')->not->toContain('OS-B');
});

it('Tier 0: search_type vazio não devolve OS nenhuma', function () {
    $resposta = portalConsultar(['search_type' => '', 'search_number' => '']);

    expect($resposta['success'] ?? null)->toBeFalse(
        'Sem critério de busca o portal devolveu OS — consulta sem filtro, todas as empresas.'
    );
    expect($resposta['repair_html'] ?? '')->not->toContain('OS-A');
    expect($resposta['repair_html'] ?? '')->not->toContain('OS-B');
});

it('Tier 0: search_type desconhecido não devolve OS nenhuma', function () {
    $resposta = portalConsultar(['search_type' => 'qualquer', 'search_number' => 'x']);

    expect($resposta['success'] ?? null)->toBeFalse(
        'Com search_type fora da lista o portal devolveu OS — consulta sem filtro.'
    );
    expect($resposta['repair_html'] ?? '')->not->toContain('OS-A');
});

it('Tier 0: só o nº de série, sem tipo de busca, não devolve OS', function () {
    $resposta = portalConsultar(['search_type' => '', 'search_number' => '', 'serial_no' => 'SERIE-COMUM']);

    expect($resposta['success'] ?? null)->toBeFalse(
        'Só com nº de série o portal devolveu OS de várias empresas.'
    );
    expect($resposta['repair_html'] ?? '')->not->toContain('OS-B');
});

it('tipo válido com número vazio não devolve OS', function () {
    $resposta = portalConsultar(['search_type' => 'job_sheet_no', 'search_number' => '   ']);

    expect($resposta['success'] ?? null)->toBeFalse();
});
