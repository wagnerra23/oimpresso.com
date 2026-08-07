<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Hotfix — RegisterWhatsappPermissionsCommand.
 *
 * Cobre os cenários canônicos:
 *  R-WA-RWP-001 — registra 7 permissions quando nenhuma whatsapp.* existe
 *  R-WA-RWP-002 — idempotência: 2 runs não duplicam permissions
 *  R-WA-RWP-003 — --business=98 atribui ao Admin#98
 *  R-WA-RWP-004 — --business=all atribui pra todos Admin#{biz} existentes
 *  R-WA-RWP-005 — business sem Admin#{biz} → skip + warning (não cria role)
 *  R-WA-RWP-006 — --dry-run não persiste
 *  R-WA-RWP-007 — --with-backfill encadeia outro comando (smoke artisan call)
 *  R-WA-RWP-008 — Tier 0: Permission é global; Role tem business_id
 *  R-WA-RWP-009 — --business=0 (inválido) retorna FAILURE
 *  R-WA-RWP-010 — --business=X inexistente: warning, exit 0, sem attach
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SCHEMA REAL (saiu da quarentena era-sqlite em 2026-08-07).
 *
 * ANTES: o beforeEach dropava e recriava à mão `business`, `users`, `channels`,
 * `permissions`, `roles` e os pivots Spatie, e um markTestSkipped pulava o arquivo
 * inteiro fora do sqlite. Consequências medidas: (a) num MySQL PERSISTENTE aquele
 * drop destrói o schema — daí o skip; (b) o teste validava contra um schema que ele
 * mesmo inventava, e que DIVERGIA do real (a `roles` sintética declarava
 * unique(name,guard_name), que o schema real NÃO tem, e `business_id` nullable,
 * quando o real é NOT NULL + FK→business ON DELETE CASCADE); (c) rodando só na lane
 * sqlite, nunca exercitou nenhuma dessas FKs.
 *
 * AGORA: usa o schema migrado, com DatabaseTransactions (rollback por teste; NÃO
 * RefreshDatabase, que dá migrate:fresh e apaga o seed no nightly persistente —
 * ver Tests\TestCase::healCanonicalTenantIfWiped, cascata de 454 falhas).
 *
 * Sem skip de driver de propósito: se faltar schema o teste FALHA alto, em vez de
 * ficar verde por não-execução. O home dele é a lane MySQL
 * .github/workflows/whatsapp-pest.yml — foi removido de .github/ci-sqlite-pest.list.
 *
 * Tenants de fixture (ADR 0358 — doutrina de teste):
 *   98 = tenant canônico fictício (default de todo teste)
 *   99 = adversário cross-tenant
 *    2 = segundo tenant do seed
 *   97 = business sem Admin#{biz} (faixa 95-105 livre em prod, medido na 0358)
 *  777 = business inexistente (fora do range de prod)
 * PROIBIDOS aqui: 4 (ROTA LIVRE/Larissa, cliente real) e 1 (WR2 Sistemas, empresa
 * real — deixou de ser default de teste).
 *
 * Os ids acima NÃO podem ser presumidos presentes: o seed do CI cria 1/2/98 e o
 * FullSuiteMinimalTenantSeeder do nightly cria só 1/2. Por isso rwpEnsureBusiness
 * é idempotente e cria o que faltar.
 *
 * @see Modules\Whatsapp\Console\Commands\RegisterWhatsappPermissionsCommand
 */
beforeEach(function () {
    // Spatie cacheia permissions no container; sem isso um teste enxerga o attach do anterior.
    app()->forgetInstance(\Spatie\Permission\PermissionRegistrar::class);
    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {
        // env de teste sem cache configurado — tolerante de propósito
    }

    // A DB é COMPARTILHADA (nightly CT 100 persiste entre runs). Zera só o que este
    // arquivo mede — whatsapp.* e os Admin# dos tenants de fixture — nunca as CORE.
    rwpResetWhatsappFixtures();
});

/**
 * Nomes das permissions que o comando registra (fonte: o próprio comando).
 *
 * @return list<string>
 */
function rwpExpectedPermissionNames(): array
{
    return [
        'whatsapp.access',
        'whatsapp.send',
        'whatsapp.assign',
        'whatsapp.templates.manage',
        'whatsapp.settings.manage',
        'whatsapp.metricas.view',
        'whatsapp.view-all-phones',
    ];
}

/** ids de business usados como fixture por este arquivo. */
function rwpFixtureBusinessIds(): array
{
    return [98, 99, 2, 97];
}

