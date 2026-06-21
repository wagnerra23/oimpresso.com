<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Console\Commands\PlanDriftCommand;
use Modules\Jana\Entities\Mcp\McpTask;

uses(Tests\TestCase::class);

/**
 * jana:plan-drift (ADR 0294 Onda 2) — drift entre status declarado do plano e a
 * realidade das tasks MCP (parent_plan).
 *
 * Os testes de `classifyDrift` (regra pura) + `readStatusVivo` (só FS) rodam em
 * qualquer driver. Os que tocam mcp_tasks são sqlite-only (mesma disciplina do
 * McpTasksHealthCheckCommandTest: no MySQL persistente do nightly, criar/dropar
 * mcp_tasks corromperia os testes irmãos — lever do floor SDD).
 */

// ── Regra pura (sem FS/DB) ────────────────────────────────────────────────────

$counts = fn (array $o = []) => array_merge(PlanDriftCommand::emptyCounts(), $o);

it('classifyDrift: em-execução com 0 tasks = 🔴 ligação fantasma', function () use ($counts) {
    $f = PlanDriftCommand::classifyDrift('em-execução', $counts(['total' => 0]));
    expect($f)->not->toBeNull()
        ->and($f['level'])->toBe('fail')
        ->and($f['issue'])->toContain('fantasma');
});

it('classifyDrift: em-execução sem todo/doing = 🟡 parou?', function () use ($counts) {
    $f = PlanDriftCommand::classifyDrift('em-execucao', $counts(['total' => 3, 'open' => 0, 'moving' => 0, 'done' => 3]));
    expect($f['level'])->toBe('warn')
        ->and($f['issue'])->toContain('parou');
});

it('classifyDrift: concluído com tasks abertas = 🟡', function () use ($counts) {
    $f = PlanDriftCommand::classifyDrift('concluído', $counts(['total' => 2, 'open' => 1, 'moving' => 1]));
    expect($f['level'])->toBe('warn')
        ->and($f['issue'])->toContain('aberta');
});

it('classifyDrift: proposto com tasks abertas = 🟡 cruzou a membrana', function () use ($counts) {
    $f = PlanDriftCommand::classifyDrift('proposto', $counts(['total' => 1, 'open' => 1, 'moving' => 1]));
    expect($f['level'])->toBe('warn')
        ->and($f['issue'])->toContain('membrana');
});

it('classifyDrift: em-execução com movimento real = sem achado', function () use ($counts) {
    $f = PlanDriftCommand::classifyDrift('em-execução', $counts(['total' => 4, 'open' => 3, 'moving' => 2, 'done' => 1]));
    expect($f)->toBeNull();
});

it('classifyDrift: concluído sem tasks abertas = sem achado', function () use ($counts) {
    $f = PlanDriftCommand::classifyDrift('concluído', $counts(['total' => 3, 'done' => 3]));
    expect($f)->toBeNull();
});

// ── readStatusVivo (só FS) ─────────────────────────────────────────────────────

it('readStatusVivo extrai status + parent_plan do bloco (aceita = e :, ignora markdown)', function () {
    $dir = sys_get_temp_dir() . '/plan-drift-' . getmypid();
    @mkdir($dir, 0777, true);
    $file = $dir . '/plano.md';
    file_put_contents($file, <<<'MD'
        # Plano X

        ## Status vivo
        status: em-execução
        owner: W
        cycle: CYCLE-07 · execução: **parent_plan=`meu-plano-slug`**

        ## Outra seção
        status: proposto
        MD);

    $cmd = app(PlanDriftCommand::class);
    [$status, $slug] = $cmd->readStatusVivo($file);

    expect($status)->toBe('em-execução')
        ->and($slug)->toBe('meu-plano-slug');

    @unlink($file);
    @rmdir($dir);
});

it('readStatusVivo devolve [null,null] quando não há bloco Status vivo', function () {
    $dir = sys_get_temp_dir() . '/plan-drift-noblk-' . getmypid();
    @mkdir($dir, 0777, true);
    $file = $dir . '/plano.md';
    file_put_contents($file, "# Só um doc\n\nver `## Status vivo` abaixo (menção inline, não é heading)\n");

    [$status, $slug] = app(PlanDriftCommand::class)->readStatusVivo($file);
    expect($status)->toBeNull()->and($slug)->toBeNull();

    @unlink($file);
    @rmdir($dir);
});

// ── mcp_tasks (sqlite-only) ────────────────────────────────────────────────────

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only.');
    }

    Schema::dropIfExists('mcp_tasks');
    Schema::create('mcp_tasks', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('identifier', 24)->nullable();
        $t->unsignedBigInteger('project_id')->nullable();
        $t->unsignedBigInteger('epic_id')->nullable();
        $t->unsignedBigInteger('cycle_id')->nullable();
        $t->unsignedBigInteger('component_id')->nullable();
        $t->unsignedBigInteger('parent_task_id')->nullable();
        $t->string('module', 60);
        $t->string('title', 255);
        $t->text('description')->nullable();
        $t->string('status', 24)->default('todo');
        $t->string('type', 24)->default('story');
        $t->string('owner', 60)->nullable();
        $t->string('sprint', 40)->nullable();
        $t->string('priority', 8)->nullable();
        $t->decimal('estimate_h', 5, 1)->nullable();
        $t->decimal('story_points', 5, 1)->nullable();
        $t->string('estimate_unit', 16)->default('points');
        $t->decimal('estimate_value', 8, 2)->nullable();
        $t->timestamp('due_date')->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('completed_at')->nullable();
        $t->text('labels')->nullable();
        $t->text('custom_fields')->nullable();
        $t->text('blocked_by')->nullable();
        $t->string('source_path', 500)->default('test');
        $t->string('source_git_sha', 40)->nullable();
        $t->timestamp('parsed_at')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') === 'sqlite') {
        Schema::dropIfExists('mcp_tasks');
    }
});

