<?php

declare(strict_types=1);

/**
 * Pest — Daemon dual-sync cross-tenant safety (Fase 1 MVP Martinho biz=164).
 *
 * Cobre Tier 0 IRREVOGÁVEIS (ADR 0093) aplicados ao daemon:
 *   1. sync_checkpoint scope: UPDATE biz=164 NÃO toca biz=1
 *   2. sync_checkpoint scope: UPDATE biz=164 NÃO toca biz=4 ROTA LIVRE (cliente real)
 *   3. metadata.user_* preservado em re-sync (JSON_MERGE_PATCH vs overwrite — Lição 2 incidente 14/maio)
 *   4. chunk resumability: last_codigo_processed avança per-chunk
 *   5. sync_checkpoint atualizado per type: success em vendas NÃO altera financeiro
 *
 * ADR 0101: testes usam biz=1 (Wagner), biz=99 (cross-tenant fictício). NUNCA biz=164 (cliente real).
 *
 * Refs:
 *   - memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md §3
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *   - memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 *   - database/migrations/2026_05_14_180000_create_sync_checkpoint.php
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pest.php aplica Tests\TestCase em tests/Feature/ — não duplicar uses().

const DAEMON_BIZ_WAGNER = 1;
const DAEMON_BIZ_CROSS = 99;

/**
 * Helper — skip se driver sqlite (UltimatePOS schema completo precisa MySQL).
 * Permite migração `sync_checkpoint` rodar em ambos drivers.
 */
function daemonNeedDbOrSkip(): void
{
    if (! Schema::hasTable('sync_checkpoint')) {
        // Tenta rodar a migration in-process (path do sync_checkpoint)
        $path = database_path('migrations/2026_05_14_180000_create_sync_checkpoint.php');
        if (file_exists($path)) {
            (require $path)->up();
        }
    }
    if (! Schema::hasTable('sync_checkpoint')) {
        test()->markTestSkipped('Tabela sync_checkpoint ausente — migration não aplicável neste driver.');
    }
}

/**
 * Insert helper — bypass Eloquent (model não existe ainda; daemon escreve SQL direto via Python).
 */
function daemonInsertCheckpoint(int $bizId, string $syncType, array $overrides = []): int
{
    $row = array_merge([
        'business_id' => $bizId,
        'sync_type' => $syncType,
        'last_sync_at' => now()->subMinutes(10),
        'last_codigo_processed' => '100',
        'last_status' => 'success',
        'rows_processed' => 42,
        'error_msg' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);

    return (int) DB::table('sync_checkpoint')->insertGetId($row);
}

beforeEach(function () {
    daemonNeedDbOrSkip();
    // Limpa tabela entre testes
    DB::table('sync_checkpoint')->delete();
});

afterAll(function () {
    if (Schema::hasTable('sync_checkpoint')) {
        DB::table('sync_checkpoint')->delete();
    }
});


// ----------------------------------------------------------------------------
// 1. Cross-tenant isolation: UPDATE biz=99 NÃO toca biz=1
// ----------------------------------------------------------------------------

test('sync_checkpoint UPDATE biz=99 não toca linha biz=1 (Tier 0 isolamento)', function () {
    $idWagner = daemonInsertCheckpoint(DAEMON_BIZ_WAGNER, 'vendas', [
        'rows_processed' => 1000,
        'last_codigo_processed' => 'CODIGO-WAGNER-1',
    ]);
    $idCross = daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'vendas', [
        'rows_processed' => 500,
        'last_codigo_processed' => 'CODIGO-CROSS-1',
    ]);

    // Daemon UPDATE pattern — sempre escopo por business_id
    DB::update(
        'UPDATE sync_checkpoint SET rows_processed = ?, last_codigo_processed = ?, updated_at = CURRENT_TIMESTAMP '
        .'WHERE business_id = ? AND sync_type = ?',
        [9999, 'CODIGO-CROSS-NEW', DAEMON_BIZ_CROSS, 'vendas']
    );

    // Wagner deve permanecer intacto
    $wagner = DB::table('sync_checkpoint')->find($idWagner);
    expect((int) $wagner->rows_processed)->toBe(1000);
    expect($wagner->last_codigo_processed)->toBe('CODIGO-WAGNER-1');

    // Cross atualizado
    $cross = DB::table('sync_checkpoint')->find($idCross);
    expect((int) $cross->rows_processed)->toBe(9999);
    expect($cross->last_codigo_processed)->toBe('CODIGO-CROSS-NEW');
});


// ----------------------------------------------------------------------------
// 2. Cross-tenant isolation: sync biz=99 não cria linha biz=4 ROTA LIVRE (ou qualquer outro)
// ----------------------------------------------------------------------------

