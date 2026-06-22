<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;

uses(Tests\TestCase::class);

/**
 * G6 (porta de saída do loop) — endpoint /api/mcp/cycle-active.
 * Auth pelo token dedicado (copiloto.mcp.drift_token, sem RBAC), igual /version.
 * Pro cron do shipped-log descobrir cycle+janela sem depender do shipped-log anterior.
 *
 * @see Modules/TeamMcp/Http/Controllers/Mcp/HealthController.php (cicloAtivo)
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: schema manual — sqlite-only.');
    }
    config(['copiloto.mcp.drift_token' => 'test-drift-token']);

    Schema::dropIfExists('mcp_jira_projects');
    Schema::create('mcp_jira_projects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('key', 24)->unique();
        $t->string('name', 100)->nullable();
        $t->string('status', 16)->default('active');
        $t->unsignedInteger('next_task_number')->default(1);
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('mcp_cycles');
    Schema::create('mcp_cycles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('project_id');
        $t->string('key', 24);
        $t->string('name', 100)->nullable();
        $t->date('start_date');
        $t->date('end_date');
        $t->text('goal')->nullable();
        $t->string('status', 16)->default('planning');
        $t->text('retro')->nullable();
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['project_id', 'key']);
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }
    Schema::dropIfExists('mcp_cycles');
    Schema::dropIfExists('mcp_jira_projects');
});

it('401 sem token', function () {
    $this->getJson('/api/mcp/cycle-active')->assertStatus(401);
});

it('401 com token errado', function () {
    $this->withHeaders(['Authorization' => 'Bearer errado'])
        ->getJson('/api/mcp/cycle-active')->assertStatus(401);
});

it('retorna o cycle ATIVO com token (ignora closed)', function () {
    $p = McpProject::create(['key' => 'COPI', 'name' => 'T']);
    McpCycle::create(['project_id' => $p->id, 'key' => 'CYCLE-09', 'name' => 'Atual', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'status' => 'active']);
    McpCycle::create(['project_id' => $p->id, 'key' => 'CYCLE-08', 'name' => 'Velho', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'status' => 'closed']);

    $this->withHeaders(['Authorization' => 'Bearer test-drift-token'])
        ->getJson('/api/mcp/cycle-active')
        ->assertStatus(200)
        ->assertJsonPath('cycle.key', 'CYCLE-09')
        ->assertJsonPath('cycle.start_date', '2026-06-01')
        ->assertJsonPath('cycle.end_date', '2026-06-30');
});

it('cycle null quando nenhum ativo', function () {
    McpProject::create(['key' => 'COPI', 'name' => 'T']);

    $this->withHeaders(['Authorization' => 'Bearer test-drift-token'])
        ->getJson('/api/mcp/cycle-active')
        ->assertStatus(200)
        ->assertJsonPath('cycle', null);
});
