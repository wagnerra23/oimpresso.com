<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Hotfix — RegisterWhatsappPermissionsCommand.
 *
 * Cobre os cenários canônicos:
 *  R-WA-RWP-001 — registra 6 permissions em tabela vazia
 *  R-WA-RWP-002 — idempotência: 2 runs não duplicam permissions
 *  R-WA-RWP-003 — --business=1 atribui ao Admin#1
 *  R-WA-RWP-004 — --business=all atribui pra todos Admin#{biz} existentes
 *  R-WA-RWP-005 — business sem Admin#{biz} → skip + warning (não cria role)
 *  R-WA-RWP-006 — --dry-run não persiste
 *  R-WA-RWP-007 — --with-backfill encadeia outro comando (smoke artisan call)
 *  R-WA-RWP-008 — Tier 0: Permission é global; Role tem business_id
 *  R-WA-RWP-009 — --business=0 (inválido) retorna FAILURE
 *
 * Schema mirror produção: permissions (global), roles (business_id), pivots.
 *
 * @see Modules\Whatsapp\Console\Commands\RegisterWhatsappPermissionsCommand
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach ([
        'model_has_permissions', 'model_has_roles', 'role_has_permissions',
        'permissions', 'roles',
        'business',
        'channel_user_access', 'channels', 'users',
    ] as $t) {
        Schema::dropIfExists($t);
    }

    // Tabela business (UltimatePOS core — só os campos mínimos pra resolver IDs)
    Schema::create('business', function ($table) {
        $table->increments('id');
        $table->string('name', 191)->nullable();
        $table->timestamps();
    });

    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('username', 100)->nullable();
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
    });

    // Spatie minimal
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
    });

    Schema::create('model_has_roles', function ($table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
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
            // tolerante a env de teste sem cache
        }
    }
});

/**
 * Cria business + role Admin#{biz} pronto pra receber attach.
 */
