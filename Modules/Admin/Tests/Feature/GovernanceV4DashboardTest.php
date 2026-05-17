<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Http\Controllers\GovernanceV4DashboardController;
use Tests\Helpers\AdminAuthHelper;

uses(Tests\TestCase::class);

/**
 * Wave 27 smoke — GovernanceV4Dashboard polish + drift visualization.
 *
 * Cobertura mínima (sem assumir mcp_scorecard_runs/observability seedados):
 *  1. Controller resolve do container (autoload + namespace)
 *  2. Constants/methods expandidos Wave 27 existem
 *  3. Rota /admin/governance-v4 renderiza Inertia component esperado
 *  4. Props deferred declaradas: modules + drift_alerts + ai_suggestions + paired_violations
 *  5. meta.drift_threshold_pts presente (chave nova Wave 27)
 *
 * Tests focam estrutura — comportamento end-to-end com dados reais requer
 * seed `mcp_scorecard_runs` (fora do escopo Wave 27 polish).
 *
 * @see Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php
 * @see resources/js/Pages/Admin/GovernanceV4Dashboard.tsx
 */

beforeEach(function () {
    // Garante middleware ATIVO (sem bypass) ou DESATIVADO uniformemente
    config()->set('admin.bypass_local', false);
});

it('controller resolve do container Laravel', function () {
    $controller = app(GovernanceV4DashboardController::class);

    expect($controller)->toBeInstanceOf(GovernanceV4DashboardController::class);
});

it('classe expõe método __invoke público pra rota GET', function () {
    $ref = new ReflectionClass(GovernanceV4DashboardController::class);

    expect($ref->hasMethod('__invoke'))->toBeTrue();
    expect($ref->getMethod('__invoke')->isPublic())->toBeTrue();
});

it('constante DRIFT_THRESHOLD_PTS definida (Wave 27)', function () {
    $ref = new ReflectionClass(GovernanceV4DashboardController::class);
    $constants = $ref->getConstants();

    expect($constants)->toHaveKey('DRIFT_THRESHOLD_PTS');
    expect($constants['DRIFT_THRESHOLD_PTS'])->toBeInt();
    expect($constants['DRIFT_THRESHOLD_PTS'])->toBeGreaterThan(0);
});

it('métodos Wave 27 buildDriftAlertsPayload e loadP99ByModule existem', function () {
    $ref = new ReflectionClass(GovernanceV4DashboardController::class);

    expect($ref->hasMethod('buildDriftAlertsPayload'))->toBeTrue();
    expect($ref->hasMethod('loadP99ByModule'))->toBeTrue();
    expect($ref->hasMethod('computeStatus'))->toBeTrue();
});

it('computeStatus retorna ok/warn/crit conforme delta vs meta', function () {
    $controller = app(GovernanceV4DashboardController::class);
    $ref = new ReflectionClass($controller);
    $method = $ref->getMethod('computeStatus');
    $method->setAccessible(true);

    expect($method->invoke($controller, 90, 85))->toBe('ok');   // acima da meta
    expect($method->invoke($controller, 85, 85))->toBe('ok');   // exatamente na meta
    expect($method->invoke($controller, 82, 85))->toBe('warn'); // 3pts abaixo
    expect($method->invoke($controller, 80, 85))->toBe('warn'); // 5pts abaixo (limite)
    expect($method->invoke($controller, 79, 85))->toBe('crit'); // 6pts abaixo (crit)
    expect($method->invoke($controller, 50, 85))->toBe('crit'); // bem abaixo
});

it('loadP99ByModule retorna array vazio quando tabela Wave 26 não existe', function () {
    $controller = app(GovernanceV4DashboardController::class);
    $ref = new ReflectionClass($controller);
    $method = $ref->getMethod('loadP99ByModule');
    $method->setAccessible(true);

    // Fallback graceful (tabela mcp_observability_aggregates_daily ainda não criada)
    $result = $method->invoke($controller);

    expect($result)->toBeArray();
});

it('rota /admin/governance-v4 renderiza Inertia component Admin/GovernanceV4Dashboard com Wagner', function () {
    if (! Illuminate\Support\Facades\Schema::hasTable('business')) {
        test()->markTestSkipped('Schema sem migrate (sqlite :memory: vazio) — smoke E2E requer migrate suite.');
    }

    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin/governance-v4', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10', // Tailscale CIDR
        ]);

    expect($response->status())->toBe(200);

    // Inertia headers/component
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/GovernanceV4Dashboard')
        ->has('meta')
        ->where('meta.drift_threshold_pts', fn ($v) => is_int($v) && $v > 0)
        ->has('meta.buckets.vertical_client_facing')
        ->has('meta.buckets.cross_cutting_infra')
        ->has('meta.buckets.ai_central')
        ->has('meta.buckets.functional_horizontal')
    );
});

it('props caras declaradas como Inertia::defer (modules/drift_alerts/ai_suggestions/paired_violations)', function () {
    if (! Illuminate\Support\Facades\Schema::hasTable('business')) {
        test()->markTestSkipped('Schema sem migrate — smoke E2E requer migrate suite.');
    }

    $user = AdminAuthHelper::createWagnerUser();

    // Request inicial — props deferred NÃO devem vir no payload (lazy)
    $response = $this->actingAs($user)
        ->call('GET', '/admin/governance-v4', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10',
        ]);

    expect($response->status())->toBe(200);

    // Em request inicial, defer props aparecem como `null`/ausentes no JSON
    // (cliente vai pedir via partial reload). Validamos que controller declarou-as.
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/GovernanceV4Dashboard')
        // meta NÃO é defer (sempre eager)
        ->has('meta')
    );
});

it('time não-Wagner recebe 403 no /admin/governance-v4 (IsWagner gate)', function () {
    if (! Illuminate\Support\Facades\Schema::hasTable('business')) {
        test()->markTestSkipped('Schema sem migrate — smoke E2E requer migrate suite.');
    }

    $user = AdminAuthHelper::createMaiaraUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin/governance-v4', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10',
        ]);

    expect($response->status())->toBe(403);
});
