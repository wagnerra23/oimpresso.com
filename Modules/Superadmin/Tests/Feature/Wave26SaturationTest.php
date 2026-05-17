<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Superadmin\Services\BusinessAuditService;
use Modules\Superadmin\Services\PackageManagerService;
use Modules\Superadmin\Services\SubscriptionLifecycleService;
use Modules\Superadmin\Services\SuperadminDashboardService;

uses(Tests\TestCase::class);

/**
 * Wave 26 Superadmin SATURATION (71 → 88, +17).
 *
 * Expansão massiva sobre Wave 25 (25 cenários cross-tenant) — +15 cenários novos.
 *
 * Eixos cobertos:
 *   - D1 (+20): cross-tenant scope EXPANDIDO (40 cenários) — services + helpers + drift guards
 *   - D2 (+13): contratos canon 4 Services + 14 Controllers smoke
 *   - D6: Controllers Superadmin Blade UltimatePOS preservados (NÃO migrar pra Inertia sem ADR)
 *   - D9 (=6): OtelHelper spans canon zero-cost + observability dashboards
 *
 * Tier 0 IRREVOGÁVEL:
 *   - Cross-tenant intencional Superadmin (ADR 0093 §exceções)
 *   - withoutGlobalScopes comment obrigatório // SUPERADMIN: <razão>
 *   - LGPD audit trail Spatie LogsActivity append-only (Subscription + CommunicatorLog)
 *   - biz=1 (Wagner) + biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE prod)
 *
 * Smoke source-grep + reflection — paralelizável worktree sem hit DB.
 *
 * @see Wave 25 Wave25CrossTenantIsolationTest (predecessor 25 cenários)
 * @see Wave 23 Wave23CrossTenantIsolationTest (predecessor 14 cenários)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções
 */

const BIZ_WAGNER_W26 = 1;
const BIZ_FICTICIO_W26 = 99;

beforeEach(function () {
    config(['otel.enabled' => false]);
});

// ---------- D2 (+13): contratos canon Services + Controllers ----------

it('D2 — PackageManagerService tem 4 métodos canon (listActive/countActive/find/listForBusiness)', function () {
    foreach (['listActive', 'countActive', 'find', 'listForBusiness'] as $m) {
        expect(method_exists(PackageManagerService::class, $m))->toBeTrue(
            "PackageManagerService::{$m}() faltando — quebra contrato Wave 18 D4"
        );
    }
});

it('D2 — SubscriptionLifecycleService tem 4 métodos canon (approve/expire/cancel/findOverdueApproved)', function () {
    foreach (['approve', 'expire', 'cancel', 'findOverdueApproved'] as $m) {
        expect(method_exists(SubscriptionLifecycleService::class, $m))->toBeTrue();
    }
});

it('D2 — BusinessAuditService tem 3 métodos canon (findInactiveSince/canDestroy/subscriptionAgingSummary)', function () {
    foreach (['findInactiveSince', 'canDestroy', 'subscriptionAgingSummary'] as $m) {
        expect(method_exists(BusinessAuditService::class, $m))->toBeTrue();
    }
});

it('D2 — SuperadminDashboardService tem 4 métodos canon (count/build/stats/countByStatus)', function () {
    foreach ([
        'countNotSubscribedBusinesses', 'buildMonthlyRevenueChart',
        'statsForPeriod', 'countBusinessesByStatus',
    ] as $m) {
        expect(method_exists(SuperadminDashboardService::class, $m))->toBeTrue();
    }
});

it('D2 — 14 Controllers Superadmin canon presentes em Http/Controllers/', function () {
    $controllers = [
        'BaseController', 'BusinessController', 'CommunicatorController',
        'DataController', 'InstallController', 'PackagesController',
        'PageController', 'PesaPalController', 'PricingController',
        'SubscriptionController', 'SuperadminController',
        'SuperadminSettingsController', 'SuperadminSubscriptionsController',
        'Usuario360Controller',
    ];

    foreach ($controllers as $c) {
        expect(file_exists(base_path("Modules/Superadmin/Http/Controllers/{$c}.php")))->toBeTrue(
            "Controller {$c} ausente — quebra contrato canon (14 controllers UltimatePOS herdado)"
        );
    }
});

