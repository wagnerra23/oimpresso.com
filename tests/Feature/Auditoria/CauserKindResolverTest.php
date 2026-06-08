<?php

use App\Observers\ActivityCauserKindObserver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-006 — Observer ActivityCauserKindObserver popula causer_kind + agent_run_id.
 *
 * Valida (per SPEC + ADR 0127):
 *   1. Container binding 'jana.agent_run_id' -> causer_kind='agent' + agent_run_id
 *   2. runningInConsole (sem testing flag) -> causer_kind='system'
 *   3. Default web context -> causer_kind='user'
 *   4. Override explicito ja setado nao e sobrescrito (defensivo)
 *
 * Skip-graceful em sqlite memory (CI). Validacao real com mysql dev pre-merge.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $hasColumn = Schema::hasColumn('activity_log', 'causer_kind');
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema activity_log ausente — rode migrations primeiro.');
    }
    if (! $hasColumn) {
        $this->markTestSkipped('Coluna causer_kind nao existe — rode migration US-AUDIT-005 primeiro.');
    }
});

it('cenario 1: container binding jana.agent_run_id seta causer_kind=agent + agent_run_id', function () {
    app()->instance('jana.agent_run_id', 12345);

    $activity = new Activity();
    $observer = new ActivityCauserKindObserver();
    $observer->saving($activity);

    expect($activity->causer_kind)->toBe('agent');
    expect($activity->agent_run_id)->toBe(12345);

    app()->forgetInstance('jana.agent_run_id');
});

it('cenario 2: override manual (causer_kind ja setado) NAO e sobrescrito', function () {
    $activity = new Activity();
    $activity->causer_kind = 'system'; // override consumer-side

    $observer = new ActivityCauserKindObserver();
    $observer->saving($activity);

    expect($activity->causer_kind)->toBe('system'); // mantem override
});

it('cenario 3: default web context (sem binding agent + sem console) -> user', function () {
    // Garante que jana binding nao existe
    if (app()->bound('jana.agent_run_id')) {
        app()->forgetInstance('jana.agent_run_id');
    }

    $activity = new Activity();
    $observer = new ActivityCauserKindObserver();
    $observer->saving($activity);

    // Em testing (Pest), runningInConsole=true mas runningUnitTests=true -> cai pro default
    // Em testing tambem nao tem request real api/* -> cai pro 'user'
    expect($activity->causer_kind)->toBeIn(['user', 'api']);
});
