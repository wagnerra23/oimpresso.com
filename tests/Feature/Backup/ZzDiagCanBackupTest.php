<?php

declare(strict_types=1);

/**
 * TEMPORARIO — diagnostico do 403 da lane backup-pest. REMOVER no mesmo PR.
 *
 * Medido no CT 100 (staging, transacao + rollback): depois de givePermissionTo('backup'),
 * can('backup') volta TRUE. No CI da 403 em toda rota que chega ao controller. Este arquivo
 * existe so pra dizer ONDE os dois ambientes divergem, em vez de eu seguir chutando.
 */

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

test('DIAG: onde can(backup) diverge entre CT100 e CI', function () {
    if (! Schema::hasTable('business') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Schema ausente');
    }

    Permission::firstOrCreate(['name' => 'backup', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $biz = $this->seededTenant();
    $u = User::factory()->create(['business_id' => $biz->id]);
    $u->givePermissionTo('backup');
    $u = User::find($u->id);

    $linhas = [
        'business_id' => $biz->id,
        'user_type' => var_export($u->user_type, true),
        'allow_login' => var_export($u->allow_login, true),
        'hasDirectPermission' => var_export($u->hasDirectPermission('backup'), true),
        'hasPermissionTo' => var_export($u->hasPermissionTo('backup'), true),
        'checkPermissionTo' => var_export($u->checkPermissionTo('backup'), true),
        'can(backup)' => var_export($u->can('backup'), true),
        'guard_name do model' => var_export(method_exists($u, 'getDefaultGuardName') ? 'n/a' : 'n/a', true),
        'auth default guard' => config('auth.defaults.guard'),
        'administrator_usernames' => var_export(config('constants.administrator_usernames'), true),
        'register_permission_check_method' => var_export(config('permission.register_permission_check_method'), true),
        'cache.default' => var_export(config('cache.default'), true),
        'permission.cache.store' => var_export(config('permission.cache.store'), true),
        'app.env' => config('app.env'),
    ];

    // request real, como o teste de verdade faz
    $this->actingAs($u);
    session(['user.business_id' => $biz->id, 'business.id' => $biz->id]);
    $r = $this->get('/backup');
    $linhas['GET /backup status'] = $r->status();
    $linhas['can dentro do request'] = var_export(auth()->check() ? auth()->user()->can('backup') : 'sem auth', true);

    $out = "\n===== DIAG can(backup) =====\n";
    foreach ($linhas as $k => $v) {
        $out .= sprintf("  %-34s %s\n", $k, $v);
    }
    $out .= "============================\n";
    fwrite(STDERR, $out);

    expect(true)->toBeTrue();
});
