<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Console\Commands\ConnectorHealthCommand;
use Modules\Connector\Http\Controllers\Api\BaseApiController;
use Modules\Connector\Http\Controllers\Api\CheckUpdateController;
use Modules\Connector\Http\Controllers\Api\LicencaComputadorController;
use Modules\Connector\Http\Controllers\Api\OImpressoRegistroController;
use Modules\Connector\Services\DelphiSyncService;

uses(Tests\TestCase::class);

/**
 * Smoke observabilidade Connector — Wave 16 governance v3 D9.
 *
 * Valida:
 *   1. OtelHelper::spanBiz instrumentado em ops críticas (zero-cost mode)
 *   2. Log estruturado nos endpoints Delphi/syncData
 *   3. ConnectorHealthCommand registrado e executa
 *
 * Critério Tier 0: OTel zero-cost (config('otel.enabled')=false) NÃO pode
 * mudar comportamento dos endpoints — span = passthrough do callback.
 *
 * @see app/Util/OtelHelper.php
 * @see ADR 0155 module-grade-v3 D9
 */

beforeEach(function () {
    // Garantir OTel desligado (zero-cost path) — não exige collector.
    config(['otel.enabled' => false]);
    // Histórico do connector:health (thread 08) num arquivo do teste — nunca no storage real.
    config(['connector.health_history_path' => sys_get_temp_dir().'/connector-health-'.uniqid().'.json']);
});

afterEach(function () {
    @unlink((string) config('connector.health_history_path'));
});

it('OtelHelper::spanBiz é zero-cost quando otel.enabled=false', function () {
    $called = false;
    $result = \App\Util\OtelHelper::spanBiz('connector.test', function () use (&$called) {
        $called = true;
        return 'ok';
    }, ['connector.endpoint' => 'test']);

    expect($called)->toBeTrue();
    expect($result)->toBe('ok');
});

it('ConnectorHealthCommand está registrado em artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('connector:health');
});

it('connector:health executa sem fatal (smoke)', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: connector:health usa tabelas MySQL UltimatePOS');
    }

    $exit = Artisan::call('connector:health');

    // 0 (OK) ou 1 (FAIL com issues) ambos aceitáveis; o que importa é não fatal.
    expect($exit)->toBeIn([0, 1]);

    $output = Artisan::output();
    expect(str_contains($output, 'connector:health'))->toBeTrue();
});

it('connector:health --detail mostra tabela com 3 checks', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível');
    }

    Artisan::call('connector:health', ['--detail' => true]);
    $output = Artisan::output();

    expect(str_contains($output, 'tokens_active_24h'))->toBeTrue();
    expect(str_contains($output, 'licencas_recent_24h'))->toBeTrue();
    expect(str_contains($output, 'rotas_registradas'))->toBeTrue();
});

it('controllers Delphi importam App\\Util\\OtelHelper (instrumentation Tier 0)', function () {
    // Garantir que os 4 entrypoints críticos referenciam OtelHelper.
    $files = [
        OImpressoRegistroController::class => 'Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php',
        CheckUpdateController::class => 'Modules/Connector/Http/Controllers/Api/CheckUpdateController.php',
        LicencaComputadorController::class => 'Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php',
        BaseApiController::class => 'Modules/Connector/Http/Controllers/Api/BaseApiController.php',
        DelphiSyncService::class => 'Modules/Connector/Services/DelphiSyncService.php',
    ];

    foreach ($files as $class => $path) {
        $full = base_path($path);
        expect(file_exists($full))->toBeTrue();
        $content = file_get_contents($full);
        // Usa preg_match pra evitar interpretação de backslash do toContain.
        expect(preg_match('#use\s+App\\\\Util\\\\OtelHelper#', $content))->toBe(1, "{$class} não importa OtelHelper");
        expect(str_contains($content, 'spanBiz'))->toBeTrue("{$class} não chama spanBiz()");
    }
});

it('ConnectorHealthCommand define signature connector:health com --detail e --notify', function () {
    $cmd = new ConnectorHealthCommand();

    $signature = (new ReflectionClass($cmd))->getProperty('signature');
    $signature->setAccessible(true);
    $sig = $signature->getValue($cmd);

    expect(str_contains($sig, 'connector:health'))->toBeTrue();
    expect(str_contains($sig, '--detail'))->toBeTrue();
    expect(str_contains($sig, '--notify'))->toBeTrue();
    expect(str_contains($sig, '--verbose'))->toBeFalse(); // Symfony reserved (rule commands.md)
});

