<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Console\Commands\McpTasksOrphansCommand;

uses(Tests\TestCase::class);

/**
 * Bonus do fix do incidente US-RB-052 (2026-06-20): detector de US-* no DB que
 * não constam em nenhum SPEC.md (órfãs) — a classe de row que o reuso de ID
 * sobrescreve em silêncio via webhook sync (ADR 0144).
 */

function orphanEnsureMcpTasksTable(): void
{
    if (Schema::hasTable('mcp_tasks')) {
        return;
    }
    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('identifier', 40)->nullable();
        $t->string('module', 80)->nullable();
        $t->string('title', 255)->nullable();
        $t->text('description')->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('owner', 60)->nullable();
        $t->string('sprint', 40)->nullable();
        $t->string('priority', 8)->nullable();
        $t->string('source_path', 500)->nullable();
        $t->timestamp('parsed_at')->nullable();
        $t->timestamps();
    });
}

function orphanWriteSpec(string $module, string $body): string
{
    $dir = base_path("memory/requisitos/{$module}");
    if (! is_dir($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    $path = $dir . '/SPEC.md';
    file_put_contents($path, $body);
    return $path;
}

function orphanSeed(string $taskId, string $status = 'todo'): void
{
    DB::table('mcp_tasks')->insert([
        'task_id' => $taskId,
        'module' => '__TestOrphanCmd',
        'title' => $taskId,
        'status' => $status,
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    orphanEnsureMcpTasksTable();
    DB::table('mcp_tasks')->where('task_id', 'LIKE', 'US-ZZORPHCMD-%')->delete();
});

afterEach(function () {
    DB::table('mcp_tasks')->where('task_id', 'LIKE', 'US-ZZORPHCMD-%')->delete();
    $dir = base_path('memory/requisitos/__TestOrphanCmd');
    if (is_dir($dir)) {
        File::deleteDirectory($dir);
    }
});

it('flagga US-* no DB ausente do SPEC e ignora as declaradas', function () {
    orphanWriteSpec('__TestOrphanCmd', "# SPEC\n\n### US-ZZORPHCMD-001 · No SPEC\n");

    orphanSeed('US-ZZORPHCMD-001');             // declarada no SPEC → não órfã
    orphanSeed('US-ZZORPHCMD-099');             // órfã (nunca no SPEC)
    orphanSeed('US-ZZORPHCMD-050', 'cancelled'); // fechada → oculta por default

    $orfas = (new McpTasksOrphansCommand())->detectarOrfas('__TestOrphanCmd', false);
    $ids = array_map(fn ($o) => $o['task_id'], $orfas);

    expect($ids)->toContain('US-ZZORPHCMD-099')
        ->and($ids)->not->toContain('US-ZZORPHCMD-001')
        ->and($ids)->not->toContain('US-ZZORPHCMD-050');
});

it('--include-closed revela órfãs done/cancelled', function () {
    orphanWriteSpec('__TestOrphanCmd', "# SPEC\n\n### US-ZZORPHCMD-001 · No SPEC\n");
    orphanSeed('US-ZZORPHCMD-050', 'cancelled');

    $orfas = (new McpTasksOrphansCommand())->detectarOrfas('__TestOrphanCmd', true);

    expect(array_map(fn ($o) => $o['task_id'], $orfas))->toContain('US-ZZORPHCMD-050');
});

it('não flagga identifier ad-hoc Linear-style (COPI-123)', function () {
    orphanWriteSpec('__TestOrphanCmd', "# SPEC sem stories ainda\n");

    orphanSeed('US-ZZORPHCMD-077');  // US-* órfã → flagga

    // Linear-style: task_id não casa US-XX-NNN → DB-only por design, não é órfã.
    DB::table('mcp_tasks')->insert([
        'task_id' => 'COPI-123',
        'module' => '__TestOrphanCmd',
        'title' => 'ad-hoc Linear-style',
        'status' => 'todo',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ids = array_map(
        fn ($o) => $o['task_id'],
        (new McpTasksOrphansCommand())->detectarOrfas('__TestOrphanCmd', false),
    );

    expect($ids)->toContain('US-ZZORPHCMD-077')
        ->and($ids)->not->toContain('COPI-123');

    DB::table('mcp_tasks')->where('task_id', 'COPI-123')->delete();
});

it('exit SUCCESS quando não há órfã, FAILURE quando há', function () {
    orphanWriteSpec('__TestOrphanCmd', "# SPEC\n\n### US-ZZORPHCMD-001 · No SPEC\n");
    orphanSeed('US-ZZORPHCMD-001'); // só a declarada → zero órfã

    $this->artisan('mcp:tasks:orphans', ['--module' => '__TestOrphanCmd'])
        ->assertExitCode(0);

    orphanSeed('US-ZZORPHCMD-088'); // agora há uma órfã

    $this->artisan('mcp:tasks:orphans', ['--module' => '__TestOrphanCmd'])
        ->assertExitCode(1);
});
