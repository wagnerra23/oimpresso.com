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
 * Auditor de conflitos channel_user_access (Wagner 2026-06-13 "removido/reativado por?").
 *
 * GUARD tests Tier 0 (ADR 0093 + ADR 0135) do `whatsapp:audit-channel-access`:
 *
 *   DUP_ATIVO    : >1 grant ativo no mesmo (canal,user) — detecta + --fix revoga extras.
 *   FLIP_BACKFILL: grant system (granted_by=0) reativando revoke humano — detecta + --fix.
 *   Backfill respeita tombstone humano (causa-raiz do flip) salvo --force.
 *
 * Pattern espelha BackfillChannelAccessCommandTest (schema sintético manual +
 * quarentena era-sqlite) — seeds via DB::table (sem activitylog), --fix via raw
 * update (idem). burn-down SDD converte pra MySQL persistente depois.
 *
 * @see Modules\Whatsapp\Console\Commands\AuditChannelAccessCommand
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
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    // channel_user_access COM o UNIQUE ANTIGO (channel_id,user_id,revoked_at) —
    // o bug que o auditor caça: NULLs distintos deixam 2 ativos coexistirem.
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
        $table->unique(['channel_id', 'user_id', 'revoked_at'], 'cua_channel_user_unq');
        $table->index(['business_id', 'user_id'], 'cua_biz_user_idx');
    });

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

    app()->forgetInstance(\Spatie\Permission\PermissionRegistrar::class);
    if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // tolerante a env de teste sem cache
        }
    }
});

function acacChannel(int $businessId, string $label = 'Suporte'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => 'acac-' . $businessId . '-' . uniqid(),
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

/** Insere grant via raw DB (sem disparar activitylog) com defaults seguros. */
function acacGrant(array $attrs): int
{
    return (int) DB::table('channel_user_access')->insertGetId(array_merge([
        'granted_at' => now(),
        'granted_by_user_id' => 1,
        'revoked_at' => null,
        'revoked_by_user_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs));
}

function acacUser(int $businessId, ?string $directPerm = null, ?string $rolePerm = null): \App\User
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
        $role = Role::firstOrCreate(['name' => 'Admin#' . $businessId, 'guard_name' => 'web']);
        $perm = Permission::firstOrCreate(['name' => $rolePerm, 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);
    }

    return $user;
}

function acacActiveCount(int $channelId, int $userId): int
{
    return (int) DB::table('channel_user_access')
        ->where('channel_id', $channelId)
        ->where('user_id', $userId)
        ->whereNull('revoked_at')
        ->count();
}

// ---------------------------------------------------------------------------
// DUP_ATIVO
// ---------------------------------------------------------------------------

it('R-WA-ACAC-001 — relatório detecta duplicado ativo SEM persistir mudança', function () {
    $ch = acacChannel(1);
    acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]);
    acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]); // 2º ativo (bug)

    expect(acacActiveCount($ch->id, 10))->toBe(2);

    $exit = Artisan::call('whatsapp:audit-channel-access', ['--business' => '1']);

    expect($exit)->toBe(0);
    // Sem --fix → nada muda
    expect(acacActiveCount($ch->id, 10))->toBe(2);
});

it('R-WA-ACAC-002 — --fix revoga o duplicado mantendo o maior id; idempotente', function () {
    $ch = acacChannel(1);
    $first = acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]);
    $second = acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]);

    Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--fix' => true]);

    expect(acacActiveCount($ch->id, 10))->toBe(1);
    // Mantém o maior id (mais recente)
    $stillActive = DB::table('channel_user_access')->whereNull('revoked_at')->first();
    expect((int) $stillActive->id)->toBe($second);
    // Revogado pelo system (0)
    $revoked = DB::table('channel_user_access')->where('id', $first)->first();
    expect((int) $revoked->revoked_by_user_id)->toBe(0);
    expect($revoked->revoked_at)->not->toBeNull();

    // 2ª run = nada a fazer
    Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--fix' => true]);
    expect(acacActiveCount($ch->id, 10))->toBe(1);
});

// ---------------------------------------------------------------------------
// FLIP_BACKFILL
// ---------------------------------------------------------------------------

it('R-WA-ACAC-003 — detecta flip: revoke humano + grant system ativo', function () {
    $ch = acacChannel(1);
    // Admin revogou (humano)
    acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 7, 'granted_at' => now()->subDay(),
        'revoked_at' => now()->subHour(), 'revoked_by_user_id' => 7,
    ]);
    // Backfill reativou (system)
    acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 0,
    ]);

    $exit = Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0);
    expect($report['summary']['flip_grants'])->toBe(1);
    expect($report['summary']['dup_groups'])->toBe(0);
});

