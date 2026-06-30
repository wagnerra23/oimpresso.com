<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Database\Seeders\ForjaDemoTicketsSeeder;
use Modules\TeamMcp\Services\Forja\ForjaBacklogService;

uses(Tests\TestCase::class);

/**
 * Forja · aba Backlog — ForjaBacklogService::build().
 *
 * Cobertura (Onda Forja — código novo sem teste):
 *   1. build($projectId) com FORJA semeado → lista de issues; cada item carrega
 *      display_id/title/tipo/fase/papel/onda/modulo/prioridade/status.
 *   2. build(null) → [] (sem dado fantasma; o front mostra empty-state).
 *
 * Padrão era-sqlite (espelha AcceptanceRefTest): schema sintético + activitylog OFF.
 * Assertions ROBUSTAS (chaves/tipos/contagem), nunca valores frágeis.
 *
 * @see Modules\TeamMcp\Services\Forja\ForjaBacklogService
 * @see memory/decisions/0070-jira-style-task-management.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético incompatível com MySQL persistente (floor SDD).');
    }

    config(['activitylog.enabled' => false]);

    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_jira_projects');

    Schema::create('mcp_jira_projects', function ($t) {
        $t->bigIncrements('id');
        $t->string('key', 20)->unique();
        $t->string('name', 120)->nullable();
        $t->text('description')->nullable();
        $t->string('status', 20)->default('active');
        $t->string('icon', 40)->nullable();
        $t->unsignedInteger('next_task_number')->default(1);
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('identifier', 40)->nullable();
        $t->unsignedBigInteger('project_id')->nullable();
        $t->string('module', 60)->nullable();
        $t->string('title', 255)->nullable();
        $t->text('description')->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('type', 20)->nullable();
        $t->string('owner', 60)->nullable();
        $t->string('priority', 10)->nullable();
        $t->json('custom_fields')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return; // era-sqlite: não dropar tabela compartilhada no MySQL persistente (US-GOV-021)
    }
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_jira_projects');
});

// -------------------------------------------------------------------------
// 1. build() com FORJA semeado — SHAPE da lista
// -------------------------------------------------------------------------

it('build() devolve lista de issues com o shape canônico do backlog', function () {
    (new ForjaDemoTicketsSeeder())->run();
    $projectId = (int) DB::table('mcp_jira_projects')->where('key', 'FORJA')->value('id');

    $rows = app(ForjaBacklogService::class)->build($projectId);

    expect($rows)->toBeArray()->not->toBeEmpty();

    // O seeder cria 14 tickets (3 triagem + 11 triadas), todos project=FORJA.
    expect(count($rows))->toBe(14);

    foreach ($rows as $row) {
        expect($row)->toHaveKeys([
            'display_id', 'title', 'tipo', 'fase', 'papel',
            'onda', 'modulo', 'prioridade', 'status',
        ]);
        // Tipos: campos sempre-string vs projeções nullable.
        expect($row['display_id'])->toBeString()->not->toBe('');
        expect($row['title'])->toBeString();
        expect($row['prioridade'])->toBeString(); // fallback p2, nunca null
        expect($row['status'])->toBeString();
    }
});

it('build() projeta custom_fields (tipo/fase/papel/onda) de issue triada', function () {
    (new ForjaDemoTicketsSeeder())->run();
    $projectId = (int) DB::table('mcp_jira_projects')->where('key', 'FORJA')->value('id');

    $rows = app(ForjaBacklogService::class)->build($projectId);

    // FORJA-142 é uma issue triada com fase=F1, onda=V1.1, papel=CC, tipo=Tela.
    $f142 = collect($rows)->firstWhere('display_id', 'FORJA-142');
    expect($f142)->not->toBeNull('FORJA-142 deveria aparecer no backlog');
    expect($f142['fase'])->toBe('F1');
    expect($f142['onda'])->toBe('V1.1');
    expect($f142['papel'])->toBe('CC');
    expect($f142['tipo'])->toBe('Tela');
    expect($f142['prioridade'])->toBe('p0');
});

it('build() aplica fallback p2 quando a issue não tem prioridade (badge não quebra)', function () {
    (new ForjaDemoTicketsSeeder())->run();
    $projectId = (int) DB::table('mcp_jira_projects')->where('key', 'FORJA')->value('id');

    $rows = app(ForjaBacklogService::class)->build($projectId);

    // FORJA-150 é proposta em triagem (priority null no seeder).
    $f150 = collect($rows)->firstWhere('display_id', 'FORJA-150');
    expect($f150)->not->toBeNull();
    expect($f150['prioridade'])->toBe('p2', 'prioridade null deve cair no fallback p2');
});

// -------------------------------------------------------------------------
// 2. build(null) — sem project FORJA → lista vazia
// -------------------------------------------------------------------------

it('build(null) → [] (sem dado fantasma)', function () {
    expect(app(ForjaBacklogService::class)->build(null))->toBe([]);
});

it('build(0) → [] (projectId falsy tratado como ausente)', function () {
    expect(app(ForjaBacklogService::class)->build(0))->toBe([]);
});
