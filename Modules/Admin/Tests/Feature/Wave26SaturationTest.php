<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Validator;
use Modules\Admin\Http\Middleware\IsWagner;
use Modules\Admin\Http\Requests\AlertAcknowledgeRequest;
use Modules\Admin\Http\Requests\RemediationRequest;
use Modules\Admin\Http\Requests\StoreUserRequest;
use Modules\Admin\Http\Requests\UpdatePermissionRequest;

uses(Tests\TestCase::class);

/**
 * Wave 26 Admin SATURATION (77 → 88, +11).
 *
 * Expansão sobre Wave 25 (FormRequests Remediation + AlertAcknowledge + IsWagner).
 *
 * Eixos:
 *   - D2 (+13): contratos canon (StoreUser/UpdatePermission/Remediation/AlertAck) + IsWagner edge
 *   - D6 (=8): preserva Inertia::render pattern + GovernanceV4 defer canônico
 *   - D8 (+5): mais validação FormRequests (boundary + payload)
 *   - D9 (=6): OtelHelper spans canon
 *
 * Tier 0 IRREVOGÁVEL:
 *   - Admin é Wagner-only via middleware IsWagner (3 ANDs)
 *   - PII NUNCA em reason/payload (FormRequest valida)
 *   - ADR whitelist Tier 0 fechada (0093/0094/0053/0062/0143)
 *
 * Smoke pura — sem hit DB. Paralelizável.
 *
 * @see Wave 25 Wave25FormRequestsTest + Wave 23 IsWagnerEdgeCasesTest (predecessors)
 * @see memory/decisions/0122-admin-center-ct100.md
 */

beforeEach(function () {
    config(['otel.enabled' => false]);
});

// ---------- D8 (+5): boundary validation FormRequests ----------

it('D8 — RemediationRequest reason boundary EXATAMENTE 5 chars passa', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'retry_health_check',
        'reason'           => '12345',  // exatamente min:5
        'confirm'          => true,
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

it('D8 — RemediationRequest reason boundary EXATAMENTE 500 chars passa', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'retry_health_check',
        'reason'           => str_repeat('a', 500),  // exatamente max:500
        'confirm'          => true,
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

it('D8 — RemediationRequest reason 501 chars falha (max guard)', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'retry_health_check',
        'reason'           => str_repeat('a', 501),
        'confirm'          => true,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('reason'))->toBeTrue();
});

it('D8 — RemediationRequest TIER_0_ADRS cobre 5 ADRs canônicas (0093/0094/0053/0062/0143)', function () {
    $expected = ['0093', '0094', '0053', '0062', '0143'];
    foreach ($expected as $adr) {
        expect(RemediationRequest::TIER_0_ADRS)->toContain($adr);
    }
    expect(count(RemediationRequest::TIER_0_ADRS))->toBeGreaterThanOrEqual(5);
});

it('D8 — RemediationRequest todos 4 remediation_kind canon aceitos', function () {
    $r = new RemediationRequest();
    foreach (['retry_health_check', 'invalidate_cache', 'reset_global_scope', 'notify_team'] as $kind) {
        $v = Validator::make([
            'adr_id'           => '0093',
            'check_name'       => 'multi_tenant_isolation',
            'remediation_kind' => $kind,
            'reason'           => 'razão válida pra ' . $kind,
            'confirm'          => true,
        ], $r->rules());

        expect($v->passes())->toBeTrue("kind={$kind} deveria ser aceito — {$v->errors()->toJson()}");
    }
});

it('D8 — AlertAcknowledgeRequest snooze boundary 5 e 60 minutos aceitos', function () {
    $r = new AlertAcknowledgeRequest();

    foreach ([5, 60] as $snooze) {
        $v = Validator::make([
            'check_name'     => 'multi_tenant_isolation',
            'adr_id'         => '0093',
            'snooze_minutes' => $snooze,
            'reason'         => 'razão válida boundary',
            'confirm'        => true,
        ], $r->rules());

        expect($v->passes())->toBeTrue("snooze={$snooze}min deveria ser aceito");
    }
});

it('D8 — AlertAcknowledgeRequest check_name max:64 chars boundary', function () {
    $r = new AlertAcknowledgeRequest();

    // Exatamente 64 chars passa
    $v = Validator::make([
        'check_name'     => str_repeat('a', 64),
        'adr_id'         => '0093',
        'snooze_minutes' => 30,
        'reason'         => 'razão',
        'confirm'        => true,
    ], $r->rules());
    expect($v->passes())->toBeTrue();

    // 65 chars falha
    $v2 = Validator::make([
        'check_name'     => str_repeat('a', 65),
        'adr_id'         => '0093',
        'snooze_minutes' => 30,
        'reason'         => 'razão',
        'confirm'        => true,
    ], $r->rules());
    expect($v2->fails())->toBeTrue();
});

// ---------- D2 (+13): contratos canon FormRequests existentes ----------