it('R-WA-ACAC-004 — --fix revoga o grant system do flip (restaura intenção humana)', function () {
    $ch = acacChannel(1);
    acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 7, 'granted_at' => now()->subDay(),
        'revoked_at' => now()->subHour(), 'revoked_by_user_id' => 7,
    ]);
    $systemGrant = acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 0,
    ]);

    Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--fix' => true]);

    expect(acacActiveCount($ch->id, 10))->toBe(0);
    $row = DB::table('channel_user_access')->where('id', $systemGrant)->first();
    expect($row->revoked_at)->not->toBeNull();
    expect((int) $row->revoked_by_user_id)->toBe(0);
});

it('R-WA-ACAC-005 — backfill legítimo (system, sem revoke humano) NÃO é flip', function () {
    $ch = acacChannel(1);
    // Grant system normal, ninguém revogou antes
    acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 0,
    ]);

    $exit = Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0);
    expect($report['summary']['flip_grants'])->toBe(0);
});

it('R-WA-ACAC-006 — multi-tenant: --business=1 não toca conflito de biz=99', function () {
    $ch99 = acacChannel(99);
    acacGrant(['business_id' => 99, 'channel_id' => $ch99->id, 'user_id' => 20]);
    acacGrant(['business_id' => 99, 'channel_id' => $ch99->id, 'user_id' => 20]); // dup biz 99

    Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--fix' => true]);

    // biz=99 intacto (escopo --business=1)
    expect(acacActiveCount($ch99->id, 20))->toBe(2);
});

it('R-WA-ACAC-007 — --json emite summary estruturado', function () {
    $ch = acacChannel(1);
    acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]);
    acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]);

    Artisan::call('whatsapp:audit-channel-access', ['--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report)->toHaveKeys(['business_filter', 'fix', 'dup_active', 'flip_backfill', 'summary']);
    expect($report['summary']['dup_groups'])->toBe(1);
    expect($report['dup_active'][0])->toHaveKeys(['channel_id', 'user_id', 'keep_id', 'revoke_ids']);
});

it('R-WA-ACAC-008 — sem conflitos: exit 0, summary zerado', function () {
    $ch = acacChannel(1);
    acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]); // 1 só

    $exit = Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0);
    expect($report['summary']['dup_groups'])->toBe(0);
    expect($report['summary']['flip_grants'])->toBe(0);
});

it('R-WA-ACAC-009 — --strict com conflito e sem --fix retorna FAILURE', function () {
    $ch = acacChannel(1);
    acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]);
    acacGrant(['business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10]);

    $exit = Artisan::call('whatsapp:audit-channel-access', ['--business' => '1', '--strict' => true]);

    expect($exit)->toBe(1);
});

// ---------------------------------------------------------------------------
// Causa-raiz: backfill respeita revoke humano
// ---------------------------------------------------------------------------

it('R-WA-ACAC-010 — backfill NÃO re-concede a quem teve revoke humano (tombstone)', function () {
    $ch = acacChannel(1);
    $u = acacUser(1, 'whatsapp.send');

    // Admin revogou de propósito (humano)
    acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => $u->id,
        'granted_by_user_id' => 7, 'granted_at' => now()->subDay(),
        'revoked_at' => now()->subHour(), 'revoked_by_user_id' => 7,
    ]);

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    // Continua sem grant ativo — backfill respeitou o tombstone
    expect(acacActiveCount($ch->id, $u->id))->toBe(0);
});

it('R-WA-ACAC-011 — backfill --force re-concede mesmo com revoke humano', function () {
    $ch = acacChannel(1);
    $u = acacUser(1, 'whatsapp.send');

    acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => $u->id,
        'granted_by_user_id' => 7, 'granted_at' => now()->subDay(),
        'revoked_at' => now()->subHour(), 'revoked_by_user_id' => 7,
    ]);

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1', '--force' => true]);

    expect(acacActiveCount($ch->id, $u->id))->toBe(1);
});

it('R-WA-ACAC-012 — backfill re-concede quando o revoke foi SYSTEM (não humano)', function () {
    $ch = acacChannel(1);
    $u = acacUser(1, 'whatsapp.send');

    // Revoke pelo system (0) — não é tombstone humano
    acacGrant([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => $u->id,
        'granted_by_user_id' => 0, 'granted_at' => now()->subDay(),
        'revoked_at' => now()->subHour(), 'revoked_by_user_id' => 0,
    ]);

    Artisan::call('whatsapp:backfill-channel-access', ['--business' => '1']);

    expect(acacActiveCount($ch->id, $u->id))->toBe(1);
});