/**
 * Conta só as permissions do domínio deste teste.
 *
 * O schema real NÃO nasce vazio: o PermissionsTableSeeder do CI e o staging do CT 100
 * já trazem dezenas de permissions (78 medidas em oimpresso_staging, 2026-08-07).
 * Um `Permission::count()` absoluto — que era o assert do era-sqlite — nunca daria 0 aqui.
 */
function rwpWhatsappPermCount(): int
{
    return Permission::query()->where('name', 'like', 'whatsapp.%')->count();
}

/** Remove o rastro deste arquivo sem tocar em permission/role de terceiros. */
function rwpResetWhatsappFixtures(): void
{
    $permIds = Permission::query()->where('name', 'like', 'whatsapp.%')->pluck('id');
    $roleIds = Role::query()->whereIn('name', array_map(
        static fn (int $b): string => "Admin#{$b}",
        rwpFixtureBusinessIds()
    ))->pluck('id');

    if ($permIds->isNotEmpty()) {
        DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permIds)->delete();
        Permission::query()->whereIn('id', $permIds)->delete();
    }
    if ($roleIds->isNotEmpty()) {
        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();
    }
}

/**
 * Garante um business VÁLIDO no schema real, idempotente.
 *
 * Não dá pra inserir só id+name: `business` tem colunas NOT NULL sem default e
 * `business.owner_id` → `users.id` enquanto `users.business_id` → `business.id`
 * (FK circular). A ordem abaixo — user sem business → business com owner → backfill
 * do business_id no owner — espelha Tests\Support\WithSeededTenant::seededSupportClientTenant
 * e database/seeders/FullSuiteMinimalTenantSeeder.
 */
