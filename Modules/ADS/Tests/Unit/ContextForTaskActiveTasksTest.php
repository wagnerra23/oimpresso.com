<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ADS\Services\ContextForTaskService;
use Modules\ADS\Services\DecisionLinksService;
use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\UserScopeService;

uses(Tests\TestCase::class);

beforeEach(function () {
    // era-sqlite: cria schema manual (sqlite-friendly). No MySQL persistente do nightly
    // isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é na lane
    // sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
});

// US-COPI-077 — ContextForTaskService consome mcp_tasks no lugar de CURRENT.md.
// Testes isolam o método novo (buildActiveTasks) via Reflection pra não
// depender das outras tabelas mcp_* que buildContext() consulta.

function adsServiceInstance(): ContextForTaskService
{
    return new ContextForTaskService(
        scope: app(UserScopeService::class),
        policy: app(PolicyEngine::class),
        links: app(DecisionLinksService::class),
    );
}

function callBuildActiveTasks(?string $domain): array
{
    $service = adsServiceInstance();
    $ref = new ReflectionMethod($service, 'buildActiveTasks');
    $ref->setAccessible(true);
    return $ref->invoke($service, $domain);
}

function createMcpTasksSchema(): void
{
    Schema::create('mcp_tasks', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('module', 60);
        $t->string('title', 255);
        $t->string('status', 20)->default('todo');
        $t->string('owner', 60)->nullable();
        $t->string('sprint', 40)->nullable();
        $t->string('priority', 4)->default('p2');
        $t->timestamps();
    });
}

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_tasks');
});

it('chave de output é active_tasks_cycle (não current_cycle_focus)', function () {
    $service = adsServiceInstance();
    $ref = new ReflectionClass($service);

    expect($ref->hasMethod('buildActiveTasks'))->toBeTrue();
    expect($ref->hasMethod('buildCycleFocus'))->toBeFalse(); // método antigo removido
});

it('graceful quando mcp_tasks não existe (degrada sem quebrar)', function () {
    Schema::dropIfExists('mcp_tasks');

    $result = callBuildActiveTasks(null);

    expect($result['active'])->toBe([]);
    expect($result['completed'])->toBe([]);
    expect($result['source'])->toContain('não existe');
});

it('source NUNCA menciona CURRENT.md (US-COPI-077 contrato)', function () {
    Schema::dropIfExists('mcp_tasks');
    expect(callBuildActiveTasks(null)['source'])->not->toContain('CURRENT.md');

    createMcpTasksSchema();
    expect(callBuildActiveTasks(null)['source'])->not->toContain('CURRENT.md');
});

it('lê tasks ativas (todo/doing/review) e separa concluídas (done)', function () {
    createMcpTasksSchema();

    DB::table('mcp_tasks')->insert([
        ['task_id' => 'US-X-001', 'module' => 'Copiloto', 'title' => 'A fazer',  'status' => 'todo',   'priority' => 'p1', 'sprint' => 'W19', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-X-002', 'module' => 'Copiloto', 'title' => 'Pronto',   'status' => 'done',   'priority' => 'p2', 'sprint' => 'W18', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-X-003', 'module' => 'Copiloto', 'title' => 'Em curso', 'status' => 'doing',  'priority' => 'p0', 'sprint' => 'W19', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-X-004', 'module' => 'Copiloto', 'title' => 'Em review','status' => 'review', 'priority' => 'p1', 'sprint' => 'W19', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-X-005', 'module' => 'Copiloto', 'title' => 'Cancelada','status' => 'cancelled','priority' => 'p3', 'sprint' => 'W17', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $result = callBuildActiveTasks(null);

    $activeIds = collect($result['active'])->pluck('task_id')->all();
    $completedIds = collect($result['completed'])->pluck('task_id')->all();

    expect($activeIds)->toContain('US-X-001', 'US-X-003', 'US-X-004');
    expect($activeIds)->not->toContain('US-X-002', 'US-X-005');
    expect($completedIds)->toContain('US-X-002');
});

it('filtra por domain quando informado (case-insensitive)', function () {
    createMcpTasksSchema();

    DB::table('mcp_tasks')->insert([
        ['task_id' => 'US-COPI-A', 'module' => 'Copiloto', 'title' => 'Cop',  'status' => 'todo',  'priority' => 'p1', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-NFSE-A', 'module' => 'NFSe',     'title' => 'NFSe', 'status' => 'doing', 'priority' => 'p0', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $resultNFSe = callBuildActiveTasks('NFSe');
    $idsNFSe = collect($resultNFSe['active'])->pluck('task_id')->all();
    expect($idsNFSe)->toContain('US-NFSE-A');
    expect($idsNFSe)->not->toContain('US-COPI-A');

    // Case insensitive: 'nfse' minúsculo também filtra
    $resultLower = callBuildActiveTasks('nfse');
    $idsLower = collect($resultLower['active'])->pluck('task_id')->all();
    expect($idsLower)->toContain('US-NFSE-A');
});
