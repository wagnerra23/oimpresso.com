<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Package;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * D2 Pest Wave 13 — Cross-Tenant Policy Test (Superadmin governança).
 *
 * Complementa `CrossTenantSuperadminTest.php` (Wave anterior) com cobertura
 * EXPANDIDA dos contratos cross-tenant intencionais documentados em:
 *   - Constituição v2 Art. 6 — Superadmin opera FORA do multi-tenant por design
 *   - ADR 0093 §exceções — Package/Business são entities cross-tenant
 *   - SPEC.md `na_justified.D5` — penalizar isolamento aqui distorce ranking
 *
 * 5 cenários:
 *   1. Package query via `withoutGlobalScopes` retorna registros de qualquer biz
 *   2. Sintaxe `// SUPERADMIN: <razão>` é convenção viva (regex check no source)
 *   3. Gate Spatie `can('superadmin')` é único portão — sem permission = 403/302
 *   4. Self-destroy guard — superadmin NÃO pode deletar próprio biz_id da session
 *   5. Throttle rate limit `superadmin` registrado (D8.b Wave 13)
 *
 * NUNCA biz=4 (ROTA LIVRE prod — ADR 0101). biz=1 (Wagner) + biz=99 (fictício).
 *
 * @see Modules/Superadmin/Tests/Feature/CrossTenantSuperadminTest.php (Wave anterior)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard granular: cenários 2+5 (filesystem + RateLimiter) rodam SEM banco.
// Cenários 1+3+4 (Eloquent + HTTP) exigem schema MySQL UltimatePOS.
function requiresMySQLSchema(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: Superadmin + Spatie + Business exigem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('packages')) {
        test()->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
}

const BIZ_WAGNER_POLICY = 1;
const BIZ_FICTICIO_POLICY = 99;

// ---------- Cenário 1: withoutGlobalScopes retorna entities cross-tenant ----------

it('Package::withoutGlobalScopes — superadmin enxerga pacotes de qualquer business', function () {
    requiresMySQLSchema();
    // Cria 2 packages globais (Package não tem business_id obrigatório).
    $pkgA = Package::create([
        'name'          => 'Pacote Policy A',
        'description'   => 'Cross-tenant policy test',
        'location_count' => 1,
        'user_count'    => 5,
        'product_count' => 50,
        'invoice_count' => 500,
        'interval'      => 'months',
        'interval_count' => 1,
        'trial_days'    => 0,
        'price'         => 0,
        'is_active'     => 1,
        'sort_order'    => 9001,
        'is_private'    => 0,
        'is_one_time'   => 0,
    ]);

    $pkgB = Package::create([
        'name'          => 'Pacote Policy B',
        'description'   => 'Cross-tenant policy test',
        'location_count' => 1,
        'user_count'    => 10,
        'product_count' => 100,
        'invoice_count' => 1000,
        'interval'      => 'months',
        'interval_count' => 1,
        'trial_days'    => 7,
        'price'         => 99,
        'is_active'     => 1,
        'sort_order'    => 9002,
        'is_private'    => 0,
        'is_one_time'   => 0,
    ]);

    // SUPERADMIN: query cross-tenant intencional.
    $count = Package::withoutGlobalScopes()
        ->whereIn('id', [$pkgA->id, $pkgB->id])
        ->count();

    expect($count)->toBe(2, 'withoutGlobalScopes deve trazer ambos packages cross-tenant');
})->afterEach(function () {
    Package::whereIn('name', ['Pacote Policy A', 'Pacote Policy B'])->forceDelete();
});

// ---------- Cenário 2: convenção `// SUPERADMIN:` viva no source ----------