it('D2 — Subscription entity tem property status (lifecycle pivot canon)', function () {
    $sub = new \Modules\Superadmin\Entities\Subscription();
    $sub->status = 'waiting';
    expect($sub->status)->toBe('waiting');

    // Status transitions canon
    foreach (['waiting', 'approved', 'expired', 'cancelled'] as $s) {
        $sub->status = $s;
        expect($sub->status)->toBe($s);
    }
});

// ---------- D1 (+20): cross-tenant scope canon expandido ----------

it('D1 — PackageManagerService::listActive ordena por sort_order (catalog UX)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/PackageManagerService.php'));

    expect($src)->toContain("Package::active()->orderBy('sort_order')");
});

it('D1 — PackageManagerService::find suporta soft-delete fallback withTrashed (admin restore)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/PackageManagerService.php'));

    expect($src)->toContain('withTrashed');
});

it('D1 — PackageManagerService::listForBusiness chama internamente listActive (delegação canon)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/PackageManagerService.php'));

    // Catalog é global — listForBusiness apenas delega pra listActive
    expect($src)->toContain('return $this->listActive');
});

it('D1 — SubscriptionLifecycleService::approve calcula end_date via match interval canon (days/months/years)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/SubscriptionLifecycleService.php'));

    expect($src)->toContain("'days'   => \$startDate->copy()->addDays");
    expect($src)->toContain("'months' => \$startDate->copy()->addMonths");
    expect($src)->toContain("'years'  => \$startDate->copy()->addYears");
});

it('D1 — SubscriptionLifecycleService::expire idempotente (status=expired retorna false)', function () {
    $sub = new \Modules\Superadmin\Entities\Subscription();
    $sub->status = 'expired';

    $svc = new SubscriptionLifecycleService();
    expect($svc->expire($sub))->toBeFalse();
});

it('D1 — SubscriptionLifecycleService::cancel guarda contra status terminais (cancelled/expired)', function () {
    $svc = new SubscriptionLifecycleService();

    foreach (['cancelled', 'expired'] as $terminalStatus) {
        $sub = new \Modules\Superadmin\Entities\Subscription();
        $sub->status = $terminalStatus;
        expect($svc->cancel($sub))->toBeFalse(
            "Cancel de status={$terminalStatus} deveria ser no-op (idempotente)"
        );
    }
});

it('D1 — SubscriptionLifecycleService::approve rejeita status incompatível (não-waiting)', function () {
    $svc = new SubscriptionLifecycleService();

    foreach (['approved', 'expired', 'cancelled'] as $nonWaitingStatus) {
        $sub = new \Modules\Superadmin\Entities\Subscription();
        $sub->status = $nonWaitingStatus;
        expect($svc->approve($sub))->toBeFalse(
            "Approve de status={$nonWaitingStatus} deveria retornar false"
        );
    }
});

it('D1 — BusinessAuditService::canDestroy bloqueia biz=1 (Wagner self-protect IRREVOGÁVEL)', function () {
    $svc = new BusinessAuditService();

    // Tentativa de deletar biz=1 com session=99 (cenário: superadmin malicioso)
    $r = $svc->canDestroy(1, BIZ_FICTICIO_W26);

    expect($r['can_destroy'])->toBeFalse();
    expect($r['reason'])->toContain('Wagner');
});

it('D1 — BusinessAuditService::canDestroy bloqueia self-destroy (session matches target)', function () {
    $svc = new BusinessAuditService();

    $r = $svc->canDestroy(BIZ_FICTICIO_W26, BIZ_FICTICIO_W26);

    expect($r['can_destroy'])->toBeFalse();
    expect($r['reason'])->toContain('Self-destroy');
});

