<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Admin\Services\HealthSnapshotReader;

uses(Tests\TestCase::class);

/**
 * HealthSnapshotReaderTest — Wave 25 SATURATION (Admin D2 boost +7).
 *
 * Cobre paths do Reader W2:
 *   1. snapshot ausente → stub vermelho instructions
 *   2. snapshot JSON inválido → stub vermelho com razão
 *   3. snapshot all green Tier 0 → overall_status=green
 *   4. snapshot 1 Tier 0 red → tier_0_failures conta 1, overall=red
 *   5. snapshot 1 Tier 0 yellow → tier_0_failures conta 1 (yellow ≠ green)
 *   6. checks vazio (mas snapshot existe) → overall=green, empty arrays
 *   7. checks não-Tier 0 red não afetam overall_status
 *
 * Pattern: usa Storage::fake('local') pra isolar I/O.
 *
 * Cross-tenant: HealthSnapshotReader é admin-only (Wagner), não tem
 * business_id scope — by design (ADR 0122 admin-center-ct100).
 *
 * @see Modules\Admin\Services\HealthSnapshotReader
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D2/D9
 */

beforeEach(function () {
    Storage::fake('local');
    config(['otel.enabled' => false]);
});

it('snapshot ausente retorna stub vermelho com instructions', function () {
    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    expect($r['available'])->toBeFalse();
    expect($r['reason'])->toBe('snapshot_missing');
    expect($r['overall_status'])->toBe('unknown');
    expect($r['checks'])->toBe([]);
    expect($r['instructions'])->toContain('jana:health-check');
});

it('snapshot JSON inválido retorna stub com razão snapshot_invalid_json', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', 'isso não é JSON {{{');

    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    expect($r['available'])->toBeFalse();
    expect($r['reason'])->toBe('snapshot_invalid_json');
});

it('snapshot all green Tier 0 retorna overall_status=green', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', json_encode([
        'generated_at' => '2026-05-16T10:00:00Z',
        'checks' => [
            ['name' => 'multi_tenant_isolation', 'status' => 'green', 'message' => 'ok'],
            ['name' => 'pii_leak_in_assistant_responses', 'status' => 'green', 'message' => 'ok'],
            ['name' => 'brief_uptime_24h', 'status' => 'green', 'message' => 'ok'],
        ],
    ]));

    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    expect($r['available'])->toBeTrue();
    expect($r['overall_status'])->toBe('green');
    expect($r['tier_0_failures'])->toBe([]);
    expect(count($r['checks']))->toBe(3);
});

it('snapshot 1 Tier 0 multi_tenant red marca overall=red', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', json_encode([
        'generated_at' => '2026-05-16T10:00:00Z',
        'checks' => [
            ['name' => 'multi_tenant_isolation', 'status' => 'red', 'message' => 'leak detectado biz=99'],
            ['name' => 'pii_leak_in_assistant_responses', 'status' => 'green', 'message' => 'ok'],
        ],
    ]));

    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    expect($r['available'])->toBeTrue();
    expect($r['overall_status'])->toBe('red');
    expect(count($r['tier_0_failures']))->toBe(1);
    expect($r['tier_0_failures'][0]['name'])->toBe('multi_tenant_isolation');
});

it('snapshot 1 Tier 0 yellow conta como failure', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', json_encode([
        'checks' => [
            ['name' => 'pii_leak_in_assistant_responses', 'status' => 'yellow', 'message' => 'borderline'],
        ],
    ]));

    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    expect($r['overall_status'])->toBe('red');
    expect(count($r['tier_0_failures']))->toBe(1);
});

it('checks vazio retorna overall=green e arrays vazios', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', json_encode([
        'generated_at' => '2026-05-16T10:00:00Z',
        'checks' => [],
    ]));

    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    expect($r['available'])->toBeTrue();
    expect($r['overall_status'])->toBe('green');
    expect($r['checks'])->toBe([]);
    expect($r['tier_0_failures'])->toBe([]);
});

it('check não-Tier 0 red não afeta overall_status', function () {
    Storage::disk('local')->put('jana-health-snapshot.json', json_encode([
        'checks' => [
            ['name' => 'algum_check_qualquer', 'status' => 'red', 'message' => 'não-Tier 0'],
            ['name' => 'multi_tenant_isolation', 'status' => 'green', 'message' => 'ok'],
        ],
    ]));

    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    expect($r['overall_status'])->toBe('green');
    expect($r['tier_0_failures'])->toBe([]);
});

it('span OTel zero-cost quando otel.enabled=false (smoke)', function () {
    config(['otel.enabled' => false]);

    $reader = new HealthSnapshotReader();
    $r = $reader->fetch();

    // Não lança exception nem custos detectáveis. Test smoke do path zero-cost.
    expect($r)->toBeArray();
    expect($r)->toHaveKey('overall_status');
});
