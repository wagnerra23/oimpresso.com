<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:health-check — Pest tests Sprint 2 ADR 0123 (compliance LGPD + integridade DMS).
 *
 * Cobertura (7 cenários mínimos):
 * 1. Command registrado em artisan list
 * 2. Output table contém os 5 checks obrigatórios
 * 3. --json retorna JSON válido com schema esperado
 * 4. check audit_log_lag retorna OK quando log recente (< 24h)
 * 5. check retention_overdue retorna WARN quando soft-deleted > 120d existe
 * 6. check vault_encryption_ratio retorna FAIL quando < 95% sensitive encrypted
 * 7. --alert retorna exit 2 quando há FAIL
 *
 * Padrão:
 *   - DB::table('arquivos')->insert([...'classified_by' => 'test-pr28-health']) pra setup
 *   - Cleanup isolado via classified_by = 'test-pr28-health' no afterEach
 *   - Biz=1 (Wagner WR2) pra testes, NUNCA biz=4 (ROTA LIVRE — ADR 0101)
 *   - Storage::fake('local') quando precisar testar filesystem
 *   - Carbon::setTestNow() para simular passagem de tempo
 *
 * @see Modules/Arquivos/Console/Commands/HealthCheckCommand.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 2
 */

// ---------------------------------------------------------------------------
// Marcadores de teste usados pra cleanup isolado
// ---------------------------------------------------------------------------

const HC_TEST_MARKER = 'test-pr28-health';

// ---------------------------------------------------------------------------
// Helpers de fixture
// ---------------------------------------------------------------------------

