<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Console\Commands\McpTasksUnassignedCommand;

uses(Tests\TestCase::class);

/**
 * Sentinela US-INFRA-043 (furo #2 da auditoria 2026-06-21): US criada por
 * `tasks-create` nasce todo/unowned/sem-cycle → invisível no roadmap. Este comando
 * dá visibilidade. Espelha McpTasksOrphansCommandTest.
 */

function unassignedEnsureTable(): void
{
    if (Schema::hasTable('mcp_tasks')) {
        return;
    }
    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('module', 80)->nullable();
        $t->string('title', 255)->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('owner', 60)->nullable();
        $t->unsignedBigInteger('cycle_id')->nullable();
        $t->timestamp('parsed_at')->nullable();
        $t->timestamps();
    });
}

function unassignedSeed(string $taskId, array $attrs = []): void
{
    DB::table('mcp_tasks')->insert(array_merge([
        'task_id' => $taskId,
        'module' => '__TestUnassigned',
        'title' => $taskId,
        'status' => 'todo',
        'owner' => null,
        'cycle_id' => null,
        'parsed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs));
}

beforeEach(function () {
    unassignedEnsureTable();
    DB::table('mcp_tasks')->where('task_id', 'LIKE', 'US-ZZUNASSIGN-%')->delete();
});

afterEach(function () {
    DB::table('mcp_tasks')->where('task_id', 'LIKE', 'US-ZZUNASSIGN-%')->delete();
});

it('flagga todo sem cycle e/ou sem owner; ignora atribuída e fechada', function () {
    unassignedSeed('US-ZZUNASSIGN-001', ['cycle_id' => 10, 'owner' => 'wagner']);   // atribuída → não
    unassignedSeed('US-ZZUNASSIGN-002', ['cycle_id' => null, 'owner' => 'wagner']);  // falta cycle
    unassignedSeed('US-ZZUNASSIGN-003', ['cycle_id' => 10, 'owner' => null]);        // falta owner
    unassignedSeed('US-ZZUNASSIGN-004', ['cycle_id' => null, 'owner' => null]);      // ambos
    unassignedSeed('US-ZZUNASSIGN-005', ['cycle_id' => null, 'owner' => null, 'status' => 'done']); // fechada → não

    $byId = collect((new McpTasksUnassignedCommand())->detectarNaoAtribuidas('__TestUnassigned', 0))
        ->keyBy('task_id');

    expect($byId->keys()->all())
        ->toContain('US-ZZUNASSIGN-002', 'US-ZZUNASSIGN-003', 'US-ZZUNASSIGN-004')
        ->not->toContain('US-ZZUNASSIGN-001')
        ->not->toContain('US-ZZUNASSIGN-005');

    expect($byId['US-ZZUNASSIGN-002']['falta'])->toBe('cycle');
    expect($byId['US-ZZUNASSIGN-003']['falta'])->toBe('owner');
    expect($byId['US-ZZUNASSIGN-004']['falta'])->toBe('cycle+owner');
});

it('--days aplica carência (recém-criada não flagga)', function () {
    unassignedSeed('US-ZZUNASSIGN-010', ['created_at' => now()->subDays(10)]); // velha → flagga
    unassignedSeed('US-ZZUNASSIGN-011', ['created_at' => now()]);              // recém → carência

    $ids = array_map(
        fn ($i) => $i['task_id'],
        (new McpTasksUnassignedCommand())->detectarNaoAtribuidas('__TestUnassigned', 7),
    );

    expect($ids)->toContain('US-ZZUNASSIGN-010')
        ->and($ids)->not->toContain('US-ZZUNASSIGN-011');
});

it('advisory exit 0 sempre; --strict exit 1 só quando há não-atribuída', function () {
    unassignedSeed('US-ZZUNASSIGN-020', ['cycle_id' => 10, 'owner' => 'wagner']); // atribuída

    $this->artisan('mcp:tasks:unassigned', ['--module' => '__TestUnassigned'])->assertExitCode(0);
    $this->artisan('mcp:tasks:unassigned', ['--module' => '__TestUnassigned', '--strict' => true])->assertExitCode(0);

    unassignedSeed('US-ZZUNASSIGN-021'); // não-atribuída (todo, sem cycle, sem owner)

    $this->artisan('mcp:tasks:unassigned', ['--module' => '__TestUnassigned'])->assertExitCode(0);          // advisory
    $this->artisan('mcp:tasks:unassigned', ['--module' => '__TestUnassigned', '--strict' => true])->assertExitCode(1);
});
