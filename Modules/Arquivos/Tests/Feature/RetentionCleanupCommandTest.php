<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * arquivos:retention-cleanup — Pest tests Sprint 7 ADR 0123 (LGPD hard-delete pós-retention).
 *
 * Cobertura (8 cenários):
 * 1. Command registrado em artisan list
 * 2. Retorna 0 + "Nada pra processar" quando sem soft-deleted rows
 * 3. --dry-run não modifica DB nem disk
 * 4. Soft-deleted há <90d NÃO é hard-deleted (preservado)
 * 5. Soft-deleted há >90d É hard-deleted (file removido + DB removido + audit log)
 * 6. --days=30 override funciona (rows com 60d soft-deleted são hard-deleted)
 * 7. Multi-tenant: --business=1 só processa biz 1, biz 99 preservado
 * 8. File ausente conta missing_file mas continua processando + DB hard-delete (orphan cleanup)
 *
 * Padrão:
 *   - Storage::fake('local') pra testes de disk
 *   - Carbon::setTestNow() pra simular passagem de tempo
 *   - DB::table('arquivos')->insert([...'deleted_at' => now()->subDays(N)]) pra setup
 *   - Cleanup isolado via classified_by = 'test-pr24-retention-cleanup' no afterEach
 *   - Biz=1 (Wagner WR2) pra testes, NUNCA biz=4 (ROTA LIVRE — ADR 0101)
 *
 * @see Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 7
 */

// ---------------------------------------------------------------------------
// Setup & Teardown
// ---------------------------------------------------------------------------

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode Modules/Arquivos migrate primeiro');
    }

    // Reset do tempo fictício antes de cada teste
    Carbon::setTestNow(null);
});

afterEach(function () {
    // Reset do tempo fictício após cada teste
    Carbon::setTestNow(null);

    // afterEach roda mesmo em tests pulados (PHPUnit tearDown). Em SQLite CI
    // sem migrate, DELETE estoura — bail antes.
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    // Limpa apenas rows de teste — não afeta dados de outros suites
    DB::table('arquivos_audit_log')
        ->whereIn('arquivo_id', function ($q) {
            $q->select('id')
              ->from('arquivos')
              ->where('classified_by', 'test-pr24-retention-cleanup');
        })
        ->delete();

    // Hard-deleted rows não existem mais em arquivos — limpa por business_id=1 e path prefix
    // (rows deletadas já foram removidas pelo command; limpa as remanescentes de testes)
    DB::table('arquivos')->where('classified_by', 'test-pr24-retention-cleanup')->delete();
});

// ---------------------------------------------------------------------------
// Helper — insere row em arquivos (soft-deleted ou não)
// ---------------------------------------------------------------------------

/**
 * Insere uma row em arquivos com os campos mínimos obrigatórios.
 *
 * @param  array  $overrides  Campos a sobrescrever nos defaults
 * @return int  ID inserido
 */
function insertArquivo(array $overrides = []): int
{
    static $seq = 8100;
    $seq++;

    $defaults = [
        'business_id'     => 1,
        'arquivable_type' => 'TestModel',
        'arquivable_id'   => $seq,
        'disk'            => 'local',
        'storage_path'    => "biz-1/2026/05/test-pr24-{$seq}.txt",
        'original_name'   => "test-pr24-{$seq}.txt",
        'mime_type'       => 'text/plain',
        'size_bytes'      => 1024,
        'md5'             => md5("test-pr24-{$seq}"),
        'bucket'          => 'active',
        'classified_by'   => 'test-pr24-retention-cleanup',
        'created_at'      => now(),
        'updated_at'      => now(),
        'deleted_at'      => null,
    ];

    return DB::table('arquivos')->insertGetId(array_merge($defaults, $overrides));
}

// ---------------------------------------------------------------------------
// 1. Command registrado em artisan list
// ---------------------------------------------------------------------------

it('command arquivos:retention-cleanup está registrado no artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arquivos:retention-cleanup');
});

// ---------------------------------------------------------------------------
// 2. Retorna 0 + "Nada pra processar" quando sem soft-deleted rows elegíveis
// ---------------------------------------------------------------------------