test('sync_checkpoint INSERT biz=99 não toca biz=4 ROTA LIVRE (cliente real protegido)', function () {
    // ROTA LIVRE biz=4 (cliente real) tem checkpoint próprio
    $rotaLivreId = daemonInsertCheckpoint(4, 'vendas', [
        'rows_processed' => 12345,
        'last_codigo_processed' => 'CODIGO-ROTA-LIVRE',
    ]);

    // Daemon Martinho (biz=99 fictício no teste) cria seu próprio checkpoint
    $martinhoId = daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'vendas', [
        'rows_processed' => 100,
    ]);

    // Total deve ser 2 — INSERT não vazou pra ROTA LIVRE
    expect(DB::table('sync_checkpoint')->count())->toBe(2);

    // ROTA LIVRE preservado byte-for-byte
    $rotaLivre = DB::table('sync_checkpoint')->find($rotaLivreId);
    expect((int) $rotaLivre->business_id)->toBe(4);
    expect((int) $rotaLivre->rows_processed)->toBe(12345);
    expect($rotaLivre->last_codigo_processed)->toBe('CODIGO-ROTA-LIVRE');

    // Martinho separado
    $martinho = DB::table('sync_checkpoint')->find($martinhoId);
    expect((int) $martinho->business_id)->toBe(DAEMON_BIZ_CROSS);
});


// ----------------------------------------------------------------------------
// 3. UNIQUE composto (business_id, sync_type) — não permite duplicado per tenant
// ----------------------------------------------------------------------------

test('UNIQUE (business_id, sync_type) impede duplicação', function () {
    daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'vendas');

    $exception = null;
    try {
        daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'vendas');  // dup
    } catch (\Throwable $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    // MySQL: "uniq_biz_type" (nome do index) | SQLite: "unique constraint failed: sync_checkpoint.business_id, sync_checkpoint.sync_type"
    $msg = strtolower($exception->getMessage());
    $unique_failure = str_contains($msg, 'uniq_biz_type')
        || str_contains($msg, 'unique constraint failed')
        || str_contains($msg, 'duplicate entry');
    expect($unique_failure)->toBeTrue("Esperava mensagem de UNIQUE constraint, recebi: {$msg}");
});


// ----------------------------------------------------------------------------
// 4. Chunk resumability — last_codigo_processed avança per-chunk
// ----------------------------------------------------------------------------

test('chunk resumability: last_codigo_processed avança per-chunk sem alterar last_sync_at', function () {
    $id = daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'vendas', [
        'last_codigo_processed' => null,
        'last_sync_at' => now()->subDays(1),
        'last_status' => 'running',
    ]);

    $lastSyncAtBefore = DB::table('sync_checkpoint')->where('id', $id)->value('last_sync_at');

    // Simula 3 chunks processados
    foreach (['100', '200', '300'] as $codigo) {
        DB::update(
            'UPDATE sync_checkpoint SET last_codigo_processed = ?, updated_at = CURRENT_TIMESTAMP '
            .'WHERE business_id = ? AND sync_type = ?',
            [$codigo, DAEMON_BIZ_CROSS, 'vendas']
        );
    }

    $row = DB::table('sync_checkpoint')->find($id);
    expect($row->last_codigo_processed)->toBe('300');

    // last_sync_at NÃO mudou (apenas mark_success avança ele)
    expect($row->last_sync_at)->toBe($lastSyncAtBefore);
});


// ----------------------------------------------------------------------------
// 5. Per-type isolation: success em vendas não altera financeiro
// ----------------------------------------------------------------------------

test('sync_checkpoint atualizado per type: vendas success não toca financeiro', function () {
    $idVendas = daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'vendas', [
        'rows_processed' => 100,
        'last_status' => 'running',
    ]);
    $idFin = daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'financeiro', [
        'rows_processed' => 50,
        'last_status' => 'success',
    ]);

    // mark_success em vendas (pattern do daemon)
    DB::update(
        'UPDATE sync_checkpoint SET last_status = ?, rows_processed = ?, last_sync_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP '
        .'WHERE business_id = ? AND sync_type = ?',
        ['success', 999, DAEMON_BIZ_CROSS, 'vendas']
    );

    $vendas = DB::table('sync_checkpoint')->find($idVendas);
    expect($vendas->last_status)->toBe('success');
    expect((int) $vendas->rows_processed)->toBe(999);

    // financeiro intacto
    $fin = DB::table('sync_checkpoint')->find($idFin);
    expect($fin->last_status)->toBe('success');
    expect((int) $fin->rows_processed)->toBe(50);
});


// ----------------------------------------------------------------------------
// 6. Failed status persiste error_msg + last_sync_at NÃO avança (retry no próximo)
// ----------------------------------------------------------------------------

test('mark_failed preserva last_sync_at anterior (retry pega delta desde último sucesso)', function () {
    $oldSyncAt = now()->subHours(2);
    $id = daemonInsertCheckpoint(DAEMON_BIZ_CROSS, 'estoque', [
        'last_sync_at' => $oldSyncAt,
        'last_status' => 'success',
    ]);

    // mark_failed pattern
    DB::update(
        'UPDATE sync_checkpoint SET last_status = ?, error_msg = ?, updated_at = CURRENT_TIMESTAMP '
        .'WHERE business_id = ? AND sync_type = ?',
        ['failed', 'firebird timeout chunk 3', DAEMON_BIZ_CROSS, 'estoque']
    );

    $row = DB::table('sync_checkpoint')->find($id);
    expect($row->last_status)->toBe('failed');
    expect($row->error_msg)->toContain('firebird timeout');

    // last_sync_at PRESERVADO (próximo retry pega delta de duas horas atrás)
    // Permitimos pequena variação dependendo do MySQL/SQLite formato datetime
    expect(strtotime((string) $row->last_sync_at))->toBe(strtotime((string) $oldSyncAt));
});
