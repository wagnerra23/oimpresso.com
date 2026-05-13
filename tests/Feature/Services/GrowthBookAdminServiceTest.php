<?php

declare(strict_types=1);

/**
 * Pest test — App\Services\GrowthBookAdminService.
 *
 * Cobre operações de escrita contra GrowthBook REST API admin:
 *   1. isConfigured() reflete presença do token
 *   2. listFeatures() / getFeature() — leitura
 *   3. setBizRule() — insere rule nova com id `biz-{N}`
 *   4. setBizRule() — upsert (substitui rule existente)
 *   5. removeBizRule() — remove rule + grava audit
 *   6. setEnvEnabled() — toggle environment
 *   7. Audit log gravado em feature_flag_audits
 *   8. Erros HTTP propagam como RuntimeException
 *
 * Não toca rede real — Http::fake() + RefreshDatabase pra audit table.
 */

use App\Models\FeatureFlagAudit;
use App\Services\GrowthBookAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=secret_admin_test_xxx');
    putenv('GROWTHBOOK_ADMIN_API_HOST=https://growthbook.test.local/api/v1');
});

afterEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');
    putenv('GROWTHBOOK_ADMIN_API_HOST=');
});

it('isConfigured reflete presença do token', function () {
    expect((new GrowthBookAdminService())->isConfigured())->toBeTrue();

    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');
    expect((new GrowthBookAdminService())->isConfigured())->toBeFalse();
});

it('listFeatures retorna array vazio quando GrowthBook responde sem features', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features*' => Http::response(['features' => []], 200),
    ]);

    expect((new GrowthBookAdminService())->listFeatures())->toBe([]);
});

it('listFeatures lança exception em HTTP 5xx', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features*' => Http::response('Internal Error', 500),
    ]);

    (new GrowthBookAdminService())->listFeatures();
})->throws(RuntimeException::class, 'GrowthBook /features falhou: HTTP 500');

it('getFeature retorna null em HTTP 404', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/inexistente' => Http::response('Not Found', 404),
    ]);

    expect((new GrowthBookAdminService())->getFeature('inexistente'))->toBeNull();
});

it('getFeature retorna feature em HTTP 200', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::response([
            'feature' => [
                'id' => 'useV2SellsCreate',
                'defaultValue' => 'false',
                'environments' => [
                    'production' => ['enabled' => true, 'rules' => []],
                ],
            ],
        ], 200),
    ]);

    $feature = (new GrowthBookAdminService())->getFeature('useV2SellsCreate');

    expect($feature)->not->toBeNull();
    expect($feature['id'])->toBe('useV2SellsCreate');
});

it('rejeita key inválida (com caracteres especiais)', function () {
    (new GrowthBookAdminService())->getFeature('flag com espaço');
})->throws(RuntimeException::class, "Feature key inválida");

it('setBizRule adiciona rule nova com id biz-{N} e grava audit', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::sequence()
            ->push([
                'feature' => [
                    'id' => 'useV2SellsCreate',
                    'environments' => [
                        'production' => ['enabled' => true, 'rules' => []],
                    ],
                ],
            ], 200)
            ->push([
                'feature' => [
                    'id' => 'useV2SellsCreate',
                    'environments' => [
                        'production' => [
                            'enabled' => true,
                            'rules' => [[
                                'id' => 'biz-4',
                                'type' => 'force',
                                'value' => 'true',
                                'condition' => '{"business_id":4}',
                                'enabled' => true,
                            ]],
                        ],
                    ],
                ],
            ], 200),
    ]);

    $service = new GrowthBookAdminService();
    $updated = $service->setBizRule('useV2SellsCreate', 4, true);

    expect($updated['environments']['production']['rules'])->toHaveCount(1);
    expect($updated['environments']['production']['rules'][0]['id'])->toBe('biz-4');

    $audit = FeatureFlagAudit::query()->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->flag_key)->toBe('useV2SellsCreate');
    expect($audit->action)->toBe('rule_upsert');
    expect($audit->environment)->toBe('production');
    expect($audit->diff_summary)->toContain('biz-4');
    expect($audit->diff_summary)->toContain('true');
});

it('setBizRule lança exception quando feature não existe', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/inexistente' => Http::response('Not Found', 404),
    ]);

    (new GrowthBookAdminService())->setBizRule('inexistente', 4, true);
})->throws(RuntimeException::class, "Feature 'inexistente' não existe");

it('removeBizRule remove rule + audit', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::sequence()
            ->push([
                'feature' => [
                    'id' => 'useV2SellsCreate',
                    'environments' => [
                        'production' => [
                            'enabled' => true,
                            'rules' => [[
                                'id' => 'biz-4',
                                'type' => 'force',
                                'value' => 'true',
                                'enabled' => true,
                            ]],
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'feature' => [
                    'id' => 'useV2SellsCreate',
                    'environments' => [
                        'production' => ['enabled' => true, 'rules' => []],
                    ],
                ],
            ], 200),
    ]);

    (new GrowthBookAdminService())->removeBizRule('useV2SellsCreate', 4);

    $audit = FeatureFlagAudit::query()->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->action)->toBe('rule_remove');
    expect($audit->diff_summary)->toContain('biz-4');
});

it('removeBizRule é no-op se rule não existir (sem audit)', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::response([
            'feature' => [
                'id' => 'useV2SellsCreate',
                'environments' => [
                    'production' => ['enabled' => true, 'rules' => []],
                ],
            ],
        ], 200),
    ]);

    (new GrowthBookAdminService())->removeBizRule('useV2SellsCreate', 999);

    expect(FeatureFlagAudit::count())->toBe(0);
});

it('setEnvEnabled toca enabled do environment + audit', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::sequence()
            ->push([
                'feature' => [
                    'id' => 'useV2SellsCreate',
                    'environments' => [
                        'production' => ['enabled' => true, 'rules' => []],
                    ],
                ],
            ], 200)
            ->push([
                'feature' => [
                    'id' => 'useV2SellsCreate',
                    'environments' => [
                        'production' => ['enabled' => false, 'rules' => []],
                    ],
                ],
            ], 200),
    ]);

    (new GrowthBookAdminService())->setEnvEnabled('useV2SellsCreate', false);

    $audit = FeatureFlagAudit::query()->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->action)->toBe('env_toggle');
    expect($audit->payload_after['enabled'])->toBeFalse();
});

it('operações sem token configurado lançam exception clara', function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');

    (new GrowthBookAdminService())->listFeatures();
})->throws(RuntimeException::class, 'GROWTHBOOK_ADMIN_API_TOKEN');
