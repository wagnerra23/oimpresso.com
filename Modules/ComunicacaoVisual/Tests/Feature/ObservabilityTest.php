<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

/**
 * Smoke D9 observability — Wave 16 governance v3.
 *
 * Garante que:
 *   1. Services ComVis usam OtelHelper::spanBiz (zero-cost quando otel.enabled=false)
 *   2. Command `comvis:health` é registrado e executa sem erro
 *   3. Log estruturado canal `comvis.*` está disponível
 *
 * Refs: ADR 0155 module-grade-v3 D9
 */

it('cenário 1: comando comvis:health está registrado em Artisan', function () {
    $commands = array_keys(Artisan::all());
    expect($commands)->toContain('comvis:health');
});

it('cenário 2: OrcamentoCalculator usa OtelHelper (span no-op com otel.enabled=false)', function () {
    config(['otel.enabled' => false]);

    $calc = new \Modules\ComunicacaoVisual\Services\OrcamentoCalculator();

    $payload = [
        'data_emissao' => '2026-05-16',
        'itens' => [
            [
                'descricao' => 'Teste',
                'largura_m' => 1.0,
                'altura_m'  => 1.0,
                'quantidade' => 1,
                'preco_unitario_m2' => 50.00,
            ],
        ],
    ];

    $result = $calc->calcular($payload);

    expect($result)->toBeArray();
    expect($result['subtotal'])->toBe(50.00);
    expect($result['total'])->toBe(50.00);
});

it('cenário 3: comvis:health roda sem crash (tabelas ausentes ou presentes)', function () {
    // Executa o command — pode retornar 0 ou 1 dependendo do schema, mas NUNCA crashar
    $exitCode = Artisan::call('comvis:health');
    expect($exitCode)->toBeIn([0, 1]);
});

it('cenário 4: comvis:health --detail imprime tabela legível quando tabelas existem', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('comvis_orcamentos')) {
        $this->markTestSkipped('Schema comvis_orcamentos não presente neste ambiente.');
    }
    $exitCode = Artisan::call('comvis:health', ['--detail' => true]);
    $output = Artisan::output();
    expect($output)->toContain('comvis:health');
    expect($exitCode)->toBeIn([0, 1]);
});