it('retorna 0 e "Nada pra processar" quando sem soft-deleted rows no threshold', function () {
    // Insere row soft-deleted recente (só 10 dias) — abaixo do threshold padrão 90d
    insertArquivo([
        'deleted_at' => now()->subDays(10)->toDateTimeString(),
    ]);

    $exitCode = Artisan::call('arquivos:retention-cleanup', ['--limit' => 500]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Nada pra processar');
});

// ---------------------------------------------------------------------------
// 3. --dry-run não modifica DB nem disk
// ---------------------------------------------------------------------------

it('--dry-run não modifica DB nem disk', function () {
    Storage::fake('local');

    $path = 'biz-1/2026/05/dry-run-test.txt';
    Storage::disk('local')->put($path, 'conteudo-dry-run');

    $id = insertArquivo([
        'disk'         => 'local',
        'storage_path' => $path,
        'deleted_at'   => now()->subDays(100)->toDateTimeString(),
    ]);

    $exitCode = Artisan::call('arquivos:retention-cleanup', [
        '--dry-run' => true,
        '--limit'   => 500,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('would hard-delete');

    // DB: row ainda existe (soft-deleted, não hard-deleted)
    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row)->not->toBeNull();

    // Disk: file ainda existe
    expect(Storage::disk('local')->exists($path))->toBeTrue();

    // Audit log: nenhum registro hard_delete pra este arquivo
    $audit = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $id)
        ->where('action', 'hard_delete')
        ->first();
    expect($audit)->toBeNull();
});

// ---------------------------------------------------------------------------
// 4. Soft-deleted há <90d NÃO é hard-deleted (dentro do período de retention)
// ---------------------------------------------------------------------------

it('soft-deleted há menos de 90 dias não é hard-deleted', function () {
    Storage::fake('local');

    $path = 'biz-1/2026/05/recente-test.txt';
    Storage::disk('local')->put($path, 'conteudo-recente');

    $id = insertArquivo([
        'disk'         => 'local',
        'storage_path' => $path,
        'deleted_at'   => now()->subDays(89)->toDateTimeString(), // 89d < 90d threshold
    ]);

    $exitCode = Artisan::call('arquivos:retention-cleanup', ['--limit' => 500]);

    expect($exitCode)->toBe(0);

    // Row DEVE ainda existir no DB
    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row)->not->toBeNull();

    // File DEVE ainda existir no disk
    expect(Storage::disk('local')->exists($path))->toBeTrue();
});

// ---------------------------------------------------------------------------
// 5. Soft-deleted há >90d É hard-deleted (file + DB + audit log)
// ---------------------------------------------------------------------------

it('soft-deleted há mais de 90 dias é hard-deleted com file + DB + audit log', function () {
    Storage::fake('local');

    $path = 'biz-1/2026/05/antigo-test.txt';
    Storage::disk('local')->put($path, 'conteudo-antigo');

    $deletedAt = now()->subDays(100)->toDateTimeString();
    $id = insertArquivo([
        'disk'         => 'local',
        'storage_path' => $path,
        'deleted_at'   => $deletedAt,
    ]);

    $exitCode = Artisan::call('arquivos:retention-cleanup', ['--limit' => 500]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Hard-deleted: 1');

    // Row DEVE ter sido removida do DB (hard-deleted)
    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row)->toBeNull();

    // File DEVE ter sido removido do disk
    expect(Storage::disk('local')->exists($path))->toBeFalse();

    // Audit log DEVE ter registro hard_delete pra este arquivo
    $audit = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $id)
        ->where('action', 'hard_delete')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->business_id)->toBe(1);

    $payload = json_decode($audit->payload, true);
    expect($payload['user_action'])->toBe('retention_cleanup_command');
    expect($payload['original_deleted_at'])->toBe($deletedAt);
    expect($payload['business_id'])->toBe(1);
});

// ---------------------------------------------------------------------------
// 6. --days=30 override funciona (rows com 60d soft-deleted são hard-deleted)
// ---------------------------------------------------------------------------

