<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * governance:staging-freshness-alert — sink da sentinela de frescor do checkout de
 * staging (host CT100) em mcp_alertas_eventos, via trait canônico PersistsDriftAlert.
 *
 * Contrato (não derivado da implementação):
 *   - verdict stale:*      → persiste 1 alerta drift_staging_freshness (severidade high)
 *   - verdict fresco/etc   → NO-OP (não polui mcp_alertas com ruído verde)
 *   - hourly no mesmo dia  → idempotente (1 alerta/dia, não spam)
 */

beforeEach(function () {
    // delete() (DML transaction-safe) — TRUNCATE dá implicit commit e quebra DatabaseTransactions.
    DB::table('mcp_alertas_eventos')->where('tipo', 'drift_staging_freshness')->delete();
});

it('verdict stale → persiste alerta high em mcp_alertas_eventos', function () {
    $this->artisan('governance:staging-freshness-alert', [
        '--verdict' => 'stale:4d',
        '--head' => '3acabd2fd31658ecefd3e8ca8f7a086b57cbc0f6',
        '--main' => '64a7593d639d8b1404feca0c3f836c28f22dcfad',
        '--age' => '4',
    ])->assertExitCode(0);

    $row = DB::table('mcp_alertas_eventos')->where('tipo', 'drift_staging_freshness')->first();
    $meta = json_decode($row->metadata, true);

    expect($row)->not->toBeNull()
        ->and($row->severidade)->toBe('high')
        ->and($row->status)->toBe('aberto')
        ->and($row->business_id)->toBeNull()          // repo-wide (ADR 0093 §Exceção)
        ->and($meta['verdict'])->toBe('stale:4d')
        ->and($meta['age_days'])->toBe(4);
});

it('verdict fresco → NO-OP (não cria alerta)', function () {
    $this->artisan('governance:staging-freshness-alert', [
        '--verdict' => 'fresco',
        '--head' => 'abc1234',
        '--main' => 'abc1234',
        '--age' => '0',
    ])->assertExitCode(0);

    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_staging_freshness')->count())->toBe(0);
});

it('verdict atras-recente → NO-OP (lag tolerado não polui alertas)', function () {
    $this->artisan('governance:staging-freshness-alert', [
        '--verdict' => 'atras-recente:1d',
        '--head' => 'aaaa111',
        '--main' => 'bbbb222',
        '--age' => '1',
    ])->assertExitCode(0);

    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_staging_freshness')->count())->toBe(0);
});

it('hourly no mesmo dia → idempotente (1 alerta/dia, não spam)', function () {
    $args = [
        '--verdict' => 'stale:5d',
        '--head' => '3acabd2fd',
        '--main' => '64a7593d6',
        '--age' => '5',
    ];
    $this->artisan('governance:staging-freshness-alert', $args)->assertExitCode(0);
    $this->artisan('governance:staging-freshness-alert', $args)->assertExitCode(0);
    $this->artisan('governance:staging-freshness-alert', $args)->assertExitCode(0);

    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_staging_freshness')->count())->toBe(1);
});
