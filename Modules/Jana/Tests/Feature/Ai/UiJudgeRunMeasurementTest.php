<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\UiJudgeRun;

uses(Tests\TestCase::class);

/**
 * R-JANA-UI-JUDGE-MEASURE — medição do PR UI Judge (parecer PR #2270).
 *
 * Antes o juiz era fire-and-forget: postava o comentário e o score evaporava.
 * Estes testes travam o contrato de medição (jana_ui_judge_runs + trend):
 *
 *  001. UiJudgeRun persiste + casts (dimensoes array · judged_at datetime · int)
 *  002. jana:ui-judge-trend agrega score médio + distribuição de verdict
 *  003. trend sem dados não quebra (exit 0 + aviso de como ligar)
 *
 * Schema montado à mão (sqlite :memory:) — espelha o job Pest Unit do ci.yml
 * que não roda migrate (Modules/Jana fora do modules-pest.yml).
 *
 * @see Modules/Jana/Database/Migrations/2026_06_05_120000_create_jana_ui_judge_runs_table.php
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::dropIfExists('jana_ui_judge_runs');
    Schema::create('jana_ui_judge_runs', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('pr_number');
        $t->string('repo', 140)->nullable();
        $t->string('provider', 40);
        $t->string('model', 60);
        $t->unsignedSmallInteger('score');
        $t->string('verdict', 20);
        $t->unsignedSmallInteger('violacoes_count')->default(0);
        $t->json('dimensoes')->nullable();
        $t->decimal('custo_usd_estimado', 8, 4)->nullable();
        $t->timestamp('judged_at');
        $t->timestamps();
    });
});

it('R-JANA-UI-JUDGE-MEASURE-001 — persiste run com casts corretos', function () {
    $run = UiJudgeRun::create([
        'pr_number' => 2284,
        'repo' => 'wagnerra23/oimpresso.com',
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-6',
        'score' => 87,
        'verdict' => 'approve',
        'violacoes_count' => 2,
        'dimensoes' => ['pt_01_slot_adherence' => ['score' => 9, 'rationale' => 'ok']],
        'custo_usd_estimado' => 0.034,
        'judged_at' => now(),
    ]);

    $fresh = $run->fresh();

    expect($fresh->score)->toBe(87)
        ->and($fresh->pr_number)->toBe(2284)
        ->and($fresh->dimensoes)->toBeArray()
        ->and($fresh->dimensoes['pt_01_slot_adherence']['score'])->toBe(9)
        ->and($fresh->judged_at)->not->toBeNull()
        ->and($fresh->model)->toBe('claude-sonnet-4-6');
});

it('R-JANA-UI-JUDGE-MEASURE-002 — trend agrega score médio + verdict', function () {
    foreach ([['s' => 80, 'v' => 'approve'], ['s' => 60, 'v' => 'comment'], ['s' => 40, 'v' => 'request_changes']] as $i => $row) {
        UiJudgeRun::create([
            'pr_number' => 100 + $i,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'score' => $row['s'],
            'verdict' => $row['v'],
            'violacoes_count' => 0,
            'custo_usd_estimado' => 0.034,
            'judged_at' => now(),
        ]);
    }

    $this->artisan('jana:ui-judge-trend', ['--days' => 30])
        ->expectsOutputToContain('Julgamentos: 3')
        ->expectsOutputToContain('Score médio: 60')
        ->assertExitCode(0);
});

it('R-JANA-UI-JUDGE-MEASURE-003 — trend sem dados avisa e não quebra', function () {
    $this->artisan('jana:ui-judge-trend')
        ->expectsOutputToContain('Nenhum julgamento')
        ->assertExitCode(0);
});