function makeTask(string $taskId, string $status, array $opts = []): McpTask
{
    // withoutEvents: pula o LogsActivity (escreveria em activity_log, que a lane
    // sqlite :memory: não migra). O comando só LÊ tasks — logging é irrelevante aqui.
    return McpTask::withoutEvents(fn () => McpTask::create(array_merge([
        'task_id' => $taskId,
        'identifier' => $taskId,
        'module' => 'TEST',
        'title' => "fake {$taskId}",
        'status' => $status,
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'test',
        'parsed_at' => now(),
    ], $opts)));
}

it('resolveParentPlan respeita a precedência custom_fields > label > description', function () {
    $cmd = app(PlanDriftCommand::class);

    $cf = makeTask('US-A-1', 'todo', ['custom_fields' => ['parent_plan' => 'via-custom']]);
    expect($cmd->resolveParentPlan($cf))->toBe('via-custom');

    $lbl = makeTask('US-A-2', 'todo', ['labels' => ['plano-perdido', 'parent_plan:via-label']]);
    expect($cmd->resolveParentPlan($lbl))->toBe('via-label');

    $desc = makeTask('US-A-3', 'todo', ['description' => "Recuperada.\n`parent_plan: via-desc` · labels: x"]);
    expect($cmd->resolveParentPlan($desc))->toBe('via-desc');

    $none = makeTask('US-A-4', 'todo');
    expect($cmd->resolveParentPlan($none))->toBeNull();
});

it('taskCountsByParentPlan agrega por slug e classifica os estados', function () {
    makeTask('US-B-1', 'doing', ['custom_fields' => ['parent_plan' => 'plano-z']]);
    makeTask('US-B-2', 'todo', ['custom_fields' => ['parent_plan' => 'plano-z']]);
    makeTask('US-B-3', 'done', ['custom_fields' => ['parent_plan' => 'plano-z']]);
    makeTask('US-B-4', 'cancelled', ['custom_fields' => ['parent_plan' => 'plano-z']]);

    $counts = app(PlanDriftCommand::class)->taskCountsByParentPlan();

    expect($counts)->toHaveKey('plano-z');
    expect($counts['plano-z'])->toMatchArray([
        'total' => 4, 'open' => 2, 'moving' => 2, 'done' => 1, 'cancelled' => 1,
    ]);
});

it('detecta drift end-to-end via --json (em-execução parado + órfão reverso) e pula gracioso sem links', function () {
    // Índice + plano com bloco Status vivo num tmpdir (realpath relativo ao índice).
    $dir = sys_get_temp_dir() . '/plan-drift-e2e-' . getmypid();
    @mkdir($dir, 0777, true);
    file_put_contents($dir . '/plano-z.md', "# Z\n\n## Status vivo\nstatus: em-execução\ncycle: CYCLE-07 · execução: parent_plan=plano-z\n");
    $index = $dir . '/PLANS-INDEX.md';
    file_put_contents($index, "# Índice\n\n| Plano | Status |\n|---|---|\n| [Z](./plano-z.md) | em-execução |\n");

    // SKIP gracioso: nenhuma task com parent_plan ainda (MCP offline / não materializado).
    makeTask('US-NOLINK', 'doing');
    Artisan::call('jana:plan-drift', ['--index' => $index, '--json' => true]);
    $skip = json_decode(Artisan::output(), true);
    expect($skip['ok'])->toBeTrue()->and($skip['skipped'] ?? false)->toBeTrue();

    // Agora liga tasks: plano-z só tem done (em-execução mas parou) + tasks órfãs noutro slug.
    makeTask('US-Z-1', 'done', ['custom_fields' => ['parent_plan' => 'plano-z']]);
    makeTask('US-Z-2', 'done', ['custom_fields' => ['parent_plan' => 'plano-z']]);
    makeTask('US-ORF-1', 'todo', ['custom_fields' => ['parent_plan' => 'slug-sem-plano']]);

    Artisan::call('jana:plan-drift', ['--index' => $index, '--json' => true]);
    $out = json_decode(Artisan::output(), true);

    expect($out['planos'])->toBe(1)
        ->and($out['linked'])->toBe(2); // plano-z + slug-sem-plano

    $issues = collect($out['findings'])->pluck('issue')->implode(' || ');
    expect($issues)->toContain('parou')          // plano-z em-execução só com done
        ->and($issues)->toContain('órfão reverso'); // slug-sem-plano

    array_map('unlink', glob($dir . '/*'));
    @rmdir($dir);
});
