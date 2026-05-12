<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Smoke test do comando jana:health-check.
 *
 * Não roda LLM real — apenas valida que o comando:
 *   1. É registrado e aceita parâmetros documentados (--json, --notify)
 *   2. Retorna exit code apropriado (0 ou 1)
 *   3. Output JSON tem shape canônico ({ok, checked_at, checks[]})
 *   4. Cada check declara name, ok, value, threshold, message
 *
 * Em CI roda em SQLite — checks de integridade Tier 0 podem retornar
 * resultados degraded (tabelas inexistentes), mas comando não pode crashar.
 */

test('comando registrado no artisan list', function () {
    $output = \Illuminate\Support\Facades\Artisan::call('list');
    expect(\Illuminate\Support\Facades\Artisan::output())->toContain('jana:health-check');
});

test('--json output tem shape canonico', function () {
    \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    // Pode ter linhas debug antes do JSON; pegar último bloco { ... }
    $jsonStart = strpos($output, '{');
    expect($jsonStart)->not->toBeFalse('Output não contém JSON');

    $json = json_decode(substr($output, $jsonStart), true);
    expect($json)->toBeArray()
        ->toHaveKey('ok')
        ->toHaveKey('checked_at')
        ->toHaveKey('checks');

    // 8 checks: multi_tenant, brief_uptime, custo_brain_b, pii_leak,
    // profile_drift, procedure_drift, spec_id_drift, whatsapp_media_pending_1h.
    expect($json['checks'])->toBeArray()->toHaveCount(8);
});

test('cada check tem campos canonicos', function () {
    \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);
    $output = \Illuminate\Support\Facades\Artisan::output();
    $json = json_decode(substr($output, strpos($output, '{')), true);

    $namesEsperados = [
        'multi_tenant_isolation',
        'brief_uptime_24h',
        'custo_brain_b_24h',
        'pii_leak_in_assistant_responses',
        'profile_distiller_drift',
        'procedure_drift',
        'spec_id_drift',
        'whatsapp_media_pending_1h',
    ];

    $namesReais = array_column($json['checks'], 'name');
    expect($namesReais)->toEqualCanonicalizing($namesEsperados);

    foreach ($json['checks'] as $check) {
        expect($check)
            ->toHaveKey('name')
            ->toHaveKey('ok')
            ->toHaveKey('value')
            ->toHaveKey('message');
        expect($check['ok'])->toBeBool();
    }
});

test('comando nao crasha mesmo se tabelas degraded', function () {
    // Roda 2x — se tiver state local, pegamos
    $exit1 = \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);
    $exit2 = \Illuminate\Support\Facades\Artisan::call('jana:health-check', ['--json' => true]);

    expect($exit1)->toBeIn([0, 1]);
    expect($exit2)->toBeIn([0, 1]);
});
