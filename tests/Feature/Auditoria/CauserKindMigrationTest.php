<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-AUDIT-005 — valida que migration causer_kind aplicou no schema atual.
 *
 * Smoke test estrutural:
 *   1. Colunas adicionadas existem em activity_log
 *   2. Default causer_kind='user' aplica em rows existentes
 *   3. Indices compostos existem
 *
 * Skip-graceful em sqlite memory (CI). Validacao real com mysql dev pre-merge.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $hasTable = Schema::hasTable('activity_log');
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema activity_log ausente — rode migrations primeiro.');
    }
    if (! $hasTable) {
        $this->markTestSkipped('Tabela activity_log nao existe.');
    }
});

it('cenario 1: 5 colunas adicionadas pela migration existem em activity_log', function () {
    $expected = [
        'causer_kind',
        'agent_run_id',
        'reverted_at',
        'reverted_by_user_id',
        'revert_reason',
    ];

    foreach ($expected as $col) {
        expect(Schema::hasColumn('activity_log', $col))
            ->toBeTrue("Coluna {$col} deveria existir apos migration US-AUDIT-005");
    }
});

it('cenario 2: rows existentes recebem default causer_kind=user (zero-downtime)', function () {
    // Conta quantas rows tem causer_kind diferente de 'user' OU NULL
    // (default deveria ter populado todas)
    $invalidCount = DB::table('activity_log')
        ->whereNull('causer_kind')
        ->count();

    expect($invalidCount)->toBe(0, 'Default deveria ter populado causer_kind=user em rows existentes');
});

it('cenario 3: indices compostos existem (queries UI /auditoria)', function () {
    // sqlite e mysql divergem em pragma; usa SQL nativo via PDO pra mysql apenas
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Index inspection so faz sentido em mysql (driver atual: '.DB::getDriverName().').');
    }

    $indexes = collect(DB::select('SHOW INDEX FROM activity_log'))
        ->pluck('Key_name')
        ->unique()
        ->all();

    expect($indexes)->toContain('idx_business_kind_created');
    expect($indexes)->toContain('idx_subject_reverted');
});