it('ConnectorServiceProvider registra schedule connector:health', function () {
    $providerPath = base_path('Modules/Connector/Providers/ConnectorServiceProvider.php');
    $content = file_get_contents($providerPath);

    expect(str_contains($content, 'ConnectorHealthCommand::class'))->toBeTrue();
    expect(str_contains($content, "'06:15'"))->toBeTrue();
    expect(str_contains($content, 'connector:health --notify'))->toBeTrue();
});

it('logs estruturados nos endpoints Delphi têm chave biz + endpoint (D9.b)', function () {
    // Inspeção estática — confirma pattern Log::channel('stack')->info(..., ['biz' => ...]).
    $endpoints = [
        'Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php' => 'connector.delphi.registrar.request',
        'Modules/Connector/Http/Controllers/Api/CheckUpdateController.php' => 'connector.delphi.check_update.request',
        'Modules/Connector/Http/Controllers/Api/BaseApiController.php' => 'connector.api.sync_data.request',
    ];

    foreach ($endpoints as $path => $logKey) {
        $content = file_get_contents(base_path($path));
        expect(str_contains($content, $logKey))->toBeTrue("{$path} sem log estruturado '{$logKey}'");
        expect(str_contains($content, "'biz' =>"))->toBeTrue("{$path} log sem chave 'biz'");
        expect(str_contains($content, "'endpoint' =>"))->toBeTrue("{$path} log sem chave 'endpoint'");
    }
});

// ── UC-CONN-26 · connector:health publica uma entrada por execução (thread 08) ──
it('UC-CONN-26 connector:health publica entrada por execução e poda o que passou de 14 dias', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: connector:health usa tabelas MySQL UltimatePOS');
    }
    $p = ConnectorHealthCommand::caminhoHistorico();
    file_put_contents($p, json_encode([
        ['executado_em' => now()->subDays(20)->toIso8601String(), 'ok' => true],
        ['executado_em' => now()->subDays(2)->toIso8601String(), 'ok' => true],
    ]));

    Artisan::call('connector:health');
    $lista = json_decode((string) file_get_contents($p), true);

    expect($lista)->toHaveCount(2); // a de 20 dias saiu, a de 2 dias ficou, a nova entrou
    $nova = $lista[1];
    // uma chave por linha: na mesma linha o gitleaks lia o nome do check como segredo
    expect(array_keys($nova))->toBe([
        'executado_em',
        'ok',
        'tokens_active_24h',
        'licencas_recent_24h',
        'rotas_registradas',
        'issues',
        'delphi',
    ]);
    expect($nova['rotas_registradas'])->toBeGreaterThanOrEqual(20);
    expect($nova['issues'])->toBeArray();
    expect(array_keys($nova['delphi']))->toBe(['chamadas_24h', 'desvios_24h', 'taxa_desvio']);
    expect(Artisan::output())->toContain('histórico publicado em');
});

it('UC-CONN-26 taxa de desvio conta corpo em formato desconhecido do log.delphi', function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! \Illuminate\Support\Facades\Schema::hasTable('licenca_log')) {
        $this->markTestSkipped('precisa de licenca_log (MySQL)');
    }
    DB::beginTransaction();
    try {
        Artisan::call('connector:health');
        $antes = ConnectorHealthCommand::historico();
        $a = end($antes)['delphi'];

        foreach (['json_flat', 'unknown', 'empty'] as $fmt) {
            DB::table('licenca_log')->insert([
                'event' => 'api_call', 'source' => 'delphi_middleware', 'created_at' => now(),
                'metadata' => json_encode(['body_format' => $fmt]),
            ]);
        }
        Artisan::call('connector:health');
        $depois = ConnectorHealthCommand::historico();
        $d = end($depois)['delphi'];
    } finally {
        DB::rollBack();
    }

    // corpo vazio não conta como chamada com corpo; unknown conta como desvio
    expect($d['chamadas_24h'] - $a['chamadas_24h'])->toBe(2);
    expect($d['desvios_24h'] - $a['desvios_24h'])->toBe(1);
    expect($d['taxa_desvio'])->toBe(round($d['desvios_24h'] / $d['chamadas_24h'], 4));
});