function rwpMakeBusinessWithAdminRole(int $bizId): Role
{
    \DB::table('business')->insert([
        'id' => $bizId,
        'name' => 'Business#' . $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Role::create([
        'name' => 'Admin#' . $bizId,
        'guard_name' => 'web',
        'business_id' => $bizId,
    ]);
}

function rwpMakeBusinessNoRole(int $bizId): void
{
    \DB::table('business')->insert([
        'id' => $bizId,
        'name' => 'Business#' . $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('R-WA-RWP-001 — registra 6 permissions em tabela vazia', function () {
    rwpMakeBusinessWithAdminRole(1);

    expect(Permission::count())->toBe(0);

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '1']);

    expect($exit)->toBe(0);
    expect(Permission::count())->toBe(6);

    $expectedNames = [
        'whatsapp.access',
        'whatsapp.send',
        'whatsapp.assign',
        'whatsapp.templates.manage',
        'whatsapp.settings.manage',
        'whatsapp.metricas.view',
    ];
    $names = Permission::orderBy('name')->pluck('name')->all();
    sort($expectedNames);
    expect($names)->toBe($expectedNames);
});

it('R-WA-RWP-002 — idempotência: 2 runs não duplicam permissions', function () {
    rwpMakeBusinessWithAdminRole(1);

    Artisan::call('whatsapp:register-permissions', ['--business' => '1']);
    expect(Permission::count())->toBe(6);

    Artisan::call('whatsapp:register-permissions', ['--business' => '1']);
    expect(Permission::count())->toBe(6); // nada duplicado
});

it('R-WA-RWP-003 — --business=1 atribui ao Admin#1', function () {
    $role = rwpMakeBusinessWithAdminRole(1);
    rwpMakeBusinessWithAdminRole(99); // não deve receber

    Artisan::call('whatsapp:register-permissions', ['--business' => '1']);

    $role->refresh();
    $rolePerms = $role->permissions()->pluck('name')->sort()->values()->all();
    expect($rolePerms)->toHaveCount(6);
    expect($rolePerms)->toContain('whatsapp.access');
    expect($rolePerms)->toContain('whatsapp.send');
    expect($rolePerms)->toContain('whatsapp.assign');
    expect($rolePerms)->toContain('whatsapp.templates.manage');
    expect($rolePerms)->toContain('whatsapp.settings.manage');
    expect($rolePerms)->toContain('whatsapp.metricas.view');

    // Admin#99 NÃO recebeu
    $role99 = Role::where('name', 'Admin#99')->first();
    expect($role99->permissions()->count())->toBe(0);
});

it('R-WA-RWP-004 — --business=all atribui pra todos Admin#{biz} existentes', function () {
    $role1 = rwpMakeBusinessWithAdminRole(1);
    $role4 = rwpMakeBusinessWithAdminRole(4);
    $role99 = rwpMakeBusinessWithAdminRole(99);

    Artisan::call('whatsapp:register-permissions', ['--business' => 'all']);

    foreach ([$role1, $role4, $role99] as $r) {
        $r->refresh();
        expect($r->permissions()->count())->toBe(6);
    }
});

it('R-WA-RWP-005 — business sem Admin#{biz}: skip + warning (não cria role)', function () {
    rwpMakeBusinessNoRole(7);

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '7']);

    expect($exit)->toBe(0); // não falha
    expect(Permission::count())->toBe(6); // permissions registradas mesmo assim
    expect(Role::count())->toBe(0); // mas role NÃO foi criada
});

it('R-WA-RWP-006 — --dry-run não persiste permissions nem attach', function () {
    rwpMakeBusinessWithAdminRole(1);

    $exit = Artisan::call('whatsapp:register-permissions', [
        '--business' => '1',
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);
    expect(Permission::count())->toBe(0);

    $role = Role::where('name', 'Admin#1')->first();
    expect($role->permissions()->count())->toBe(0);
});

it('R-WA-RWP-007 — --with-backfill encadeia o outro comando', function () {
    rwpMakeBusinessWithAdminRole(1);

    // dry-run pra ambos os comandos (backfill aceita --dry-run também).
    // Smoke garante que a chamada encadeada não explode.
    $exit = Artisan::call('whatsapp:register-permissions', [
        '--business' => '1',
        '--with-backfill' => true,
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);

    $output = Artisan::output();
    // Saída do RegisterWhatsapp + saída do Backfill encadeado
    expect($output)->toContain('Encadeando whatsapp:backfill-channel-access');
});

it('R-WA-RWP-008 — Tier 0: Permission é global; Role tem business_id', function () {
    rwpMakeBusinessWithAdminRole(1);

    Artisan::call('whatsapp:register-permissions', ['--business' => '1']);

    // Permissions registradas SEM coluna business_id (são globais)
    $perm = Permission::where('name', 'whatsapp.send')->first();
    expect($perm)->not->toBeNull();
    // Sanity check — a tabela permissions nem tem business_id; o atributo
    // não aparece nos attributes do model
    expect($perm->getAttributes())->not->toHaveKey('business_id');

    // Role tem business_id setado
    $role = Role::where('name', 'Admin#1')->first();
    expect($role->business_id)->toBe(1);
});

it('R-WA-RWP-009 — --business=0 (inválido) retorna FAILURE', function () {
    rwpMakeBusinessWithAdminRole(1);

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '0']);

    expect($exit)->toBe(1); // FAILURE
    // Permissions já foram registradas antes do filter por business (idempotência
    // OK — o registry é fase 1, o attach é fase 2 que falha cedo)
    // Por isso checamos só que o role não recebeu attach.
    $role = Role::where('name', 'Admin#1')->first();
    expect($role->permissions()->count())->toBe(0);
});

it('R-WA-RWP-010 — --business=X inexistente: warning, exit 0, sem attach', function () {
    rwpMakeBusinessWithAdminRole(1);

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '777']);

    expect($exit)->toBe(0);
    $role = Role::where('name', 'Admin#1')->first();
    expect($role->permissions()->count())->toBe(0); // Admin#1 não foi tocado
});
