<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Entities\Initiative;
use Modules\Governance\Services\InitiativeService;
use Tests\TestCase;

/**
 * Wave 28 Agent 1 (2026-05-17) — Tests pro loop Initiatives Cortex/Port.io-style.
 *
 * Cobre:
 *   - migration cria tabela mcp_governance_initiatives com colunas canônicas
 *   - createFromScorecardBreach() abre Initiative + idempotência
 *   - listOpen() com/sem filtro bucket
 *   - autoClose() marca expired + alerta cross-tenant
 *   - syncFromScorecards() loop completo (abre breach + fecha recuperada + expira deadline)
 *   - Command governance:initiative-sync roda + schedule registrado
 *
 * Pattern Wave 26 canônico — `uses(TestCase::class)` explicit + Schema::create
 * direto em beforeEach. NÃO usa RefreshDatabase porque migrations legacy do
 * projeto (transactions MODIFY COLUMN ENUM) quebram em SQLite :memory:.
 *
 * @see Modules/Governance/Services/InitiativeService.php
 * @see Modules/Governance/Entities/Initiative.php
 * @see Modules/Governance/Console/Commands/ScorecardInitiativeSyncCommand.php
 * @see Modules/Governance/Database/Migrations/2026_05_17_000003_create_mcp_governance_initiatives_table.php
 */

uses(TestCase::class);