function rwpEnsureBusiness(int $bizId): void
{
    if (DB::table('business')->where('id', $bizId)->exists()) {
        return;
    }

    $curId = optional(DB::table('currencies')->first())->id ?? 1;
    $username = "rwp_owner_{$bizId}";

    $ownerId = optional(DB::table('users')->where('username', $username)->first())->id
        ?? DB::table('users')->insertGetId([
            'first_name' => "RWP Biz {$bizId}",
            'username' => $username,
            'password' => bcrypt('ci'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    DB::table('business')->insert([
        'id' => $bizId,
        'name' => "RWP Biz {$bizId} (ficticio)",
        'currency_id' => $curId,
        'owner_id' => $ownerId,
        'stop_selling_before' => 0,
        'weighing_scale_setting' => '',
        'certificado' => '',
        'officeimpresso_numerodemaquinas' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('users')->where('id', $ownerId)->update(['business_id' => $bizId]);
}

/** Cria business + role Admin#{biz} pronto pra receber attach. */
function rwpMakeBusinessWithAdminRole(int $bizId): Role
{
    rwpEnsureBusiness($bizId);

    return Role::query()->firstOrCreate([
        'name' => "Admin#{$bizId}",
        'guard_name' => 'web',
        'business_id' => $bizId,
    ]);
}

function rwpMakeBusinessNoRole(int $bizId): void
{
    rwpEnsureBusiness($bizId);
}

it('R-WA-RWP-001 — registra 7 permissions quando nenhuma whatsapp.* existe', function () {
    rwpMakeBusinessWithAdminRole(98);

    expect(rwpWhatsappPermCount())->toBe(0);

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '98']);

    expect($exit)->toBe(0);
    expect(rwpWhatsappPermCount())->toBe(7);

    $expectedNames = rwpExpectedPermissionNames();
    sort($expectedNames);
    $names = Permission::query()->where('name', 'like', 'whatsapp.%')->orderBy('name')->pluck('name')->all();
    expect($names)->toBe($expectedNames);
});

it('R-WA-RWP-002 — idempotência: 2 runs não duplicam permissions', function () {
    rwpMakeBusinessWithAdminRole(98);

    Artisan::call('whatsapp:register-permissions', ['--business' => '98']);
    expect(rwpWhatsappPermCount())->toBe(7);

    Artisan::call('whatsapp:register-permissions', ['--business' => '98']);
    expect(rwpWhatsappPermCount())->toBe(7); // nada duplicado
});

it('R-WA-RWP-003 — --business=98 atribui ao Admin#98', function () {
    $role = rwpMakeBusinessWithAdminRole(98);
    rwpMakeBusinessWithAdminRole(99); // não deve receber

    Artisan::call('whatsapp:register-permissions', ['--business' => '98']);

    $role->refresh();
    $rolePerms = $role->permissions()->pluck('name')->sort()->values()->all();
    expect($rolePerms)->toHaveCount(7);
    foreach (rwpExpectedPermissionNames() as $expected) {
        expect($rolePerms)->toContain($expected);
    }

    // Admin#99 NÃO recebeu — isolamento cross-tenant (ADR 0093)
    $role99 = Role::query()->where('name', 'Admin#99')->firstOrFail();
    expect($role99->permissions()->count())->toBe(0);
});

it('R-WA-RWP-004 — --business=all atribui pra todos Admin#{biz} existentes', function () {
    $role98 = rwpMakeBusinessWithAdminRole(98);
    $role99 = rwpMakeBusinessWithAdminRole(99);
    $role2 = rwpMakeBusinessWithAdminRole(2);

    Artisan::call('whatsapp:register-permissions', ['--business' => 'all']);

    foreach ([$role98, $role99, $role2] as $r) {
        $r->refresh();
        expect($r->permissions()->count())->toBe(7);
    }
});

it('R-WA-RWP-005 — business sem Admin#{biz}: skip + warning (não cria role)', function () {
    rwpMakeBusinessNoRole(97);

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '97']);

    expect($exit)->toBe(0); // não falha
    expect(rwpWhatsappPermCount())->toBe(7); // permissions registradas mesmo assim
    // ...mas a role NÃO foi criada (assert por ausência do nome — a DB real tem
    // outras roles, então um Role::count() absoluto não diria nada aqui)
    expect(Role::query()->where('name', 'Admin#97')->exists())->toBeFalse();
});

it('R-WA-RWP-006 — --dry-run não persiste permissions nem attach', function () {
    rwpMakeBusinessWithAdminRole(98);

    $exit = Artisan::call('whatsapp:register-permissions', [
        '--business' => '98',
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);
    expect(rwpWhatsappPermCount())->toBe(0);

    $role = Role::query()->where('name', 'Admin#98')->firstOrFail();
    expect($role->permissions()->count())->toBe(0);
});

it('R-WA-RWP-007 — --with-backfill encadeia o outro comando', function () {
    rwpMakeBusinessWithAdminRole(98);

    // dry-run pra ambos os comandos (backfill aceita --dry-run também).
    // Smoke garante que a chamada encadeada não explode.
    $exit = Artisan::call('whatsapp:register-permissions', [
        '--business' => '98',
        '--with-backfill' => true,
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);

    $output = Artisan::output();
    // Saída do RegisterWhatsapp + saída do Backfill encadeado
    expect($output)->toContain('Encadeando whatsapp:backfill-channel-access');
});

it('R-WA-RWP-008 — Tier 0: Permission é global; Role tem business_id', function () {
    rwpMakeBusinessWithAdminRole(98);

    Artisan::call('whatsapp:register-permissions', ['--business' => '98']);

    // Permissions registradas SEM coluna business_id (são globais)
    $perm = Permission::query()->where('name', 'whatsapp.send')->first();
    expect($perm)->not->toBeNull();
    expect($perm->getAttributes())->not->toHaveKey('business_id');

    // Role tem business_id setado — no schema real a coluna é NOT NULL + FK→business
    $role = Role::query()->where('name', 'Admin#98')->firstOrFail();
    expect((int) $role->business_id)->toBe(98);
});

it('R-WA-RWP-009 — --business=0 (inválido) retorna FAILURE', function () {
    rwpMakeBusinessWithAdminRole(98);

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '0']);

    expect($exit)->toBe(1); // FAILURE
    // Permissions já foram registradas antes do filter por business (idempotência
    // OK — o registry é fase 1, o attach é fase 2 que falha cedo)
    // Por isso checamos só que o role não recebeu attach.
    $role = Role::query()->where('name', 'Admin#98')->firstOrFail();
    expect($role->permissions()->count())->toBe(0);
});

it('R-WA-RWP-010 — --business=X inexistente: warning, exit 0, sem attach', function () {
    rwpMakeBusinessWithAdminRole(98);
    expect(DB::table('business')->where('id', 777)->exists())->toBeFalse(); // pré-condição

    $exit = Artisan::call('whatsapp:register-permissions', ['--business' => '777']);

    expect($exit)->toBe(0);
    $role = Role::query()->where('name', 'Admin#98')->firstOrFail();
    expect($role->permissions()->count())->toBe(0); // Admin#98 não foi tocado
});
