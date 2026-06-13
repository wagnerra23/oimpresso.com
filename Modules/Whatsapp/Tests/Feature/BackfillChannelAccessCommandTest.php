<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Hotfix US-WA-068/069 — Backfill command pra restaurar acesso atendentes
 * após merge #655 (US-WA-069 ativou filtragem per-canal).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   1. Dry-run não persiste (preview)
 *   2. Idempotente — 2 runs não duplicam grants
 *   3. Cross-tenant: biz=1 não recebe grant pra user biz=99 (mesmo se user
 *      tem whatsapp.send)
 *   4. User sem permission whatsapp.send/access não é grantado
 *   5. Channel com revoked_at preexistente permite re-grant (UNIQUE composto)
 *   6. --business filter respeitado
 *   7. Channel sem nenhum user elegível → skip + warning, não erro fatal
 *
 * Pattern espelha ChannelUserAccessTest (PR #644) com adição de tabelas
 * Spatie (roles, permissions, model_has_*) necessárias pro filtro de perms.
 *
 * @see Modules\Whatsapp\Console\Commands\BackfillChannelAccessCommand
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach ([
        'model_has_permissions', 'model_has_roles', 'role_has_permissions',
        'permissions', 'roles',
        'channel_user_access', 'channels', 'users',
    ] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('username', 100)->nullable();
        $table->string('email', 100)->nullable();
        $table->unsignedInteger('business_id');
        $table->string('password')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->string('display_identifier', 100)->nullable();
        $table->text('config_json')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('channel_user_access', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('granted_by_user_id');
        $table->timestamp('granted_at');
        $table->timestamp('revoked_at')->nullable();
        $table->unsignedInteger('revoked_by_user_id')->nullable();
        $table->timestamps();
        $table->unique(
            ['channel_id', 'user_id', 'revoked_at'],
            'cua_channel_user_unq'
        );
        $table->index(['business_id', 'user_id'], 'cua_biz_user_idx');
    });

    // Spatie tables (versão minimal — só o necessário pro filtro de perms)
    Schema::create('permissions', function ($table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name')->default('web');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('roles', function ($table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name')->default('web');
        $table->unsignedInteger('business_id')->nullable();
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('model_has_permissions', function ($table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
        $table->index(['model_id', 'model_type']);
    });

    Schema::create('model_has_roles', function ($table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
        $table->index(['model_id', 'model_type']);
    });

    Schema::create('role_has_permissions', function ($table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    // Limpa cache Spatie entre testes
    app()->forgetInstance(\Spatie\Permission\PermissionRegistrar::class);
    if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // tolerante a env de teste sem cache configurado
        }
    }
});

/**
 * Cria User real persistido + atribui permission/role usando Spatie API.
 */
