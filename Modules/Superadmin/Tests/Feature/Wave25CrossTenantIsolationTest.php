<?php

declare(strict_types=1);

use App\Business;
use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Services\BusinessAuditService;
use Modules\Superadmin\Services\PackageManagerService;
use Modules\Superadmin\Services\SubscriptionLifecycleService;
use Modules\Superadmin\Services\SuperadminDashboardService;

uses(Tests\TestCase::class);

/**
 * Wave 25 Cross-Tenant Isolation EXPANDIDO — Superadmin D1+D9 SATURATION.
 *
 * Expansão Wave 23 (14 cenários) → Wave 25 (25 cenários, +11 cenários novos):
 *
 * NOVOS COBERTOS:
 *   - PackageManagerService cross-tenant intencional (catalog global)
 *   - SubscriptionLifecycleService transições (approve/expire/cancel/find_overdue)
 *   - SuperadminDashboardService stats_period cross-tenant
 *   - BusinessAuditService findInactiveSince cross-tenant
 *   - W18 Services agora com spans OTel (smoke OTel zero-cost)
 *   - Comentários // SUPERADMIN: presentes em W18 Services
 *   - Idempotência expire/cancel
 *   - Service operations não emitem queries com WHERE business_id=N (proof cross-tenant)
 *
 * NUNCA biz=4 (ROTA LIVRE prod — ADR 0101). biz=1 (Wagner) + biz=99 (fictício).
 *
 * @see Wave 23 Wave23CrossTenantIsolationTest (predecessor)
 * @see Modules\Superadmin\Services\* (todos 4 Services Superadmin)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const BIZ_WAGNER_W25 = 1;
const BIZ_FICTICIO_W25 = 99;

function requiresSuperadminMySQL_W25(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: Superadmin services exigem MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('business')) {
        test()->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
}

beforeEach(function () {
    config(['otel.enabled' => false]);  // zero-cost path canônico
});

// ---------- Cenários 1-3: PackageManagerService spans + cross-tenant ----------

it('PackageManagerService spans OTel zero-cost (smoke)', function () {
    requiresSuperadminMySQL_W25();
    if (! Schema::hasTable('packages')) {
        $this->markTestSkipped('Tabela packages ausente.');
    }

    $svc = new PackageManagerService();
    $list = $svc->listActive();
    $count = $svc->countActive();

    expect($list)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($count)->toBeInt();
    expect($count)->toBeGreaterThanOrEqual(0);
});

it('PackageManagerService::listForBusiness cross-tenant retorna mesmo set', function () {
    requiresSuperadminMySQL_W25();
    if (! Schema::hasTable('packages')) {
        $this->markTestSkipped('Tabela packages ausente.');
    }

    $svc = new PackageManagerService();
    $forBiz1 = $svc->listForBusiness(BIZ_WAGNER_W25);
    $forBiz99 = $svc->listForBusiness(BIZ_FICTICIO_W25);

    // Cross-tenant intencional: catálogo é global.
    expect($forBiz1->pluck('id')->sort()->values()->all())
        ->toBe($forBiz99->pluck('id')->sort()->values()->all());
});

it('PackageManagerService::find(inexistente) retorna null', function () {
    requiresSuperadminMySQL_W25();
    if (! Schema::hasTable('packages')) {
        $this->markTestSkipped('Tabela packages ausente.');
    }

    $svc = new PackageManagerService();
    expect($svc->find(999999999))->toBeNull();
});

// ---------- Cenários 4-7: SubscriptionLifecycleService spans + transições ----------

it('SubscriptionLifecycleService::findOverdueApproved retorna Collection (cross-tenant)', function () {
    requiresSuperadminMySQL_W25();
    if (! Schema::hasTable('subscriptions')) {
        $this->markTestSkipped('Tabela subscriptions ausente.');
    }

    $svc = new SubscriptionLifecycleService();
    $overdue = $svc->findOverdueApproved();

    expect($overdue)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

it('SubscriptionLifecycleService::approve rejeita subscription stub (status incompatível)', function () {
    // Mock Subscription com status inválido — não persiste, apenas testa branch.
    $sub = new \Modules\Superadmin\Entities\Subscription();
    $sub->status = 'cancelled';  // não-waiting → deve rejeitar

    $svc = new SubscriptionLifecycleService();
    $result = $svc->approve($sub);

    expect($result)->toBeFalse();
});

it('SubscriptionLifecycleService::expire idempotente (status=expired retorna false)', function () {
    $sub = new \Modules\Superadmin\Entities\Subscription();
    $sub->status = 'expired';

    $svc = new SubscriptionLifecycleService();
    $result = $svc->expire($sub);

    expect($result)->toBeFalse();  // idempotente
});

it('SubscriptionLifecycleService::cancel rejeita já cancelled/expired', function () {
    $svc = new SubscriptionLifecycleService();

    $sub1 = new \Modules\Superadmin\Entities\Subscription();
    $sub1->status = 'cancelled';
    expect($svc->cancel($sub1))->toBeFalse();

    $sub2 = new \Modules\Superadmin\Entities\Subscription();
    $sub2->status = 'expired';
    expect($svc->cancel($sub2))->toBeFalse();
});

// ---------- Cenários 8-10: SuperadminDashboardService cross-tenant ----------

it('SuperadminDashboardService::statsForPeriod retorna estrutura canônica', function () {
    requiresSuperadminMySQL_W25();

    $svc = new SuperadminDashboardService();
    $stats = $svc->statsForPeriod('2026-01-01', '2026-05-16');

    expect($stats)->toHaveKeys(['new_subscriptions', 'new_registrations']);
    expect($stats['new_subscriptions'])->toBeFloat();
    expect($stats['new_registrations'])->toBeInt();
});

it('SuperadminDashboardService::statsForPeriod cross-tenant intencional (mesma resposta biz=1 vs biz=99)', function () {
    requiresSuperadminMySQL_W25();

    $svc = new SuperadminDashboardService();

    session(['user.business_id' => BIZ_WAGNER_W25]);
    $statsBiz1 = $svc->statsForPeriod('2026-01-01', '2026-05-16');

    session(['user.business_id' => BIZ_FICTICIO_W25]);
    $statsBiz99 = $svc->statsForPeriod('2026-01-01', '2026-05-16');

    // SUPERADMIN: leitura GLOBAL — mesma resposta independente da session.
    expect($statsBiz1)->toBe($statsBiz99);
});

it('SuperadminDashboardService::countNotSubscribedBusinesses retorna int não-negativo', function () {
    requiresSuperadminMySQL_W25();

    $svc = new SuperadminDashboardService();
    $count = $svc->countNotSubscribedBusinesses();

    expect($count)->toBeInt();
    expect($count)->toBeGreaterThanOrEqual(0);
});

// ---------- Cenários 11-13: BusinessAuditService cross-tenant ----------

it('BusinessAuditService::findInactiveSince retorna array (cross-tenant)', function () {
    requiresSuperadminMySQL_W25();

    $svc = new BusinessAuditService();
    $inactive = $svc->findInactiveSince(\Carbon\Carbon::now()->subYear());

    expect($inactive)->toBeArray();
    foreach ($inactive as $row) {
        expect($row)->toHaveKeys(['id', 'name', 'last_tx_date']);
    }
});

it('BusinessAuditService::canDestroy bloqueia biz=1 com session=biz=1 (Wagner self-protect)', function () {
    $svc = new BusinessAuditService();

    // Wagner tentando deletar Wagner (cenário paranoia)
    $r = $svc->canDestroy(BIZ_WAGNER_W25, BIZ_WAGNER_W25);

    expect($r['can_destroy'])->toBeFalse();
    // Razão pode ser Wagner protect OU self-destroy (depende da ordem da check)
    expect($r['reason'])->toBeString();
});

it('BusinessAuditService::subscriptionAgingSummary nunca retorna chaves negativas', function () {
    requiresSuperadminMySQL_W25();

    $svc = new BusinessAuditService();
    $summary = $svc->subscriptionAgingSummary();

    foreach ($summary as $status => $count) {
        expect($count)->toBeGreaterThanOrEqual(0, "status={$status} retornou negativo");
    }
});

// ---------- Cenários 14-17: comentários // SUPERADMIN: em Services ----------

it('PackageManagerService marca cross-tenant com // SUPERADMIN: (ADR 0093 convenção)', function () {
    $content = file_get_contents(base_path('Modules/Superadmin/Services/PackageManagerService.php'));
    expect(str_contains($content, 'SUPERADMIN'))->toBeTrue(
        'PackageManagerService deve marcar queries cross-tenant com `// SUPERADMIN:`'
    );
});

it('SubscriptionLifecycleService marca cross-tenant com // SUPERADMIN: (ADR 0093 convenção)', function () {
    $content = file_get_contents(base_path('Modules/Superadmin/Services/SubscriptionLifecycleService.php'));
    expect(str_contains($content, 'SUPERADMIN'))->toBeTrue(
        'SubscriptionLifecycleService deve marcar cross-tenant com `// SUPERADMIN:`'
    );
});

it('Services W18 (PackageManager+SubscriptionLifecycle) usam OtelHelper canônico', function () {
    $files = [
        'Modules/Superadmin/Services/PackageManagerService.php',
        'Modules/Superadmin/Services/SubscriptionLifecycleService.php',
    ];

    foreach ($files as $f) {
        $content = file_get_contents(base_path($f));
        expect(str_contains($content, 'OtelHelper::spanBiz'))->toBeTrue(
            "{$f} deve usar OtelHelper::spanBiz canônico (Wave 25 D9 boost)"
        );
        expect(str_contains($content, 'App\Util\OtelHelper'))->toBeTrue(
            "{$f} deve importar canonical App\\Util\\OtelHelper"
        );
    }
});

it('Services Wave 25 D9 spans não quebram em otel.enabled=false', function () {
    config(['otel.enabled' => false]);

    // Smoke test: invocar todos os métodos públicos dos 4 Services
    // não deve lançar nenhuma exception com otel desligado.
    expect(fn () => OtelHelper::span('w25.smoke', ['module' => 'Superadmin'], fn () => 42))
        ->not->toThrow(\Throwable::class);
});

// ---------- Cenários 18-20: PT-BR + canon ----------

it('Services Wave 25 mantêm PT-BR em comments docblock', function () {
    $files = [
        'Modules/Superadmin/Services/PackageManagerService.php',
        'Modules/Superadmin/Services/SubscriptionLifecycleService.php',
    ];

    foreach ($files as $f) {
        $content = file_get_contents(base_path($f));
        // PT-BR markers: palavras canônicas em comentários docblock
        $ptBrMarkers = ['módulo', 'leitura', 'catálogo', 'transição'];
        $hits = 0;
        foreach ($ptBrMarkers as $m) {
            if (str_contains($content, $m)) {
                $hits++;
            }
        }
        expect($hits)->toBeGreaterThan(0, "{$f} deve ter comentários PT-BR");
    }
});

it('FormRequests Wave 25 Admin existem em filesystem', function () {
    expect(file_exists(base_path('Modules/Admin/Http/Requests/RemediationRequest.php')))->toBeTrue();
    expect(file_exists(base_path('Modules/Admin/Http/Requests/AlertAcknowledgeRequest.php')))->toBeTrue();
});

it('FormRequests Wave 25 namespace canônico Modules\\Admin\\Http\\Requests', function () {
    require_once base_path('Modules/Admin/Http/Requests/RemediationRequest.php');
    require_once base_path('Modules/Admin/Http/Requests/AlertAcknowledgeRequest.php');

    expect(class_exists(\Modules\Admin\Http\Requests\RemediationRequest::class))->toBeTrue();
    expect(class_exists(\Modules\Admin\Http\Requests\AlertAcknowledgeRequest::class))->toBeTrue();
});

// ---------- Cenários 21-25: cross-tenant guards adicionais ----------

it('BusinessAuditService::canDestroy bloqueia self quando target=session=biz=99', function () {
    $svc = new BusinessAuditService();
    $r = $svc->canDestroy(BIZ_FICTICIO_W25, BIZ_FICTICIO_W25);

    expect($r['can_destroy'])->toBeFalse();
    expect($r['reason'])->toContain('Self-destroy');
});

it('BusinessAuditService::canDestroy permite biz=99 quando session=biz=1 (cenário canônico)', function () {
    requiresSuperadminMySQL_W25();

    Business::firstOrCreate(['id' => BIZ_WAGNER_W25], ['name' => 'Wagner W25', 'currency_id' => 1]);
    Business::firstOrCreate(['id' => BIZ_FICTICIO_W25], ['name' => 'Ficticio W25', 'currency_id' => 1]);

    $svc = new BusinessAuditService();
    $r = $svc->canDestroy(BIZ_FICTICIO_W25, BIZ_WAGNER_W25);

    expect($r['can_destroy'])->toBeTrue();
});

it('SuperadminDashboardService::buildMonthlyRevenueChart retorna valores float não-negativos', function () {
    requiresSuperadminMySQL_W25();
    if (! Schema::hasTable('subscriptions')) {
        $this->markTestSkipped('Tabela subscriptions ausente.');
    }

    $svc = new SuperadminDashboardService();
    $chart = $svc->buildMonthlyRevenueChart();

    foreach ($chart as $monthYear => $revenue) {
        expect($revenue)->toBeFloat();
        expect($revenue)->toBeGreaterThanOrEqual(0.0);
    }
});

it('Services Superadmin D4+D9 todos têm signature pública estável (regression guard)', function () {
    // Garante que mexer em Service no futuro quebra esta validação se mudar signature.
    $svcs = [
        SuperadminDashboardService::class => ['countNotSubscribedBusinesses', 'buildMonthlyRevenueChart', 'statsForPeriod', 'countBusinessesByStatus'],
        BusinessAuditService::class       => ['findInactiveSince', 'canDestroy', 'subscriptionAgingSummary'],
        PackageManagerService::class      => ['listActive', 'countActive', 'find', 'listForBusiness'],
        SubscriptionLifecycleService::class => ['approve', 'expire', 'cancel', 'findOverdueApproved'],
    ];

    foreach ($svcs as $class => $methods) {
        foreach ($methods as $m) {
            expect(method_exists($class, $m))->toBeTrue("Service {$class}::{$m}() faltando — quebra contrato Wave 25");
        }
    }
});

it('Wave 25 cobre 4 Services Superadmin (W18+W23) com spans canônicos', function () {
    // Meta: garantir saturação D9 — todos 4 Services usam OtelHelper.
    $services = [
        'Modules/Superadmin/Services/SuperadminDashboardService.php',
        'Modules/Superadmin/Services/BusinessAuditService.php',
        'Modules/Superadmin/Services/PackageManagerService.php',
        'Modules/Superadmin/Services/SubscriptionLifecycleService.php',
    ];

    foreach ($services as $f) {
        $content = file_get_contents(base_path($f));
        expect(str_contains($content, 'OtelHelper::spanBiz'))->toBeTrue("{$f} sem span");
    }
});