it('--days=30 override hard-deleta rows com 60 dias de deleted_at', function () {
    Storage::fake('local');

    $path60 = 'biz-1/2026/05/sessenta-dias.txt';
    $path20 = 'biz-1/2026/05/vinte-dias.txt';

    Storage::disk('local')->put($path60, 'conteudo-60d');
    Storage::disk('local')->put($path20, 'conteudo-20d');

    // Row deletada há 60 dias — deve ser purged com --days=30
    $id60 = insertArquivo([
        'disk'         => 'local',
        'storage_path' => $path60,
        'deleted_at'   => now()->subDays(60)->toDateTimeString(),
    ]);

    // Row deletada há 20 dias — deve ser preservada mesmo com --days=30
    $id20 = insertArquivo([
        'disk'         => 'local',
        'storage_path' => $path20,
        'deleted_at'   => now()->subDays(20)->toDateTimeString(),
    ]);

    $exitCode = Artisan::call('arquivos:retention-cleanup', [
        '--days'  => 30,
        '--limit' => 500,
    ]);

    expect($exitCode)->toBe(0);

    // Row 60d DEVE ter sido removida
    expect(DB::table('arquivos')->where('id', $id60)->first())->toBeNull();
    expect(Storage::disk('local')->exists($path60))->toBeFalse();

    // Row 20d DEVE ainda existir (abaixo do threshold de 30d)
    expect(DB::table('arquivos')->where('id', $id20)->first())->not->toBeNull();
    expect(Storage::disk('local')->exists($path20))->toBeTrue();
});

// ---------------------------------------------------------------------------
// 7. Multi-tenant: --business=1 só processa biz 1, biz 99 preservado
// ---------------------------------------------------------------------------

it('--business=1 processa só biz 1 e preserva biz 99', function () {
    Storage::fake('local');

    $pathBiz1  = 'biz-1/2026/05/biz1-antigo.txt';
    $pathBiz99 = 'biz-99/2026/05/biz99-antigo.txt';

    Storage::disk('local')->put($pathBiz1, 'conteudo-biz1');
    Storage::disk('local')->put($pathBiz99, 'conteudo-biz99');

    $idBiz1 = insertArquivo([
        'business_id'  => 1,
        'disk'         => 'local',
        'storage_path' => $pathBiz1,
        'deleted_at'   => now()->subDays(100)->toDateTimeString(),
    ]);

    $idBiz99 = insertArquivo([
        'business_id'  => 99,
        'disk'         => 'local',
        'storage_path' => $pathBiz99,
        'deleted_at'   => now()->subDays(100)->toDateTimeString(),
    ]);

    $exitCode = Artisan::call('arquivos:retention-cleanup', [
        '--business' => 1,
        '--limit'    => 500,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Hard-deleted: 1');

    // biz=1 DEVE ter sido hard-deleted
    expect(DB::table('arquivos')->where('id', $idBiz1)->first())->toBeNull();

    // biz=99 DEVE ter sido preservado (filtro --business=1)
    expect(DB::table('arquivos')->where('id', $idBiz99)->first())->not->toBeNull();

    // Cleanup manual do biz=99 pra não vazar pra outros testes
    DB::table('arquivos')->where('id', $idBiz99)->delete();
});

// ---------------------------------------------------------------------------
// 8. File ausente conta missing_file mas continua processando + DB hard-delete
// ---------------------------------------------------------------------------

it('file ausente conta missing_file mas faz DB hard-delete e insere audit log (orphan cleanup)', function () {
    Storage::fake('local');

    // Não cria o arquivo em disk — simula orphan (DB tem row, disk não tem file)
    $pathOrphan = 'biz-1/2026/05/orphan-' . uniqid() . '.txt';

    $id = insertArquivo([
        'disk'         => 'local',
        'storage_path' => $pathOrphan,
        'deleted_at'   => now()->subDays(100)->toDateTimeString(),
    ]);

    $exitCode = Artisan::call('arquivos:retention-cleanup', ['--limit' => 500]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('File ausente (orphan): 1');
    expect(Artisan::output())->toContain('Hard-deleted: 1');

    // DB DEVE ter sido hard-deleted mesmo com file ausente (orphan cleanup)
    $row = DB::table('arquivos')->where('id', $id)->first();
    expect($row)->toBeNull();

    // Audit log DEVE existir pra rastreio LGPD
    $audit = DB::table('arquivos_audit_log')
        ->where('arquivo_id', $id)
        ->where('action', 'hard_delete')
        ->first();

    expect($audit)->not->toBeNull();
    $payload = json_decode($audit->payload, true);
    expect($payload['file_removed_from_disk'])->toBeFalse();
    expect($payload['user_action'])->toBe('retention_cleanup_command');
});