it('D2 — StoreUserRequest existe + namespace canônico Modules\\Admin\\Http\\Requests', function () {
    expect(class_exists(StoreUserRequest::class))->toBeTrue();
    expect((new StoreUserRequest()))->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

it('D2 — UpdatePermissionRequest existe + namespace canônico', function () {
    expect(class_exists(UpdatePermissionRequest::class))->toBeTrue();
    expect((new UpdatePermissionRequest()))->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

it('D2 — 4 FormRequests canon Modules\\Admin\\Http\\Requests todos files presentes', function () {
    $files = [
        'StoreUserRequest', 'UpdatePermissionRequest',
        'RemediationRequest', 'AlertAcknowledgeRequest',
    ];
    foreach ($files as $f) {
        $path = base_path("Modules/Admin/Http/Requests/{$f}.php");
        expect(file_exists($path))->toBeTrue("FormRequest {$f} ausente em filesystem");
    }
});

it('D2 — IsWagner valida defesa profunda (3 ANDs canon)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Middleware/IsWagner.php'));

    // Defense in depth ADR 0122 §1 — 3 ANDs garantem hardening
    expect($src)->toContain('$userIdMatch && $businessIdMatch && $hasRole');
});

it('D2 — IsWagner fallback emergencial via env (DB restore proof)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Middleware/IsWagner.php'));

    // Override emergencial — Wagner não fica trancado se DB restore zerar user_id=1
    expect($src)->toContain('fallback_username');
    expect($src)->toContain('$fallbackUsername && $user->username === $fallbackUsername && $hasRole');
});

it('D2 — IsWagner bypass_local AND env=local (defesa prod canon)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Middleware/IsWagner.php'));

    // Conjunção dupla — bypass não vaza em prod mesmo se config vier true
    expect($src)->toContain("config('admin.bypass_local') && app()->environment('local')");
});

it('D2 — IsWagner audit log unauthorized via Log channel stack', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Middleware/IsWagner.php'));

    expect($src)->toContain("Log::channel('stack')->warning('admin.unauthorized'");
    expect($src)->toContain("'no_auth'");
    expect($src)->toContain("'gate_check_failed'");
});

it('D2 — IsWagner mensagem 403 PT-BR (Wagner-only)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Middleware/IsWagner.php'));

    expect($src)->toContain("abort(403, 'Admin Center é Wagner-only.')");
});

// ---------- D6: Inertia patterns canônicos ----------

it('D6 — GovernanceV4Dashboard usa Inertia::defer canônico (3 props caras)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php'));

    expect($src)->toContain("Inertia::defer(fn () => \$this->buildModulesPayload())");
    expect($src)->toContain("Inertia::defer(fn () => \$this->buildAiSuggestionsPayload())");
    expect($src)->toContain("Inertia::defer(fn () => \$this->buildPairedViolationsPayload())");
});

it('D6 — IndexController preserva eager load (rollback PR #963 lição)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Controllers/IndexController.php'));

    // ROLLBACK Wave L/W7 PR #963 — Inertia::defer quebrava Pages (initial render undefined)
    // Wave 18 (D6+D9) mantém eager load até frontend Admin/Index.tsx ter <Deferred> wrap
    expect($src)->toContain('ROLLBACK Wave L/W7 PR #963');
    expect($src)->toContain('Inertia::render(\'Admin/Index\'');
    expect($src)->toContain("'widgets' => \$widgets");
});

it('D6 — IndexController wrap 10 widgets em OtelHelper::spanBiz (agregado D9)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Controllers/IndexController.php'));

    expect($src)->toContain("OtelHelper::spanBiz('admin.index.widgets'");
    expect($src)->toContain("'widget_count' => 10");
});

it('D6 — FeatureFlagsController Show usa defer canon (Wave 25 D6 saturação)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Controllers/FeatureFlagsController.php'));

    expect($src)->toContain("Inertia::defer(fn () =>");
    expect($src)->toContain("'audits'       => Inertia::defer");
});

// ---------- D9 (=6): OTel canon ----------

it('D9 — Admin GovernanceV4DashboardController usa OtelHelper::span', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php'));

    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("OtelHelper::span('admin.governance_v4");
});

it('D9 — OtelHelper::spanBiz zero-cost smoke (Admin)', function () {
    config(['otel.enabled' => false]);

    $result = OtelHelper::spanBiz('admin.test.smoke', fn () => ['widget' => 'ok'], [
        'module' => 'Admin', 'op' => 'smoke',
    ]);

    expect($result)->toBe(['widget' => 'ok']);
});

// ---------- Tier 0 IRREVOGÁVEIS: convenções canon ----------

it('TIER 0 — Admin é Wagner-only (config admin.wagner_user_id + business_id canônico)', function () {
    $configPath = base_path('Modules/Admin/Config/config.php');
    expect(file_exists($configPath))->toBeTrue();

    $src = file_get_contents($configPath);
    foreach (['wagner_user_id', 'wagner_business_id', 'bypass_local', 'fallback_username'] as $key) {
        expect($src)->toContain($key);
    }
});

it('TIER 0 — RemediationRequest blinda PII em reason (documento docblock)', function () {
    $src = file_get_contents(base_path('Modules/Admin/Http/Requests/RemediationRequest.php'));

    // Docblock canon — instrui leitor a NUNCA passar PII em reason
    expect($src)->toContain('PII NUNCA aparece em `reason`');
});

it('TIER 0 — AlertAcknowledgeRequest snooze cap 60min (Wagner não silencia Tier 0 por dias)', function () {
    expect(AlertAcknowledgeRequest::MAX_SNOOZE_MINUTES)->toBe(60);
    expect(AlertAcknowledgeRequest::MAX_SNOOZE_MINUTES)->toBeLessThanOrEqual(60);
});

it('TIER 0 — IsWagner é registrado como alias is-wagner no AdminServiceProvider', function () {
    $src = file_get_contents(base_path('Modules/Admin/Providers/AdminServiceProvider.php'));

    expect($src)->toContain("aliasMiddleware('is-wagner', IsWagner::class)");
});
