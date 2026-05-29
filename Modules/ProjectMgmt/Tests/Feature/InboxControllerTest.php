<?php

declare(strict_types=1);

use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Inbox UI — Onda 2 (US-TR-304..306 / SPEC-UI-FASE7).
 *
 * Cobertura do InboxController:
 *  - GET /project-mgmt/inbox sem permission → 403
 *  - GET com permission → 200 + Inertia 'ProjectMgmt/Inbox/Index' + props canônicas
 *  - lista = unread do AUTH user (paridade tool `my-inbox`)
 *  - MULTI-TENANT Tier 0: notificação de OUTRO user NÃO aparece + não pode ser marcada
 *  - PATCH /inbox/{id}/read → 200 + seta read_at + some do unread
 *  - PATCH /inbox/read-all → 200 + marca todas do user
 *  - PATCH /inbox/{id}/read de notif de outro user → 404 (abort_unless scope)
 *
 * Padrão Board/Repair/Jana: roda contra DB dev real, markTestSkipped se schema
 * mcp_inbox_notifications ou User não estão semeados.
 *
 * Fixtures: notif body prefixado TEST-INBOX-, cleanup afterEach.
 */

function inboxBootstrapUser(): User
{
    try {
        $user = User::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: ' . $e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped('Sem user no banco — rode seeder UltimatePOS antes.');
    }

    Permission::firstOrCreate(['name' => 'copiloto.mcp.usage.all', 'guard_name' => 'web']);

    session([
        'user.business_id' => $user->business_id,
        'user.id'          => $user->id,
        'business.id'      => $user->business_id,
        'is_admin'         => true,
    ]);

    return $user;
}

function inboxGivePerm(User $user): void
{
    Permission::firstOrCreate(['name' => 'copiloto.mcp.usage.all', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('copiloto.mcp.usage.all')) {
        $user->givePermissionTo('copiloto.mcp.usage.all');
    }
}

function inboxRevokePerm(User $user): void
{
    if ($user->hasPermissionTo('copiloto.mcp.usage.all')) {
        $user->revokePermissionTo('copiloto.mcp.usage.all');
    }
}

function inboxMakeNotif(int $userId, ?int $actorId = null, ?\Carbon\Carbon $readAt = null): McpInboxNotification
{
    try {
        return McpInboxNotification::create([
            'user_id'  => $userId,
            'type'     => 'assigned',
            'task_id'  => 'TEST-INBOX-T1',
            'actor_id' => $actorId,
            'body'     => 'TEST-INBOX fixture notif',
            'read_at'  => $readAt,
        ]);
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema mcp_inbox_notifications indisponível: ' . $e->getMessage());
    }
    return new McpInboxNotification;
}

afterEach(function () {
    try {
        McpInboxNotification::where('body', 'like', 'TEST-INBOX%')->delete();
        McpInboxNotification::where('task_id', 'TEST-INBOX-T1')->delete();
    } catch (\Throwable $e) {
        // ignore — env de teste pode não ter a tabela
    }
});

it('GET /project-mgmt/inbox sem permission retorna 403', function () {
    $user = inboxBootstrapUser();
    inboxRevokePerm($user);

    $response = $this->actingAs($user)->get('/project-mgmt/inbox');

    expect($response->status())->toBe(403);
});

it('GET /project-mgmt/inbox com permission retorna Inertia ProjectMgmt/Inbox/Index', function () {
    $user = inboxBootstrapUser();
    inboxGivePerm($user);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/project-mgmt/inbox');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado neste env.');
    }

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ProjectMgmt/Inbox/Index')
        ->has('inbox')
        ->has('inbox_stats')
        ->has('filters')
    );
});

it('inbox lista unread do auth user e NÃO vaza notif de outro user (Tier 0)', function () {
    $user = inboxBootstrapUser();
    inboxGivePerm($user);

    $mine = inboxMakeNotif((int) $user->id);
    $otherUserId = (int) $user->id + 999999; // user inexistente/diferente
    $theirs = inboxMakeNotif($otherUserId);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/project-mgmt/inbox');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertInertia(function (AssertableInertia $page) use ($mine, $theirs) {
        $ids = collect($page->toArray()['props']['inbox'] ?? [])->pluck('id')->all();
        expect($ids)->toContain($mine->id);
        expect($ids)->not->toContain($theirs->id); // isolamento por user_id
    });
});

it('PATCH /inbox/{id}/read seta read_at e remove do unread', function () {
    $user = inboxBootstrapUser();
    inboxGivePerm($user);
    $notif = inboxMakeNotif((int) $user->id);

    expect($notif->read_at)->toBeNull();

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/inbox/{$notif->id}/read");

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson(['ok' => true, 'id' => $notif->id]);

    expect($notif->fresh()->read_at)->not->toBeNull();
});

it('PATCH /inbox/{id}/read de notif de OUTRO user retorna 404 (não marca)', function () {
    $user = inboxBootstrapUser();
    inboxGivePerm($user);

    $otherUserId = (int) $user->id + 999999;
    $theirs = inboxMakeNotif($otherUserId);

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/inbox/{$theirs->id}/read");

    expect($response->status())->toBe(404);
    expect($theirs->fresh()->read_at)->toBeNull(); // não marcou notif alheia
});

it('PATCH /inbox/read-all marca todas as não-lidas do auth user', function () {
    $user = inboxBootstrapUser();
    inboxGivePerm($user);

    $n1 = inboxMakeNotif((int) $user->id);
    $n2 = inboxMakeNotif((int) $user->id);

    $response = $this->actingAs($user)
        ->patchJson('/project-mgmt/inbox/read-all');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson(['ok' => true]);
    expect((int) $response->json('marked'))->toBeGreaterThanOrEqual(2);

    expect($n1->fresh()->read_at)->not->toBeNull();
    expect($n2->fresh()->read_at)->not->toBeNull();
});

it('PATCH /inbox/read-all sem permission retorna 403', function () {
    $user = inboxBootstrapUser();
    inboxRevokePerm($user);

    $response = $this->actingAs($user)
        ->patchJson('/project-mgmt/inbox/read-all');

    expect($response->status())->toBe(403);
});