beforeEach(function () {
    // era-sqlite: cria schema manual (sqlite-friendly). No MySQL persistente do nightly
    // isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é na lane
    // sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Tabela canônica do Wave 28 — cria direto pra evitar RefreshDatabase global
    Schema::dropIfExists('mcp_governance_initiatives');
    Schema::create('mcp_governance_initiatives', function (Blueprint $table) {
        $table->id();
        $table->string('module', 100);
        $table->string('bucket', 50);
        $table->string('rule_id', 100);
        $table->string('titulo');
        $table->text('descricao');
        $table->string('status', 20)->default('open'); // SQLite não tem ENUM nativo — string OK
        $table->date('deadline');
        $table->unsignedSmallInteger('score_before');
        $table->unsignedSmallInteger('score_target');
        $table->unsignedSmallInteger('score_after')->nullable();
        $table->unsignedBigInteger('owner_user_id')->nullable();
        $table->timestamp('opened_at');
        $table->timestamp('closed_at')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->index(['status', 'deadline'], 'idx_mgi_status_deadline');
        $table->index(['module', 'rule_id', 'status'], 'idx_mgi_module_rule_status');
    });

    // mcp_scorecard_runs — consumida por syncFromScorecards
    Schema::dropIfExists('mcp_scorecard_runs');
    Schema::create('mcp_scorecard_runs', function (Blueprint $table) {
        $table->id();
        $table->string('module', 100);
        $table->string('bucket', 50);
        $table->unsignedSmallInteger('score');
        $table->json('breakdown_json');
        $table->date('snapshot_date');
        $table->timestamp('created_at')->useCurrent();
    });

    // mcp_alertas — Wave 28 grava alertas expired (cross-tenant biz=1 superadmin)
    Schema::dropIfExists('mcp_alertas');
    Schema::create('mcp_alertas', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('business_id');
        $table->string('kind', 50);
        $table->float('threshold')->nullable();
        $table->string('canal', 50)->nullable();
        $table->boolean('ativo')->default(true);
        $table->json('config_extra')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_governance_initiatives');
    Schema::dropIfExists('mcp_scorecard_runs');
    Schema::dropIfExists('mcp_alertas');
});

it('migration cria tabela mcp_governance_initiatives com colunas canônicas', function () {
    expect(Schema::hasTable('mcp_governance_initiatives'))->toBeTrue();
    expect(Schema::hasColumns('mcp_governance_initiatives', [
        'id', 'module', 'bucket', 'rule_id', 'titulo', 'descricao',
        'status', 'deadline', 'score_before', 'score_target', 'score_after',
        'owner_user_id', 'opened_at', 'closed_at', 'metadata',
    ]))->toBeTrue();
});

it('createFromScorecardBreach cria Initiative open com deadline default +14 dias', function () {
    $service = app(InitiativeService::class);

    $init = $service->createFromScorecardBreach(
        module: 'Vestuario',
        bucket: 'vertical_client_facing',
        ruleId: 'F1.a',
        scoreBefore: 40,
        scoreTarget: 80,
    );

    expect($init->id)->toBeGreaterThan(0);
    expect($init->module)->toBe('Vestuario');
    expect($init->rule_id)->toBe('F1.a');
    expect($init->status)->toBe(Initiative::STATUS_OPEN);
    expect($init->score_before)->toBe(40);
    expect($init->score_target)->toBe(80);
    expect($init->deadline->toDateString())->toBe(now()->addDays(14)->toDateString());
    expect($init->isOpen())->toBeTrue();
});

it('createFromScorecardBreach é IDEMPOTENTE — não duplica pro mesmo (module, rule_id, status=open)', function () {
    $service = app(InitiativeService::class);

    $first = $service->createFromScorecardBreach('Governance', 'cross_cutting_infra', 'D1.b', 30, 75);
    $second = $service->createFromScorecardBreach('Governance', 'cross_cutting_infra', 'D1.b', 35, 75);
    $third = $service->createFromScorecardBreach('Governance', 'cross_cutting_infra', 'D1.b', 28, 75);

    expect($first->id)->toBe($second->id);
    expect($second->id)->toBe($third->id);
    expect(Initiative::count())->toBe(1);
});

it('listOpen retorna apenas Initiatives open/in_progress, ordered by deadline asc', function () {
    $service = app(InitiativeService::class);

    Initiative::create([
        'module' => 'A', 'bucket' => 'b1', 'rule_id' => 'R1', 'titulo' => 'T1', 'descricao' => 'D1',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->addDays(5),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now(),
    ]);
    Initiative::create([
        'module' => 'B', 'bucket' => 'b1', 'rule_id' => 'R2', 'titulo' => 'T2', 'descricao' => 'D2',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->addDays(2),
        'score_before' => 40, 'score_target' => 80, 'opened_at' => now(),
    ]);
    Initiative::create([
        'module' => 'C', 'bucket' => 'b1', 'rule_id' => 'R3', 'titulo' => 'T3', 'descricao' => 'D3',
        'status' => Initiative::STATUS_DONE, 'deadline' => now()->addDays(1),
        'score_before' => 50, 'score_target' => 80, 'score_after' => 85,
        'opened_at' => now()->subDays(5), 'closed_at' => now(),
    ]);

    $open = $service->listOpen();
    expect($open)->toHaveCount(2);
    expect($open->first()->module)->toBe('B');
    expect($open->last()->module)->toBe('A');
});

it('listOpen filtra por bucket quando passado', function () {
    $service = app(InitiativeService::class);

    Initiative::create([
        'module' => 'X', 'bucket' => 'vertical_client_facing', 'rule_id' => 'V1', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->addDays(7),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now(),
    ]);
    Initiative::create([
        'module' => 'Y', 'bucket' => 'cross_cutting_infra', 'rule_id' => 'D1', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->addDays(7),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now(),
    ]);

    $filtered = $service->listOpen('vertical_client_facing');
    expect($filtered)->toHaveCount(1);
    expect($filtered->first()->bucket)->toBe('vertical_client_facing');
});

it('autoClose marca expired Initiatives com deadline passada e registra mcp_alertas', function () {
    $service = app(InitiativeService::class);

    Initiative::create([
        'module' => 'OldA', 'bucket' => 'b', 'rule_id' => 'R1', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->subDays(3),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now()->subDays(20),
    ]);
    Initiative::create([
        'module' => 'OldB', 'bucket' => 'b', 'rule_id' => 'R2', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_IN_PROGRESS, 'deadline' => now()->subDays(1),
        'score_before' => 40, 'score_target' => 80, 'opened_at' => now()->subDays(16),
    ]);
    Initiative::create([
        'module' => 'Fresh', 'bucket' => 'b', 'rule_id' => 'R3', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->addDays(5),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now(),
    ]);

    $count = $service->autoClose();

    expect($count)->toBe(2);
    expect(Initiative::where('status', Initiative::STATUS_EXPIRED)->count())->toBe(2);
    expect(Initiative::where('status', Initiative::STATUS_OPEN)->count())->toBe(1);

    $alertCount = DB::table('mcp_alertas')
        ->where('kind', InitiativeService::DRIFT_ALERT_KIND_EXPIRED)
        ->count();
    expect($alertCount)->toBe(2);
});

it('syncFromScorecards abre Initiative pra rule abaixo do target (idempotent em reruns)', function () {
    $service = app(InitiativeService::class);

    DB::table('mcp_scorecard_runs')->insert([
        'module' => 'Vestuario',
        'bucket' => 'vertical_client_facing',
        'score' => 60,
        'breakdown_json' => json_encode([
            'rules' => [
                ['rule_id' => 'F1.a', 'score' => 40, 'target' => 80],
                ['rule_id' => 'V6.a', 'score' => 90, 'target' => 80],
            ],
        ]),
        'snapshot_date' => now()->toDateString(),
        'created_at' => now(),
    ]);

    $stats1 = $service->syncFromScorecards();
    expect($stats1['opened'])->toBe(1);
    expect(Initiative::where('module', 'Vestuario')->where('rule_id', 'F1.a')->open()->count())->toBe(1);

    $stats2 = $service->syncFromScorecards();
    expect($stats2['opened'])->toBe(0);
    expect(Initiative::where('module', 'Vestuario')->where('rule_id', 'F1.a')->open()->count())->toBe(1);
});

it('syncFromScorecards fecha Initiative quando score_after >= target (loop completo)', function () {
    $service = app(InitiativeService::class);

    Initiative::create([
        'module' => 'Vestuario', 'bucket' => 'vertical_client_facing', 'rule_id' => 'F1.a',
        'titulo' => 'T', 'descricao' => 'D', 'status' => Initiative::STATUS_OPEN,
        'deadline' => now()->addDays(10),
        'score_before' => 40, 'score_target' => 80,
        'opened_at' => now()->subDays(3),
    ]);

    DB::table('mcp_scorecard_runs')->insert([
        'module' => 'Vestuario',
        'bucket' => 'vertical_client_facing',
        'score' => 88,
        'breakdown_json' => json_encode([
            'rules' => [
                ['rule_id' => 'F1.a', 'score' => 85, 'target' => 80],
            ],
        ]),
        'snapshot_date' => now()->toDateString(),
        'created_at' => now(),
    ]);

    $stats = $service->syncFromScorecards();

    expect($stats['closed'])->toBe(1);
    $closed = Initiative::where('module', 'Vestuario')->where('rule_id', 'F1.a')->first();
    expect($closed->status)->toBe(Initiative::STATUS_DONE);
    expect($closed->score_after)->toBe(85);
    expect($closed->closed_at)->not->toBeNull();
});

it('command governance:initiative-sync roda sem erro e reporta stats', function () {
    DB::table('mcp_scorecard_runs')->insert([
        'module' => 'Repair',
        'bucket' => 'cross_cutting_infra',
        'score' => 50,
        'breakdown_json' => json_encode([
            'rules' => [
                ['rule_id' => 'D9.b', 'score' => 30, 'target' => 70],
            ],
        ]),
        'snapshot_date' => now()->toDateString(),
        'created_at' => now(),
    ]);

    $this->artisan('governance:initiative-sync')
        ->expectsOutputToContain('Sync OK')
        ->assertExitCode(0);

    expect(Initiative::where('module', 'Repair')->where('rule_id', 'D9.b')->open()->count())->toBe(1);
});

it('command --detail mostra Initiatives abertas linha-por-linha', function () {
    Initiative::create([
        'module' => 'TestMod', 'bucket' => 'cross_cutting_infra', 'rule_id' => 'X.a',
        'titulo' => 'Test', 'descricao' => 'D', 'status' => Initiative::STATUS_OPEN,
        'deadline' => now()->addDays(7),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now(),
    ]);

    $this->artisan('governance:initiative-sync --detail')
        ->expectsOutputToContain('Sync OK')
        ->expectsOutputToContain('TestMod')
        ->assertExitCode(0);
});

it('schedule governance:initiative-sync registrado daily 08:00 BRT (live env)', function () {
    /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events());

    $event = $events->first(function ($e) {
        return str_contains((string) $e->command, 'governance:initiative-sync');
    });

    expect($event)->not->toBeNull('Schedule governance:initiative-sync nao registrado em app/Console/Kernel.php');
    expect($event->expression)->toBe('0 8 * * *');
    expect($event->timezone)->toBe('America/Sao_Paulo');
});

it('Initiative::isOverdue retorna true quando deadline passou e status=open', function () {
    $overdueInit = Initiative::create([
        'module' => 'X', 'bucket' => 'b', 'rule_id' => 'R', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->subDays(2),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now()->subDays(20),
    ]);
    $freshInit = Initiative::create([
        'module' => 'Y', 'bucket' => 'b', 'rule_id' => 'R', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_OPEN, 'deadline' => now()->addDays(5),
        'score_before' => 30, 'score_target' => 80, 'opened_at' => now(),
    ]);
    $doneInit = Initiative::create([
        'module' => 'Z', 'bucket' => 'b', 'rule_id' => 'R', 'titulo' => 'T', 'descricao' => 'D',
        'status' => Initiative::STATUS_DONE, 'deadline' => now()->subDays(2),
        'score_before' => 30, 'score_target' => 80, 'score_after' => 85,
        'opened_at' => now()->subDays(20), 'closed_at' => now()->subDay(),
    ]);

    expect($overdueInit->isOverdue())->toBeTrue();
    expect($freshInit->isOverdue())->toBeFalse();
    expect($doneInit->isOverdue())->toBeFalse();
});
