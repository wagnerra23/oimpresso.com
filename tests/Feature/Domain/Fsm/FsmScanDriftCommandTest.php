<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * US-SELL-032 v2 — Comando `fsm:scan-drift` (ADR 0129).
 *
 * Specs:
 *   1. exit 0 quando zero drifts
 *   2. exit 1 quando >=1 drift
 *   3. output formatado lista drifts (orphan + mismatch)
 *   4. tabela fora da whitelist retorna exit 2 (sanity check)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Schema::create('fsm_test_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('current_stage_id')->nullable();
    });

    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }

        Schema::dropIfExists('fsm_test_subjects');
    }
});

function scanCmdSetup(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'cmd_drift_' . $bizId,
        'name' => 'CmdDrift biz=' . $bizId,
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    $a = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'cmda_' . $bizId, 'name' => 'StageA', 'sort_order' => 0, 'is_initial' => true,
    ]);
    $b = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'cmdb_' . $bizId, 'name' => 'StageB', 'sort_order' => 1,
    ]);

    return compact('a', 'b');
}

function scanCmdSubject(int $bizId, ?int $stageId): int
{
    return (int) DB::table('fsm_test_subjects')->insertGetId([
        'business_id' => $bizId,
        'current_stage_id' => $stageId,
    ]);
}

function scanCmdHistory(int $bizId, int $txId, ?int $fromStageId, ?int $toStageId, string $executedAt): void
{
    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'action_id' => 1,
        'from_stage_id' => $fromStageId,
        'to_stage_id' => $toStageId,
        'executed_at' => $executedAt,
    ]);
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. exit 0 quando zero drifts', function () {
    ['a' => $a, 'b' => $b] = scanCmdSetup(1);
    $tx = scanCmdSubject(1, $b->id);

    // History coerente: subject está em B, último to_stage_id é B
    scanCmdHistory(1, $tx, null, $a->id, '2026-05-12 10:00:00');
    scanCmdHistory(1, $tx, $a->id, $b->id, '2026-05-12 11:00:00');

    $this->artisan('fsm:scan-drift', ['table' => 'fsm_test_subjects'])
        ->expectsOutputToContain('No drift detected')
        ->assertExitCode(0);
});

it('2. exit 1 quando >=1 drift', function () {
    ['a' => $a] = scanCmdSetup(1);
    scanCmdSubject(1, $a->id); // orphan (zero history)

    $this->artisan('fsm:scan-drift', ['table' => 'fsm_test_subjects'])
        ->expectsOutputToContain('Found 1 drift')
        ->assertExitCode(1);
});

it('3. output formatado lista drifts (orphan + mismatch)', function () {
    ['a' => $a, 'b' => $b] = scanCmdSetup(1);

    // Drift 1: orphan
    $txOrphan = scanCmdSubject(1, $a->id);

    // Drift 2: mismatch
    $txMis = scanCmdSubject(1, $b->id);
    scanCmdHistory(1, $txMis, null, $a->id, '2026-05-12 10:00:00');
    // history aponta to_stage_id=A, current=B → mismatch
    DB::table('fsm_test_subjects')->where('id', $txMis)->update(['current_stage_id' => $b->id]);

    $this->artisan('fsm:scan-drift', ['table' => 'fsm_test_subjects'])
        ->expectsOutputToContain('Found 2 drift')
        ->expectsOutputToContain('orphan')
        ->expectsOutputToContain('mismatch')
        ->assertExitCode(1);
});

it('4. tabela fora da whitelist retorna exit 2', function () {
    $this->artisan('fsm:scan-drift', ['table' => 'users'])
        ->expectsOutputToContain('não está na whitelist')
        ->assertExitCode(2);
});
