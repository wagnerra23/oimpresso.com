<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Services\BusinessAuditService;
use Modules\Superadmin\Services\SuperadminDashboardService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Wave 23 Cross-Tenant Isolation Test EXPANDIDO — Superadmin D1+D4+D9.
 *
 * Cobertura expandida (além de CrossTenantSuperadminTest + SuperadminCrossTenantPolicyTest
 * existentes) com foco em ISOLATION REAL via Services D4 novos:
 *
 *   1. SuperadminDashboardService rotina cross-tenant (ADR 0093 §exceções)
 *   2. BusinessAuditService::canDestroy guard biz=1 imutável
 *   3. BusinessAuditService::canDestroy bloqueia self (mesma session biz)
 *   4. Service operações spans OTel zero-cost (config otel.enabled=false)
 *   5. superadmin:health command registrado + --detail
 *   6. Pest cross-tenant biz=1 ≠ biz=99 via Services (não direto Eloquent)
 *   7. Subscription aging summary não filtra business_id (cross-tenant intencional)
 *
 * NUNCA biz=4 (ROTA LIVRE prod — ADR 0101). biz=1 (Wagner) + biz=99 (fictício).
 *
 * @see Modules\Superadmin\Services\SuperadminDashboardService (Wave 23 D4)
 * @see Modules\Superadmin\Services\BusinessAuditService (Wave 23 D4)
 * @see Modules\Superadmin\Console\SuperadminHealthCommand (Wave 23 D9.c)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

function requiresSuperadminMySQL(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: Superadmin + Spatie + Business exigem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business')) {
        test()->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
}

const BIZ_WAGNER_W23 = 1;
const BIZ_FICTICIO_W23 = 99;

beforeEach(function () {
    config(['otel.enabled' => false]);  // zero-cost path
});

// ---------- Cenário 1: SuperadminDashboardService cross-tenant ----------

it('SuperadminDashboardService::countNotSubscribedBusinesses opera cross-tenant (Tier 0 exceção)', function () {
    requiresSuperadminMySQL();

    $svc = new SuperadminDashboardService();
    $count = $svc->countNotSubscribedBusinesses();

    expect($count)->toBeInt();
    expect($count)->toBeGreaterThanOrEqual(0);
});

it('SuperadminDashboardService::countBusinessesByStatus retorna estrutura canônica', function () {
    requiresSuperadminMySQL();

    $svc = new SuperadminDashboardService();
    $stats = $svc->countBusinessesByStatus();

    expect($stats)->toHaveKeys(['active', 'inactive', 'total']);
    expect($stats['active'])->toBeInt();
    expect($stats['inactive'])->toBeInt();
    expect($stats['total'])->toBe($stats['active'] + $stats['inactive']);
});

it('SuperadminDashboardService::buildMonthlyRevenueChart retorna array Mon-YYYY => float', function () {
    requiresSuperadminMySQL();
    if (! Schema::hasTable('subscriptions')) {
        $this->markTestSkipped('Tabela subscriptions ausente.');
    }

    $svc = new SuperadminDashboardService();
    $chart = $svc->buildMonthlyRevenueChart();

    expect($chart)->toBeArray();
    foreach ($chart as $monthYear => $revenue) {
        expect($monthYear)->toBeString();
        expect($revenue)->toBeFloat();
    }
});

// ---------- Cenário 2-3: BusinessAuditService self-destroy guard ----------

it('BusinessAuditService::canDestroy bloqueia biz=1 (Wagner) sempre', function () {
    $svc = new BusinessAuditService();

    // Mesmo com session biz=99 (outro user superadmin tentando deletar Wagner)
    $result = $svc->canDestroy(BIZ_WAGNER_W23, BIZ_FICTICIO_W23);

    expect($result['can_destroy'])->toBeFalse();
    expect($result['reason'])->toContain('Wagner');
});

it('BusinessAuditService::canDestroy bloqueia self-destroy (mesma session biz)', function () {
    $svc = new BusinessAuditService();

    // session biz=99 tentando deletar biz=99 próprio
    $result = $svc->canDestroy(BIZ_FICTICIO_W23, BIZ_FICTICIO_W23);

    expect($result['can_destroy'])->toBeFalse();
    expect($result['reason'])->toContain('Self-destroy');
});