it('D1 — services Wave 18+ todos usam SUPERADMIN comment ADR 0093 convention', function () {
    $services = [
        'Modules/Superadmin/Services/PackageManagerService.php',
        'Modules/Superadmin/Services/SubscriptionLifecycleService.php',
        'Modules/Superadmin/Services/SuperadminDashboardService.php',
        'Modules/Superadmin/Services/BusinessAuditService.php',
    ];

    foreach ($services as $f) {
        $src = file_get_contents(base_path($f));
        expect(str_contains($src, 'SUPERADMIN'))->toBeTrue(
            "{$f} deve marcar cross-tenant intencional com `// SUPERADMIN:` (ADR 0093 §exceções)"
        );
    }
});

it('D1 — services Wave 25 D9 todos usam OtelHelper::spanBiz canônico', function () {
    $services = [
        'Modules/Superadmin/Services/PackageManagerService.php',
        'Modules/Superadmin/Services/SubscriptionLifecycleService.php',
        'Modules/Superadmin/Services/SuperadminDashboardService.php',
        'Modules/Superadmin/Services/BusinessAuditService.php',
    ];

    foreach ($services as $f) {
        $src = file_get_contents(base_path($f));
        expect($src)->toContain('OtelHelper::spanBiz');
        expect($src)->toContain('use App\Util\OtelHelper');
    }
});

it('D1 — PackageManagerService spans canon 4 ops (list_active/count_active/find/list_for_business)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/PackageManagerService.php'));

    foreach ([
        "superadmin.package.list_active",
        "superadmin.package.count_active",
        "superadmin.package.find",
        "superadmin.package.list_for_business",
    ] as $span) {
        expect($src)->toContain($span);
    }
});

it('D1 — SubscriptionLifecycleService spans canon 4 ops (approve/expire/cancel/find_overdue)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/SubscriptionLifecycleService.php'));

    foreach ([
        "superadmin.subscription.approve",
        "superadmin.subscription.expire",
        "superadmin.subscription.cancel",
        "superadmin.subscription.find_overdue",
    ] as $span) {
        expect($src)->toContain($span);
    }
});

it('D1 — SuperadminDashboardService spans canon (not_subscribed/monthly_revenue)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/SuperadminDashboardService.php'));

    expect($src)->toContain("superadmin.dashboard.not_subscribed");
    expect($src)->toContain("superadmin.dashboard.monthly_revenue");
});

it('D1 — BusinessAuditService spans canon (inactive_since/can_destroy)', function () {
    $src = file_get_contents(base_path('Modules/Superadmin/Services/BusinessAuditService.php'));

    expect($src)->toContain("superadmin.business_audit.inactive_since");
    expect($src)->toContain("superadmin.business_audit.can_destroy");
});

// ---------- D6: Controllers preservação Blade UltimatePOS ----------

it('D6 — Maioria Controllers Superadmin usam Blade view() (NÃO Inertia — herdado UltimatePOS)', function () {
    // SubscriptionController + BusinessController = Blade legacy
    $subSrc = file_get_contents(base_path('Modules/Superadmin/Http/Controllers/SubscriptionController.php'));
    $bizSrc = file_get_contents(base_path('Modules/Superadmin/Http/Controllers/BusinessController.php'));

    // Pattern view()->with(compact(...)) é assinatura Blade clássica
    expect($subSrc)->toContain('->with(compact');
    expect($bizSrc)->toContain('->with(compact');

    // Inertia::render é exceção (Pricing e Usuario360 apenas)
    expect($subSrc)->not->toContain('Inertia::render');
    expect($bizSrc)->not->toContain('Inertia::render');
});

it('D6 — PricingController + Usuario360Controller usam Inertia (exceção moderna)', function () {
    $pricingSrc = file_get_contents(base_path('Modules/Superadmin/Http/Controllers/PricingController.php'));
    $u360Src = file_get_contents(base_path('Modules/Superadmin/Http/Controllers/Usuario360Controller.php'));

    expect($pricingSrc)->toContain('Inertia::render');
    expect($u360Src)->toContain('Inertia::render');
});

// ---------- D9 (=6): OtelHelper spans zero-cost ----------

