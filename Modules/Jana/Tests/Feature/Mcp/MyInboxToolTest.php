<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response as McpResponse;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\Jana\Mcp\Tools\MyInboxTool;

uses(Tests\TestCase::class);

/**
 * Bug #3 fix (2026-05-13) — MyInboxTool agora default `mark_read=true`.
 *
 * Antes: notifications acumulavam unread (33+ stale após 1 semana).
 * Agora: ler = consumir (igual email). Flag `keep_unread:true` escapa pra
 * Wagner querer só espiar.
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Schema mínimo da inbox — replica migration (enum em SQLite não rola).
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

    // Token MCP fake injetado em request()->attributes (formato canônico
    // que o MyInboxTool::handle() espera — ADR 0081 Identity Mesh).
    request()->attributes->set('mcp_token', (object) [
        'user_id' => 1,
        'actor_id' => null,
    ]);
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_inbox_notifications');
    request()->attributes->remove('mcp_token');
});

function makeUnreadNotification(int $userId, string $body = 'fake assignment'): McpInboxNotification
{
    return McpInboxNotification::create([
        'user_id' => $userId,
        'type' => 'assigned',
        'task_id' => 'US-TEST-' . random_int(1000, 9999),
        'body' => $body,
        'read_at' => null,
    ]);
}

function callMyInboxTool(array $params = []): McpResponse
{
    $tool = new MyInboxTool;
    $request = new McpRequest($params);

    return $tool->handle($request);
}

test('MyInboxTool default (sem params) marca todas retornadas como lidas — Bug #3 fix', function () {
    makeUnreadNotification(userId: 1, body: 'fake 1');
    makeUnreadNotification(userId: 1, body: 'fake 2');
    makeUnreadNotification(userId: 1, body: 'fake 3');

    expect(McpInboxNotification::whereNull('read_at')->count())->toBe(3);

    callMyInboxTool();

    // Todas viraram read automático (mark_read default true)
    expect(McpInboxNotification::whereNotNull('read_at')->count())->toBe(3)
        ->and(McpInboxNotification::whereNull('read_at')->count())->toBe(0);
});

test('MyInboxTool com keep_unread:true preserva unread (modo espiar)', function () {
    makeUnreadNotification(userId: 1, body: 'preservar 1');
    makeUnreadNotification(userId: 1, body: 'preservar 2');

    expect(McpInboxNotification::whereNull('read_at')->count())->toBe(2);

    callMyInboxTool(['keep_unread' => true]);

    // Continuam unread
    expect(McpInboxNotification::whereNull('read_at')->count())->toBe(2)
        ->and(McpInboxNotification::whereNotNull('read_at')->count())->toBe(0);
});

test('MyInboxTool com mark_read:false explícito também preserva unread (back-compat)', function () {
    makeUnreadNotification(userId: 1, body: 'fake');
    makeUnreadNotification(userId: 1, body: 'fake 2');

    callMyInboxTool(['mark_read' => false]);

    // mark_read explicit false vence — preserva
    expect(McpInboxNotification::whereNull('read_at')->count())->toBe(2);
});

test('MyInboxTool com keep_unread:true vence mark_read:true (override)', function () {
    makeUnreadNotification(userId: 1, body: 'fake');

    callMyInboxTool(['mark_read' => true, 'keep_unread' => true]);

    // keep_unread tem precedência — não consome
    expect(McpInboxNotification::whereNull('read_at')->count())->toBe(1);
});

test('MyInboxTool inbox vazia retorna mensagem amigável (sem crash)', function () {
    $response = callMyInboxTool();

    expect((string) $response->content())->toContain('Inbox vazia');
});

test('MyInboxTool isola user_id (multi-user safety)', function () {
    // Notification de outro user — não deve aparecer nem ser tocada
    makeUnreadNotification(userId: 99, body: 'do user 99');
    makeUnreadNotification(userId: 1, body: 'do user 1');

    callMyInboxTool();

    // user=1 (token) virou read; user=99 permanece unread
    expect(McpInboxNotification::where('user_id', 1)->whereNotNull('read_at')->count())->toBe(1)
        ->and(McpInboxNotification::where('user_id', 99)->whereNull('read_at')->count())->toBe(1);
});