function bcacMakeUser(int $businessId, ?string $directPerm = null, ?string $rolePerm = null): \App\User
{
    $user = new \App\User();
    $user->username = 'u' . uniqid();
    $user->email = $user->username . '@test.local';
    $user->business_id = $businessId;
    $user->password = 'x';
    $user->save();

    if ($directPerm !== null) {
        $perm = Permission::firstOrCreate(['name' => $directPerm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    if ($rolePerm !== null) {
        $role = Role::firstOrCreate([
            'name' => 'Admin#' . $businessId,
            'guard_name' => 'web',
        ]);
        $perm = Permission::firstOrCreate(['name' => $rolePerm, 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);
    }

    return $user;
}

function bcacMakeChannel(int $businessId, string $label = 'Suporte'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => 'bcac-' . $businessId . '-' . uniqid(),
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

it('R-WA-BCAC-001 — dry-run não persiste grants', function () {
    $ch = bcacMakeChannel(1, 'Vendas');
    bcacMakeUser(1, 'whatsapp.send');

    expect(ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);

    $exit = Artisan::call('whatsapp:backfill-channel-access', [
        '--business' => '1',
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);
    expect(ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('R-WA-BCAC-002 — idempotente: 2 runs não duplicam grants', function () {
    $ch = bcacMakeChannel(1);
    bcacMakeUser(1, 'whatsapp.send');

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);
    $firstCount = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count();
    expect($firstCount)->toBe(1);

    // 2ª run — não duplica
    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);
    $secondCount = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count();
    expect($secondCount)->toBe(1);
});

it('R-WA-BCAC-003 — multi-tenant: biz=1 canal não recebe grant de user biz=99', function () {
    $ch1 = bcacMakeChannel(1, 'Suporte biz1');
    $ch99 = bcacMakeChannel(99, 'Suporte biz99');

    $u1 = bcacMakeUser(1, 'whatsapp.send');
    $u99 = bcacMakeUser(99, 'whatsapp.send');

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    $grants = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($grants)->toHaveCount(1);
    expect($grants->first()->business_id)->toBe(1);
    expect($grants->first()->user_id)->toBe($u1->id);
    expect($grants->first()->channel_id)->toBe($ch1->id);
});

it('R-WA-BCAC-004 — user sem whatsapp.send/access não é grantado', function () {
    $ch = bcacMakeChannel(1);
    // User sem nenhuma permission Whatsapp
    bcacMakeUser(1);
    // User com permission unrelated não conta
    bcacMakeUser(1, 'sells.view');

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    expect(ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('R-WA-BCAC-005 — user com whatsapp.access (não só .send) também é grantado', function () {
    $ch = bcacMakeChannel(1);
    bcacMakeUser(1, 'whatsapp.access');

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    expect(ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(1);
});

it('R-WA-BCAC-006 — permission via role (Admin#biz) também é detectada', function () {
    $ch = bcacMakeChannel(1);
    // Permission só via role, não direto no user
    bcacMakeUser(1, null, 'whatsapp.send');

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    expect(ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(1);
});

it('R-WA-BCAC-007 — channel com revoked_at != null permite re-grant', function () {
    $ch = bcacMakeChannel(1);
    $u = bcacMakeUser(1, 'whatsapp.send');

    // Estado pré-existente: row revoked (history preservation)
    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_id' => $ch->id,
        'user_id' => $u->id,
        'granted_by_user_id' => 1,
        'granted_at' => now()->subDay(),
        'revoked_at' => now()->subHour(),
        'revoked_by_user_id' => 1,
    ]);

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    $allRows = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($allRows)->toHaveCount(2); // 1 revoked + 1 novo ativo

    $active = $allRows->whereNull('revoked_at');
    expect($active)->toHaveCount(1);
});

it('R-WA-BCAC-008 — channel sem users elegíveis: skip canal + warning, exit 0', function () {
    bcacMakeChannel(1, 'Canal órfão');
    // Nenhum user com permission Whatsapp

    $exit = Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    expect($exit)->toBe(0); // não falha
    expect(ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('R-WA-BCAC-009 — --business=all processa múltiplos businesses', function () {
    $ch1 = bcacMakeChannel(1);
    $ch99 = bcacMakeChannel(99);
    bcacMakeUser(1, 'whatsapp.send');
    bcacMakeUser(99, 'whatsapp.send');

    Artisan::call('whatsapp:backfill-channel-access'); // default = all

    $grants = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($grants)->toHaveCount(2);
    expect($grants->pluck('business_id')->sort()->values()->all())->toBe([1, 99]);
});

it('R-WA-BCAC-010 — --business=X inválido retorna FAILURE sem persistir', function () {
    bcacMakeChannel(1);
    bcacMakeUser(1, 'whatsapp.send');

    $exit = Artisan::call('whatsapp:backfill-channel-access', ['--business' => '0']);

    expect($exit)->toBe(1); // FAILURE
    expect(ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('R-WA-BCAC-011 — granted_by_user_id = 0 (sentinel system)', function () {
    $ch = bcacMakeChannel(1);
    bcacMakeUser(1, 'whatsapp.send');

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    $row = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($row->granted_by_user_id)->toBe(0);
});