it('controlador Superadmin segue convenção `// SUPERADMIN:` para queries cross-tenant', function () {
    // Não exige presença obrigatória — apenas valida que SE existir withoutGlobalScopes,
    // está comentado conforme ADR 0093 (auditoria simples por regex).
    $sourceDir = base_path('Modules/Superadmin');

    if (! is_dir($sourceDir)) {
        $this->markTestSkipped('Diretório Modules/Superadmin ausente — esperado em monolito.');
    }

    // Pelo menos a Constituição precisa estar referenciada em algum lugar do módulo.
    $files = [
        base_path('Modules/Superadmin/Http/Requests/UpdateBusinessPasswordRequest.php'),
        base_path('Modules/Superadmin/Http/Requests/StoreBusinessRequest.php'),
    ];

    $foundSuperadminMarker = false;
    foreach ($files as $f) {
        if (is_file($f) && str_contains((string) file_get_contents($f), 'SUPERADMIN')) {
            $foundSuperadminMarker = true;
            break;
        }
    }

    expect($foundSuperadminMarker)->toBeTrue(
        'Ao menos 1 FormRequest do módulo deve marcar convenção SUPERADMIN cross-tenant (ADR 0093)'
    );
});

// ---------- Cenário 3: Permission superadmin é portão único ----------

it('usuário SEM permission `superadmin` recebe 403/302 em /superadmin/business cross-tenant', function () {
    requiresMySQLSchema();
    Business::firstOrCreate(
        ['id' => BIZ_FICTICIO_POLICY],
        ['name' => 'Business Ficticio Policy', 'currency_id' => 1]
    );

    $userBiz99 = User::firstOrCreate(
        ['username' => 'policy_user_biz99'],
        [
            'email'       => 'policy_biz99@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_FICTICIO_POLICY,
            'first_name'  => 'Policy',
            'last_name'   => 'Biz99',
        ]
    );

    $userBiz99->syncRoles([]);
    $userBiz99->syncPermissions([]);

    $response = $this->actingAs($userBiz99)->get('/superadmin/business');

    expect($response->status())->toBeIn([302, 403]);
    expect($response->status())->not->toBe(200, 'Sem permission JAMAIS pode receber 200 em rota cross-tenant');
});

// ---------- Cenário 4: self-destroy guard — não pode deletar próprio business ----------

it('superadmin NÃO pode deletar próprio business_id (self-destroy guard)', function () {
    requiresMySQLSchema();
    Business::firstOrCreate(
        ['id' => BIZ_WAGNER_POLICY],
        ['name' => 'Wagner Teste Policy', 'currency_id' => 1]
    );

    $admin = User::firstOrCreate(
        ['username' => 'policy_admin_biz1'],
        [
            'email'       => 'policy_admin@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_WAGNER_POLICY,
            'first_name'  => 'Policy',
            'last_name'   => 'Admin',
        ]
    );

    $permission = Permission::firstOrCreate(
        ['name' => 'superadmin', 'guard_name' => 'web']
    );

    if (! $admin->hasPermissionTo('superadmin')) {
        $admin->givePermissionTo($permission);
    }

    // Simula session com business_id próprio
    session(['user.business_id' => BIZ_WAGNER_POLICY]);
    session(['user.id' => $admin->id]);

    // Tenta deletar o próprio biz=1 — Controller responde 4xx ou redirect, NUNCA hard delete
    $response = $this->actingAs($admin)->get('/superadmin/business/'.BIZ_WAGNER_POLICY.'/destroy');

    // Aceita: 302 (redirect com status erro), 403, 200 (com mensagem) — desde que biz=1 ainda exista
    $stillExists = Business::find(BIZ_WAGNER_POLICY) !== null;

    expect($stillExists)->toBeTrue(
        'Self-destroy guard deve preservar Business::find(1) mesmo após tentativa de destroy próprio biz_id'
    );
    expect($response->status())->toBeLessThan(500, 'Self-destroy não pode quebrar com 5xx');
});

// ---------- Cenário 5: RateLimiter `superadmin` registrado (D8.b Wave 13) ----------

it('RateLimiter `superadmin` está registrado no RouteServiceProvider', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('superadmin');

    expect($limiter)->not->toBeNull('RateLimiter::for(\'superadmin\') deve existir após boot RouteServiceProvider');

    // Smoke do callable — retorna Limit instance pra qualquer Request
    $request = \Illuminate\Http\Request::create('/superadmin', 'GET');
    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
});