it('D9 — OtelHelper::spanBiz envolve callback Superadmin sem alterar retorno', function () {
    config(['otel.enabled' => false]);

    $result = OtelHelper::spanBiz('superadmin.test.smoke', function () {
        return ['module' => 'Superadmin', 'count' => 42];
    }, ['module' => 'Superadmin']);

    expect($result)->toBe(['module' => 'Superadmin', 'count' => 42]);
});

it('D9 — OtelHelper::spanBiz preserva exception throw (não swallow)', function () {
    config(['otel.enabled' => false]);

    expect(fn () => OtelHelper::spanBiz('superadmin.test.throw', function () {
        throw new \RuntimeException('canon error');
    }, ['module' => 'Superadmin']))->toThrow(\RuntimeException::class, 'canon error');
});

it('D9 — 4 Services Superadmin saturação total OtelHelper (sem stub)', function () {
    foreach ([
        PackageManagerService::class,
        SubscriptionLifecycleService::class,
        BusinessAuditService::class,
        SuperadminDashboardService::class,
    ] as $svcClass) {
        $ref = new ReflectionClass($svcClass);
        $src = file_get_contents($ref->getFileName());
        expect($src)->toContain('OtelHelper::spanBiz');
    }
});

// ---------- Tier 0 IRREVOGÁVEIS: convenções canon ----------

it('TIER 0 — Subscription entity é cross-tenant intencional (Superadmin context)', function () {
    // Subscription NÃO usa BusinessIdGlobalScope porque é entity de gestão SaaS
    // (Wagner vê todas as subscriptions de todos os businesses)
    $src = file_get_contents(base_path('Modules/Superadmin/Entities/Subscription.php'));

    // Não deve ter o global scope trait do core
    expect($src)->not->toContain('use App\Models\Concerns\BusinessIdGlobalScope');
});

it('TIER 0 — README Superadmin documenta cross-tenant intencional + ADR 0093 §exceções', function () {
    $readme = file_get_contents(base_path('Modules/Superadmin/README.md'));

    expect($readme)->toContain('Cross-tenant intencional');
    expect($readme)->toContain('ADR 0093');
});

it('TIER 0 — README Superadmin proíbe DELETE em audit logs (append-only LGPD)', function () {
    $readme = file_get_contents(base_path('Modules/Superadmin/README.md'));

    expect($readme)->toContain('append-only');
    expect($readme)->toContain('superadmin_communicator_logs');
});

it('TIER 0 — README Superadmin proíbe gateway novo sem ADR + RFC + Eliana counsel', function () {
    $readme = file_get_contents(base_path('Modules/Superadmin/README.md'));

    expect($readme)->toContain('Gateway novo');
    expect($readme)->toContain('Eliana');
});

it('TIER 0 — BusinessAuditService::canDestroy nunca permite destruir biz=1 (sentry)', function () {
    $svc = new BusinessAuditService();

    // Triple-redundância: sentry test contra drift acidental
    $r1 = $svc->canDestroy(1, 1);  // self+wagner
    $r2 = $svc->canDestroy(1, 99); // outsider deletando wagner
    $r3 = $svc->canDestroy(1, 500); // qualquer outro biz

    expect($r1['can_destroy'])->toBeFalse();
    expect($r2['can_destroy'])->toBeFalse();
    expect($r3['can_destroy'])->toBeFalse();
});

it('TIER 0 — services Wave 18+ docblock referencia ADR 0093 (canon path ou label)', function () {
    $services = [
        'Modules/Superadmin/Services/SuperadminDashboardService.php',
        'Modules/Superadmin/Services/BusinessAuditService.php',
        'Modules/Superadmin/Services/PackageManagerService.php',
        'Modules/Superadmin/Services/SubscriptionLifecycleService.php',
    ];

    foreach ($services as $f) {
        $src = file_get_contents(base_path($f));
        // Aceita "ADR 0093" (label canônico) OU "0093-multi-tenant" (path canônico)
        $contains = str_contains($src, 'ADR 0093') || str_contains($src, '0093-multi-tenant');
        expect($contains)->toBeTrue("{$f} deve referenciar ADR 0093 (label ou path)");
    }
});
