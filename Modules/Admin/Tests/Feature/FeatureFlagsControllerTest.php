<?php

declare(strict_types=1);

use App\Models\FeatureFlagAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Helpers\AdminAuthHelper;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * US-INFRA-008 (2026-05-13) — Painel /admin/feature-flags Inertia + REST API.
 *
 * Smoke tests:
 *   - Wagner Tailscale acessa Index OK
 *   - Sem auth → 403 (Tailscale primeiro)
 *   - POST biz-rule grava audit
 */

beforeEach(function () {
    config()->set('admin.bypass_local', false);
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=secret_admin_test_xxx');
    putenv('GROWTHBOOK_ADMIN_API_HOST=https://growthbook.test.local/api/v1');

    Http::fake([
        'growthbook.test.local/api/v1/features*' => Http::response(['features' => []], 200),
    ]);
});

afterEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');
    putenv('GROWTHBOOK_ADMIN_API_HOST=');
});

it('Wagner Tailscale acessa /admin/feature-flags com 200', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin/feature-flags', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10',
        ]);

    expect($response->status())->toBe(200);
});

it('Maiara Tailscale (não-Wagner) recebe 403', function () {
    $user = AdminAuthHelper::createMaiaraUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin/feature-flags', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10',
        ]);

    expect($response->status())->toBe(403);
});

it('Wagner externo (não-Tailscale) recebe 403', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin/feature-flags', [], [], [], [
            'REMOTE_ADDR' => '189.4.123.55',
        ]);

    expect($response->status())->toBe(403);
});

it('POST biz-rule grava audit em feature_flag_audits', function () {
    $user = AdminAuthHelper::createWagnerUser();

    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::sequence()
            ->push(['feature' => [
                'id' => 'useV2SellsCreate',
                'environments' => ['production' => ['enabled' => true, 'rules' => []]],
            ]], 200)
            ->push(['feature' => [
                'id' => 'useV2SellsCreate',
                'environments' => [
                    'production' => [
                        'enabled' => true,
                        'rules' => [['id' => 'biz-4', 'value' => 'true']],
                    ],
                ],
            ]], 200),
    ]);

    $response = $this->actingAs($user)
        ->call('POST', '/admin/feature-flags/useV2SellsCreate/biz-rule',
            [
                'biz_id' => 4,
                'value' => true,
                'remove' => false,
                'env' => 'production',
                'clear_cache' => false,
            ],
            [], [], [
                'REMOTE_ADDR' => '100.99.5.10',
            ]
        );

    expect($response->status())->toBe(302);  // redirect after submit
    expect(FeatureFlagAudit::count())->toBe(1);

    $audit = FeatureFlagAudit::query()->first();
    expect($audit->flag_key)->toBe('useV2SellsCreate');
    expect($audit->action)->toBe('rule_upsert');
});

it('POST biz-rule sem auth Tailscale recebe 403', function () {
    $response = $this->call('POST', '/admin/feature-flags/x/biz-rule',
        ['biz_id' => 4, 'value' => true],
        [], [], ['REMOTE_ADDR' => '189.4.123.55']
    );

    expect($response->status())->toBe(403);
});
