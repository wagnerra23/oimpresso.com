<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * R-WA-083 — GUARD tests pro `HandleInertiaRequests::sidebarCounts`.
 *
 * Atalho da Sidebar (Tarefas/Chat/Atendimento) usava counts hard-coded
 * mock ({6, 3, 2}). Wagner percebeu que o "2" do Atendimento não mudava
 * com unread real. Este PR (US-WA-083) wire counts reais via 3 queries:
 *
 *   - atendimento: SUM(conversations.unread_count) WHERE business_id=X
 *   - tarefas:     COUNT(mcp_tasks) WHERE owner=actor.slug AND status IN
 *                  ('todo','doing','blocked'); slug vem do
 *                  mcp_actors.user_id → users.id mapping
 *   - chat:        COUNT(mcp_inbox_notifications) WHERE user_id=X
 *                  AND read_at IS NULL
 *
 * Try/catch per-key — qualquer query individual que falhe vira 0 sem
 * travar render do shell. Tests aqui provam:
 *
 *   001. atendimento soma corretamente unread de várias conversations
 *        + ignora outras businesses (Tier 0 ADR 0093)
 *   002. tarefas conta apenas status ativos (todo/doing/blocked) do
 *        owner mapeado via actor.slug
 *   003. chat conta apenas notificações não lidas
 *   004. tabela ausente (módulo desinstalado) NÃO trava render — vira 0
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['conversations', 'channels', 'messages', 'mcp_tasks', 'mcp_actors', 'mcp_inbox_notifications'] as $t) {
        Schema::dropIfExists($t);
    }

    // Schema mínimo p/ os 3 counts. Campos extras omitidos.
    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamps();
    });

    Schema::create('mcp_actors', function ($table) {
        $table->bigIncrements('id');
        $table->string('slug', 60);
        $table->enum('type', ['human', 'ai_agent', 'service'])->default('human');
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamp('revoked_at')->nullable();
        $table->timestamps();
    });

    Schema::create('mcp_tasks', function ($table) {
        $table->bigIncrements('id');
        $table->string('task_id', 40);
        $table->string('module', 60)->nullable();
        $table->string('title', 255);
        $table->enum('status', ['backlog', 'todo', 'doing', 'review', 'done', 'blocked', 'cancelled'])->default('todo');
        $table->string('owner', 60)->nullable();
        $table->timestamps();
    });

    Schema::create('mcp_inbox_notifications', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('user_id');
        $table->string('type', 40)->default('mention');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
});

