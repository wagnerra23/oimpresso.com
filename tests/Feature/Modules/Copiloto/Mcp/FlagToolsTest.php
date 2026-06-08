<?php

declare(strict_types=1);

/**
 * Pest tests pras 5 tools MCP de Feature Flag (US-INFRA-002 — 2026-05-13):
 * flag-list, flag-get, flag-set, flag-env-toggle, flag-cache-clear.
 *
 * Cobre auth (sem token → erro), schema (args obrigatórios), happy path com
 * Http::fake() mockando GrowthBook REST API, e gravação em feature_flag_audits.
 */

use App\Models\FeatureFlagAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Mcp\OimpressoMcpServer;
use Modules\Jana\Mcp\Tools\FlagCacheClearTool;
use Modules\Jana\Mcp\Tools\FlagEnvToggleTool;
use Modules\Jana\Mcp\Tools\FlagGetTool;
use Modules\Jana\Mcp\Tools\FlagListTool;
use Modules\Jana\Mcp\Tools\FlagSetTool;

uses(RefreshDatabase::class);

beforeEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=secret_admin_test_xxx');
    putenv('GROWTHBOOK_ADMIN_API_HOST=https://growthbook.test.local/api/v1');
});

afterEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');
    putenv('GROWTHBOOK_ADMIN_API_HOST=');
});

// ─── flag-list ─────────────────────────────────────────────────────────────

it('flag-list sem token retorna erro claro', function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');

    OimpressoMcpServer::tool(FlagListTool::class)
        ->assertSee('não configurado');
});

it('flag-list mostra features retornadas', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features*' => Http::response([
            'features' => [[
                'id' => 'useV2SellsCreate',
                'valueType' => 'boolean',
                'defaultValue' => 'false',
                'environments' => [
                    'production' => ['enabled' => true, 'rules' => [['id' => 'biz-1']]],
                ],
            ]],
        ], 200),
    ]);

    OimpressoMcpServer::tool(FlagListTool::class)
        ->assertOk()
        ->assertSee(['useV2SellsCreate', 'production', '1 rule']);
});

it('flag-list vazio retorna mensagem amigável', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features*' => Http::response(['features' => []], 200),
    ]);

    OimpressoMcpServer::tool(FlagListTool::class)
        ->assertSee('Nenhuma feature flag');
});

// ─── flag-get ──────────────────────────────────────────────────────────────

it('flag-get sem key retorna erro', function () {
    OimpressoMcpServer::tool(FlagGetTool::class, ['key' => ''])
        ->assertHasErrors();
});

it('flag-get com key inexistente retorna not found', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/inexistente' => Http::response('not found', 404),
    ]);

    OimpressoMcpServer::tool(FlagGetTool::class, ['key' => 'inexistente'])
        ->assertSee('não encontrada');
});

it('flag-get mostra rules em formato tabela markdown', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::response([
            'feature' => [
                'id' => 'useV2SellsCreate',
                'defaultValue' => 'false',
                'valueType' => 'boolean',
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

    OimpressoMcpServer::tool(FlagGetTool::class, ['key' => 'useV2SellsCreate'])
        ->assertOk()
        ->assertSee(['useV2SellsCreate', 'biz-4', 'business_id', 'production']);
});

// ─── flag-set ──────────────────────────────────────────────────────────────

it('flag-set sem biz_id retorna erro', function () {
    OimpressoMcpServer::tool(FlagSetTool::class, ['key' => 'x'])
        ->assertHasErrors();
});

it('flag-set ativa rule + grava audit', function () {
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

    OimpressoMcpServer::tool(FlagSetTool::class, [
        'key' => 'useV2SellsCreate',
        'biz_id' => 4,
        'value' => true,
        'clear_cache' => false,
    ])->assertOk()->assertSee('biz-4');

    expect(FeatureFlagAudit::count())->toBe(1);
    $audit = FeatureFlagAudit::query()->first();
    expect($audit->action)->toBe('rule_upsert');
    expect($audit->flag_key)->toBe('useV2SellsCreate');
});

it('flag-set remove=true apaga rule', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::sequence()
            ->push(['feature' => [
                'id' => 'useV2SellsCreate',
                'environments' => [
                    'production' => [
                        'enabled' => true,
                        'rules' => [['id' => 'biz-4', 'value' => 'true']],
                    ],
                ],
            ]], 200)
            ->push(['feature' => [
                'id' => 'useV2SellsCreate',
                'environments' => ['production' => ['enabled' => true, 'rules' => []]],
            ]], 200),
    ]);

    OimpressoMcpServer::tool(FlagSetTool::class, [
        'key' => 'useV2SellsCreate',
        'biz_id' => 4,
        'remove' => true,
        'clear_cache' => false,
    ])->assertOk()->assertSee('removida');

    $audit = FeatureFlagAudit::query()->latest('id')->first();
    expect($audit->action)->toBe('rule_remove');
});

// ─── flag-env-toggle ───────────────────────────────────────────────────────

it('flag-env-toggle requer enabled', function () {
    OimpressoMcpServer::tool(FlagEnvToggleTool::class, ['key' => 'x'])
        ->assertHasErrors();
});

it('flag-env-toggle desliga environment + audit env_toggle', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2SellsCreate' => Http::sequence()
            ->push(['feature' => [
                'id' => 'useV2SellsCreate',
                'environments' => ['production' => ['enabled' => true, 'rules' => []]],
            ]], 200)
            ->push(['feature' => [
                'id' => 'useV2SellsCreate',
                'environments' => ['production' => ['enabled' => false, 'rules' => []]],
            ]], 200),
    ]);

    OimpressoMcpServer::tool(FlagEnvToggleTool::class, [
        'key' => 'useV2SellsCreate',
        'enabled' => false,
        'clear_cache' => false,
    ])->assertOk()->assertSee('DESLIGADA');

    $audit = FeatureFlagAudit::query()->latest('id')->first();
    expect($audit->action)->toBe('env_toggle');
    expect($audit->payload_after['enabled'])->toBeFalse();
});

// ─── flag-cache-clear ──────────────────────────────────────────────────────

it('flag-cache-clear retorna sucesso', function () {
    OimpressoMcpServer::tool(FlagCacheClearTool::class)
        ->assertOk()
        ->assertSee('Cache local Laravel');
});
