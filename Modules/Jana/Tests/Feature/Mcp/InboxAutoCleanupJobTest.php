<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\Jana\Jobs\Mcp\InboxAutoCleanupJob;

uses(Tests\TestCase::class);

/**
 * Bug #3 fix (2026-05-13) — auto-cleanup inbox notifications stale.
 *
 * Cria a tabela em memória (SQLite) pra simular o schema real
 * (migration usa enum, não funciona em SQLite — replicamos com string).
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::dropIfExists('mcp_inbox_notifications');
    Schema::create('mcp_inbox_notifications', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('user_id');
        $t->string('type', 40)->default('mention');
        $t->string('task_id', 40)->nullable();
        $t->unsignedBigInteger('actor_id')->nullable();
        $t->text('body')->nullable();
        $t->json('payload')->nullable();
        $t->timestamp('read_at')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_inbox_notifications');
});

/**
 * Helper — cria notification com idade controlada.
 */
function makeInboxNotification(int $userId, int $ageDays, ?\Carbon\Carbon $readAt = null): McpInboxNotification
{
    $n = McpInboxNotification::create([
        'user_id' => $userId,
        'type' => 'assigned',
        'task_id' => 'US-TEST-' . random_int(1000, 9999),
        'body' => "Assignment fake idade={$ageDays}d",
        'read_at' => $readAt,
    ]);

    // Backdate created_at — Eloquent default é now(), forçamos via update direto.
    \DB::table('mcp_inbox_notifications')
        ->where('id', $n->id)
        ->update([
            'created_at' => now()->subDays($ageDays),
            'updated_at' => now()->subDays($ageDays),
        ]);

    return $n->fresh();
}

test('InboxAutoCleanupJob marca read apenas as unread com mais de 7 dias', function () {
    // 3 stale (>7d, unread) — devem ser marcadas
    makeInboxNotification(userId: 1, ageDays: 8);
    makeInboxNotification(userId: 1, ageDays: 14);
    makeInboxNotification(userId: 2, ageDays: 30);

    // 2 recentes (<7d, unread) — devem ficar unread
    makeInboxNotification(userId: 1, ageDays: 1);
    makeInboxNotification(userId: 1, ageDays: 6);

    expect(McpInboxNotification::whereNull('read_at')->count())->toBe(5);

    (new InboxAutoCleanupJob)->handle();

    // 3 marcadas read, 2 permanecem unread
    expect(McpInboxNotification::whereNotNull('read_at')->count())->toBe(3)
        ->and(McpInboxNotification::whereNull('read_at')->count())->toBe(2);
});

test('InboxAutoCleanupJob é idempotente — segunda execução não muda nada', function () {
    makeInboxNotification(userId: 1, ageDays: 10);
    makeInboxNotification(userId: 1, ageDays: 20);
    makeInboxNotification(userId: 2, ageDays: 2);

    // Primeira execução marca 2 das 3
    (new InboxAutoCleanupJob)->handle();

    $readAtsApós1 = McpInboxNotification::whereNotNull('read_at')
        ->orderBy('id')
        ->pluck('read_at')
        ->all();

    expect(count($readAtsApós1))->toBe(2);

    // Avança 1s pra garantir que se houvesse re-update, read_at mudaria
    \Carbon\Carbon::setTestNow(now()->addMinute());

    // Segunda execução — não deve tocar nas já read; recente continua unread
    (new InboxAutoCleanupJob)->handle();

    $readAtsApós2 = McpInboxNotification::whereNotNull('read_at')
        ->orderBy('id')
        ->pluck('read_at')
        ->all();

    expect(count($readAtsApós2))->toBe(2)
        ->and((string) $readAtsApós2[0])->toBe((string) $readAtsApós1[0])
        ->and((string) $readAtsApós2[1])->toBe((string) $readAtsApós1[1])
        ->and(McpInboxNotification::whereNull('read_at')->count())->toBe(1);

    \Carbon\Carbon::setTestNow();
});

test('InboxAutoCleanupJob NÃO toca em notifications já read (preserva read_at original)', function () {
    $jaLida = makeInboxNotification(
        userId: 1,
        ageDays: 20,
        readAt: now()->subDays(15),
    );
    $readAtOriginal = (string) $jaLida->read_at;

    (new InboxAutoCleanupJob)->handle();

    $atual = McpInboxNotification::find($jaLida->id);
    expect((string) $atual->read_at)->toBe($readAtOriginal);
});

test('InboxAutoCleanupJob é multi-tenant safe (varre todos user_ids per-user)', function () {
    // 2 users diferentes, ambas stale — ambas devem ser marcadas
    makeInboxNotification(userId: 1, ageDays: 10);
    makeInboxNotification(userId: 99, ageDays: 10);

    (new InboxAutoCleanupJob)->handle();

    expect(McpInboxNotification::whereNotNull('read_at')->count())->toBe(2);
});

test('InboxAutoCleanupJob tem tags canônicas Horizon', function () {
    expect((new InboxAutoCleanupJob)->tags())
        ->toBe(['jana', 'mcp', 'inbox', 'auto-cleanup']);
});