/**
 * Insere uma row em arquivos com os campos mínimos obrigatórios.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertArquivoHc(array $overrides = []): int
{
    static $seq = 9700;
    $seq++;

    $defaults = [
        'business_id'     => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => $seq,
        'disk'            => 'local',
        'storage_path'    => "biz-1/2026/05/pr28-hc-{$seq}.txt",
        'original_name'   => "pr28-hc-{$seq}.txt",
        'mime_type'       => 'text/plain',
        'size_bytes'      => 1024,
        'md5'             => md5("pr28-hc-{$seq}"),
        'bucket'          => 'active',
        'classified_by'   => HC_TEST_MARKER,
        'encrypted'       => false,
        'deleted_at'      => null,
        'created_at'      => now(),
        'updated_at'      => now(),
    ];

    return DB::table('arquivos')->insertGetId(array_merge($defaults, $overrides));
}

/**
 * Insere uma row em arquivos_audit_log com marcador de teste.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertAuditLogHc(array $overrides = []): int
{
    $defaults = [
        'arquivo_id'  => 9700,
        'business_id' => 1,
        'user_id'     => 100,
        'action'      => 'upload',
        'payload'     => json_encode(['test_marker' => HC_TEST_MARKER]),
        'created_at'  => now(),
    ];

    return DB::table('arquivos_audit_log')->insertGetId(array_merge($defaults, $overrides));
}

// ---------------------------------------------------------------------------
// Setup / Teardown
// ---------------------------------------------------------------------------

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro.');
    }

    Carbon::setTestNow(null);
});

afterEach(function () {
    Carbon::setTestNow(null);

    // afterEach roda mesmo em tests pulados (PHPUnit tearDown semantics).
    // Em SQLite CI sem migrate, DELETE em arquivos estoura — bail antes.
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    // Limpa apenas rows de teste — nunca afeta dados reais de outros suites
    DB::table('arquivos')->where('classified_by', HC_TEST_MARKER)->delete();

    if (Schema::hasTable('arquivos_audit_log')) {
        DB::table('arquivos_audit_log')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.test_marker')) = ?", [HC_TEST_MARKER])
            ->delete();
    }

    if (Schema::hasTable('arquivos_dedupe')) {
        // Limpa qualquer md5 que comece com 'pr28hc' — prefixo exclusivo deste suite
        DB::table('arquivos_dedupe')
            ->where('md5', 'like', 'pr28hc%')
            ->delete();
    }
});

// ---------------------------------------------------------------------------
// 1. Command registrado em artisan list
// ---------------------------------------------------------------------------

it('command arquivos:health-check está registrado no artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arquivos:health-check');
});

// ---------------------------------------------------------------------------
// 2. Output table contém os 5 checks obrigatórios
// ---------------------------------------------------------------------------

it('output table contém os 5 checks obrigatórios com cabeçalho correto', function () {
    $exitCode = Artisan::call('arquivos:health-check');

    expect($exitCode)->toBe(0);

    $output = Artisan::output();

    // Verifica presença dos 5 checks pelo nome na tabela
    expect($output)->toContain('orphan_files');
    expect($output)->toContain('dedupe_inconsistent');
    expect($output)->toContain('audit_log_lag');
    expect($output)->toContain('retention_overdue');
    expect($output)->toContain('vault_encryption_ratio');

    // Verifica cabeçalho informativo
    expect($output)->toContain('Health Check');

    // Verifica footer summary (formato "N OK, M WARN, K FAIL de 5 checks")
    expect($output)->toContain('de 5 checks');
});

// ---------------------------------------------------------------------------
// 3. --json retorna JSON válido com schema esperado
// ---------------------------------------------------------------------------

it('--json retorna JSON válido com schema esperado', function () {
    $exitCode = Artisan::call('arquivos:health-check', ['--json' => true]);

    expect($exitCode)->toBe(0);

    $rawOutput = Artisan::output();

    // Deve ser JSON válido
    $data = json_decode($rawOutput, true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($data)->not->toBeNull();

    // Schema obrigatório de topo
    expect($data)->toHaveKey('timestamp');
    expect($data)->toHaveKey('business_filter');
    expect($data)->toHaveKey('checks');
    expect($data)->toHaveKey('summary');

    // Exatamente 5 checks
    expect($data['checks'])->toHaveCount(5);

    // Cada check tem os campos obrigatórios
    foreach ($data['checks'] as $check) {
        expect($check)->toHaveKey('name');
        expect($check)->toHaveKey('status');
        expect($check)->toHaveKey('value');
        expect($check)->toHaveKey('threshold');
        expect($check)->toHaveKey('details');
        expect($check)->toHaveKey('recommendation');

        // Status deve ser OK, WARN ou FAIL
        expect($check['status'])->toBeIn(['OK', 'WARN', 'FAIL']);
    }

    // Summary tem os 4 campos
    expect($data['summary'])->toHaveKey('ok');
    expect($data['summary'])->toHaveKey('warn');
    expect($data['summary'])->toHaveKey('fail');
    expect($data['summary'])->toHaveKey('total');
    expect($data['summary']['total'])->toBe(5);

    // ok + warn + fail = total
    expect($data['summary']['ok'] + $data['summary']['warn'] + $data['summary']['fail'])->toBe(5);

    // Os 5 nomes de check corretos devem estar presentes
    $checkNames = collect($data['checks'])->pluck('name')->all();
    expect($checkNames)->toContain('orphan_files');
    expect($checkNames)->toContain('dedupe_inconsistent');
    expect($checkNames)->toContain('audit_log_lag');
    expect($checkNames)->toContain('retention_overdue');
    expect($checkNames)->toContain('vault_encryption_ratio');
});

// ---------------------------------------------------------------------------
// 4. Check audit_log_lag retorna OK quando log recente (< 24h)
// ---------------------------------------------------------------------------

it('check audit_log_lag retorna OK quando existe log recente (< 24h)', function () {
    if (! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('arquivos_audit_log table missing.');
    }

    // Insere log recente (2h atrás — dentro do limite de 24h)
    insertAuditLogHc([
        'action'     => 'upload',
        'created_at' => now()->subHours(2)->toDateTimeString(),
    ]);

    $exitCode = Artisan::call('arquivos:health-check', [
        '--business' => 1,
        '--json'     => true,
    ]);

    $data = json_decode(Artisan::output(), true);

    $auditCheck = collect($data['checks'])->firstWhere('name', 'audit_log_lag');
    expect($auditCheck)->not->toBeNull();
    expect($auditCheck['status'])->toBe('OK');
});

// ---------------------------------------------------------------------------
// 5. Check retention_overdue retorna WARN quando soft-deleted > 120d existe
// ---------------------------------------------------------------------------

it('check retention_overdue retorna WARN quando existe soft-deleted há mais de 120 dias', function () {
    // Insere arquivo soft-deleted há 120 dias
    // Com retention default 90d + grace 30d = 120d de threshold para WARN
    // 121d garante ultrapassar o threshold
    insertArquivoHc([
        'deleted_at' => now()->subDays(121)->toDateTimeString(),
        'bucket'     => 'active',
    ]);

    $exitCode = Artisan::call('arquivos:health-check', [
        '--business' => 1,
        '--json'     => true,
    ]);

    $data = json_decode(Artisan::output(), true);

    $retentionCheck = collect($data['checks'])->firstWhere('name', 'retention_overdue');
    expect($retentionCheck)->not->toBeNull();

    // Deve ser WARN ou FAIL (acima de 0 rows)
    expect($retentionCheck['status'])->toBeIn(['WARN', 'FAIL']);
    // Value deve ser > 0 (count de rows overdue)
    expect((int) $retentionCheck['value'])->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// 6. Check vault_encryption_ratio retorna FAIL quando < 95% sensitive encrypted
// ---------------------------------------------------------------------------

it('check vault_encryption_ratio retorna FAIL quando menos de 95% sensitive está encrypted', function () {
    // Insere 9 arquivos sensitive SEM encrypt (90% sem cifrar)
    foreach (range(1, 9) as $i) {
        insertArquivoHc([
            'bucket'    => 'sensitive',
            'encrypted' => false,
        ]);
    }

    // Insere 1 arquivo sensitive COM encrypt (10% cifrado — abaixo de 95%)
    insertArquivoHc([
        'bucket'    => 'sensitive',
        'encrypted' => true,
    ]);

    $exitCode = Artisan::call('arquivos:health-check', [
        '--business' => 1,
        '--json'     => true,
    ]);

    $data = json_decode(Artisan::output(), true);

    $vaultCheck = collect($data['checks'])->firstWhere('name', 'vault_encryption_ratio');
    expect($vaultCheck)->not->toBeNull();
    expect($vaultCheck['status'])->toBe('FAIL');

    // Details deve mencionar os 9 sem encryption
    expect($vaultCheck['details'])->toContain('SEM encryption');
});

// ---------------------------------------------------------------------------
// 7. --alert retorna exit 2 quando há FAIL
// ---------------------------------------------------------------------------

it('--alert retorna exit code 2 quando existe pelo menos um check FAIL', function () {
    // Garante cenário de FAIL no check vault_encryption_ratio:
    // Insere 10 sensitive, todos sem encrypt → ratio 0% → FAIL (< 95%)
    foreach (range(1, 10) as $i) {
        insertArquivoHc([
            'bucket'    => 'sensitive',
            'encrypted' => false,
        ]);
    }

    $exitCode = Artisan::call('arquivos:health-check', [
        '--business' => 1,
        '--alert'    => true,
    ]);

    // Deve retornar exit 2 porque há FAIL no vault_encryption_ratio
    expect($exitCode)->toBe(2);
});
