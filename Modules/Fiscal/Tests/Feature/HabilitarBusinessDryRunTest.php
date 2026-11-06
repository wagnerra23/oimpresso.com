<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-018 — `fiscal:habilitar-business --dry-run` numa empresa que já tem o role
 * principal mas ainda não tem as permissões fiscal.* (instalação nova).
 *
 * Achado da auditoria Fiscal, lote 2.2 (2026-11-06): o dry-run não cria as permissões (certo)
 * e o PASSO 2 chamava `$role->hasPermissionTo()` em permissão inexistente, que lança
 * PermissionDoesNotExist. O HabilitarBusinessCommandTest só pegou isso numa ordem aleatória
 * do Pest; aqui o role Admin#98 é criado no próprio caso, então a mordida não depende da ordem.
 *
 * Tenant fictício 98 (ADR 0358).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: business + tabelas do Spatie exigem MySQL');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('roles')) {
        $this->markTestSkipped('Tabelas UltimatePOS ausentes');
    }
});

it('dry-run com o role existente e sem as fiscal.* sai 0 e não cria nada', function () {
    $biz = (int) $this->seededTenant()->id;

    // A lane compartilha o banco: guarda as fiscal.* que existem para devolver no fim.
    $antes = Permission::where('name', 'like', 'fiscal.%')->where('guard_name', 'web')->pluck('name')->all();
    $roleCriado = false;
    $role = Role::where('name', "Admin#{$biz}")->where('guard_name', 'web')->first();
    if (! $role) {
        $role = Role::create(['name' => "Admin#{$biz}", 'guard_name' => 'web', 'business_id' => $biz]);
        $roleCriado = true;
    }

    try {
        Permission::where('name', 'like', 'fiscal.%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $saida = Artisan::call('fiscal:habilitar-business', ['businessId' => $biz, '--dry-run' => true]);

        expect($saida)->toBe(0);
        expect(Permission::where('name', 'like', 'fiscal.%')->count())->toBe(0);
        // Controle: o comando chegou ao PASSO 2 com o role certo, não saiu por "role não existe".
        expect(Artisan::output())->toContain("Admin#{$biz} ← fiscal.access");
        expect($role->fresh()->permissions()->where('name', 'like', 'fiscal.%')->count())->toBe(0);
    } finally {
        foreach ($antes as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }
        if ($roleCriado) {
            $role->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
});