it('BusinessAuditService::canDestroy permite destroy quando target ≠ session ≠ biz=1', function () {
    requiresSuperadminMySQL();

    // Garante ao menos 2 businesses existentes (biz=1 + biz=99)
    Business::firstOrCreate(['id' => BIZ_WAGNER_W23], ['name' => 'Wagner W23', 'currency_id' => 1]);
    Business::firstOrCreate(['id' => BIZ_FICTICIO_W23], ['name' => 'Ficticio W23', 'currency_id' => 1]);

    $svc = new BusinessAuditService();
    // session=biz=1 (Wagner admin) deletando biz=99 (fictício)
    $result = $svc->canDestroy(BIZ_FICTICIO_W23, BIZ_WAGNER_W23);

    expect($result['can_destroy'])->toBeTrue();
    expect($result['reason'])->toContain('ok pra destroy');
});

// ---------- Cenário 4: Subscription aging cross-tenant ----------

it('BusinessAuditService::subscriptionAgingSummary retorna estrutura canônica cross-tenant', function () {
    requiresSuperadminMySQL();

    $svc = new BusinessAuditService();
    $summary = $svc->subscriptionAgingSummary();

    expect($summary)->toHaveKeys(['waiting', 'approved', 'expired', 'cancelled']);
    foreach (['waiting', 'approved', 'expired', 'cancelled'] as $k) {
        expect($summary[$k])->toBeInt();
    }
});

// ---------- Cenário 5: superadmin:health registrado ----------

it('comando superadmin:health está registrado em artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('superadmin:health');
});

it('superadmin:health executa sem fatal (smoke)', function () {
    requiresSuperadminMySQL();

    $exit = Artisan::call('superadmin:health');

    expect($exit)->toBeIn([0, 1]);
    $output = Artisan::output();
    expect($output)->toContain('superadmin:health');
});

it('superadmin:health --detail mostra tabela check/status/detalhe', function () {
    requiresSuperadminMySQL();

    Artisan::call('superadmin:health', ['--detail' => true]);
    $output = Artisan::output();

    expect($output)->toContain('check');
    expect($output)->toContain('status');
});

it('SuperadminHealthCommand signature usa --detail (não --verbose Symfony)', function () {
    $cmd = app(\Modules\Superadmin\Console\SuperadminHealthCommand::class);
    $signature = (new ReflectionClass($cmd))->getProperty('signature');
    $signature->setAccessible(true);
    $sig = $signature->getValue($cmd);

    expect($sig)->toContain('superadmin:health');
    expect($sig)->toContain('--detail');
    expect($sig)->toContain('--notify');
    expect(str_contains($sig, '--verbose'))->toBeFalse();
});

// ---------- Cenário 6: cross-tenant biz=1 vs biz=99 via Services ----------

it('Services Wave 23 D4 não filtram business_id (cross-tenant intencional documentado)', function () {
    requiresSuperadminMySQL();

    $dashSvc = new SuperadminDashboardService();

    session(['user.business_id' => BIZ_WAGNER_W23]);
    $countBiz1 = $dashSvc->countBusinessesByStatus()['total'];

    session(['user.business_id' => BIZ_FICTICIO_W23]);
    $countBiz99 = $dashSvc->countBusinessesByStatus()['total'];

    // Cross-tenant intencional: contagem GLOBAL, igual em ambas sessions.
    expect($countBiz1)->toBe($countBiz99, 'SUPERADMIN: leitura cross-tenant deve retornar mesma contagem independente da session');
});

// ---------- Cenário 7: usuário sem permission bloqueado em rotas novas ----------

it('user sem permission superadmin bloqueado em rota cross-tenant Services D4', function () {
    requiresSuperadminMySQL();

    Business::firstOrCreate(['id' => BIZ_FICTICIO_W23], ['name' => 'Ficticio W23', 'currency_id' => 1]);

    $user = User::firstOrCreate(
        ['username' => 'w23_unauth_user'],
        [
            'email' => 'w23_unauth@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_FICTICIO_W23,
            'first_name' => 'Unauth',
        ]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);

    $response = $this->actingAs($user)->get('/superadmin/business');

    expect($response->status())->toBeIn([302, 403]);
    expect($response->status())->not->toBe(200);
});

// ---------- Cenário 8: Sintaxe // SUPERADMIN: viva nos Services D4 ----------

it('Services Wave 23 D4 marcam queries cross-tenant com comentário SUPERADMIN:', function () {
    $files = [
        base_path('Modules/Superadmin/Services/SuperadminDashboardService.php'),
        base_path('Modules/Superadmin/Services/BusinessAuditService.php'),
    ];

    foreach ($files as $f) {
        expect(file_exists($f))->toBeTrue("Service Wave 23 D4 ausente: {$f}");
        $content = file_get_contents($f);
        expect(str_contains($content, 'SUPERADMIN'))->toBeTrue(
            "{$f} deve marcar queries cross-tenant com `// SUPERADMIN:` (ADR 0093 convenção)"
        );
    }
});