it('R-WA-083-001 — atendimento count soma unread das conversations do business + ignora cross-tenant', function () {
    // biz=1 (current): 3 + 0 + 5 = 8 unread
    \DB::table('conversations')->insert([
        ['business_id' => 1, 'unread_count' => 3, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'unread_count' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'unread_count' => 5, 'created_at' => now(), 'updated_at' => now()],
    ]);
    // biz=99 (cross-tenant — Tier 0 NÃO deve vazar): 100 unread
    \DB::table('conversations')->insert([
        ['business_id' => 99, 'unread_count' => 100, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $middleware = app(HandleInertiaRequests::class);
    $invoke = (new \ReflectionClass($middleware))->getMethod('sidebarCounts');
    $invoke->setAccessible(true);
    $counts = $invoke->invoke($middleware, 1, 42); // business=1 user=42

    expect($counts['atendimento'])->toBe(8); // NÃO 108 (cross-tenant filtered out)
});

it('R-WA-083-002 — tarefas conta apenas todo/doing/blocked do owner mapeado via actor.slug', function () {
    \DB::table('mcp_actors')->insert([
        'slug' => 'wagner',
        'type' => 'human',
        'user_id' => 42,
        'revoked_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \DB::table('mcp_actors')->insert([
        'slug' => 'eliana',
        'type' => 'human',
        'user_id' => 99,
        'revoked_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \DB::table('mcp_tasks')->insert([
        ['task_id' => 'US-1', 'title' => 'todo wagner', 'status' => 'todo', 'owner' => 'wagner', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-2', 'title' => 'doing wagner', 'status' => 'doing', 'owner' => 'wagner', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-3', 'title' => 'blocked wagner', 'status' => 'blocked', 'owner' => 'wagner', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-4', 'title' => 'done wagner (NAO conta)', 'status' => 'done', 'owner' => 'wagner', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-5', 'title' => 'review wagner (NAO conta)', 'status' => 'review', 'owner' => 'wagner', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-6', 'title' => 'cancelled wagner (NAO conta)', 'status' => 'cancelled', 'owner' => 'wagner', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => 'US-7', 'title' => 'todo eliana (NAO eh wagner)', 'status' => 'todo', 'owner' => 'eliana', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $middleware = app(HandleInertiaRequests::class);
    $invoke = (new \ReflectionClass($middleware))->getMethod('sidebarCounts');
    $invoke->setAccessible(true);
    $counts = $invoke->invoke($middleware, 1, 42); // user_id=42 → wagner

    expect($counts['tarefas'])->toBe(3); // 3 ativos do wagner (todo+doing+blocked), NAO 7
});

it('R-WA-083-003 — chat conta apenas notificacoes nao lidas do user', function () {
    \DB::table('mcp_inbox_notifications')->insert([
        ['user_id' => 42, 'type' => 'mention',    'read_at' => null,         'created_at' => now(), 'updated_at' => now()],
        ['user_id' => 42, 'type' => 'assigned',   'read_at' => null,         'created_at' => now(), 'updated_at' => now()],
        ['user_id' => 42, 'type' => 'commented',  'read_at' => now(),        'created_at' => now(), 'updated_at' => now()], // JA LIDA
        ['user_id' => 99, 'type' => 'mention',    'read_at' => null,         'created_at' => now(), 'updated_at' => now()], // OUTRO USER
    ]);

    $middleware = app(HandleInertiaRequests::class);
    $invoke = (new \ReflectionClass($middleware))->getMethod('sidebarCounts');
    $invoke->setAccessible(true);
    $counts = $invoke->invoke($middleware, 1, 42);

    expect($counts['chat'])->toBe(2); // 2 unread do user 42, NAO 3 (1 lida) NAO 4 (cross-user)
});

it('R-WA-083-004 — tabela ausente (modulo desinstalado) retorna 0 sem trava render', function () {
    // Drop one of the tables — simula módulo desinstalado / migration pendente
    Schema::dropIfExists('mcp_tasks');

    \DB::table('conversations')->insert([
        ['business_id' => 1, 'unread_count' => 7, 'created_at' => now(), 'updated_at' => now()],
    ]);
    \DB::table('mcp_inbox_notifications')->insert([
        ['user_id' => 42, 'type' => 'mention', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $middleware = app(HandleInertiaRequests::class);
    $invoke = (new \ReflectionClass($middleware))->getMethod('sidebarCounts');
    $invoke->setAccessible(true);

    // NÃO deve lançar exception
    $counts = $invoke->invoke($middleware, 1, 42);

    expect($counts['atendimento'])->toBe(7);
    expect($counts['tarefas'])->toBe(0);       // tabela ausente — try/catch retornou 0
    expect($counts['chat'])->toBe(1);
});

it('R-WA-083-005 — actor revoked NAO conta tasks (segurança: user banido perde acesso visual aos contadores)', function () {
    \DB::table('mcp_actors')->insert([
        'slug' => 'ex-funcionario',
        'type' => 'human',
        'user_id' => 42,
        'revoked_at' => now()->subDays(30), // revogado!
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \DB::table('mcp_tasks')->insert([
        ['task_id' => 'US-X', 'title' => 'doing ex', 'status' => 'doing', 'owner' => 'ex-funcionario', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $middleware = app(HandleInertiaRequests::class);
    $invoke = (new \ReflectionClass($middleware))->getMethod('sidebarCounts');
    $invoke->setAccessible(true);
    $counts = $invoke->invoke($middleware, 1, 42);

    // actor.revoked_at is not null → slug NÃO resolve → tarefas=0
    expect($counts['tarefas'])->toBe(0);
});
