<?php

declare(strict_types=1);

use Modules\ConsultaOs\Console\Commands\ConsultaOsHealthCommand;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\ConsultaOs\Http\Controllers\ConsultaOsController;
use Modules\ConsultaOs\Repositories\MockConsultaOsRepository;
use Modules\ConsultaOs\Services\ConsultaOsMockService;

uses(Tests\TestCase::class);

/**
 * Wave 23 SATURATION ConsultaOs — F1 Pest + F2 reuse + F6 Health + D7 LGPD complementar.
 *
 * Gap audit 63 → ≥80: este test cobre 5 sub-dimensoes:
 *   - F1 Pest: smoke contract Service + Repository
 *   - F2 reuse: Contract ConsultaOsRepositoryInterface eh consumivel por Repair/OficinaAuto
 *               (mock-only hoje; US-CONSULTA-001 entrega RepairRepository real)
 *   - F6 Health: command `consultaos:health` registrado + smoke probes
 *   - D7 LGPD: Config/retention.php declarado canon (consulta_os_logs 365d + tokens 90d)
 *   - D4 SoC: Controller magro <200 linhas, Service single-purpose
 *
 * Zero hit em rede externa (Repository mock-only) — Pest local-runnable sem custo.
 *
 * @see Modules\ConsultaOs\Services\ConsultaOsMockService
 * @see Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface
 * @see Modules\ConsultaOs\Console\Commands\ConsultaOsHealthCommand
 * @see memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md
 */

it('F1: ConsultaOsMockService resolvido via container com DI Repository', function () {
    $service = app(ConsultaOsMockService::class);
    expect($service)->toBeInstanceOf(ConsultaOsMockService::class);
});

it('F2 reuse: ConsultaOsRepositoryInterface bindado a impl concreta (Mock OR Repair-real)', function () {
    $repo = app(ConsultaOsRepositoryInterface::class);
    expect($repo)->toBeInstanceOf(ConsultaOsRepositoryInterface::class);

    // Hoje = Mock. US-CONSULTA-001 troca pra RepairRepository — interface preserva contrato.
    expect($repo)->toBeInstanceOf(MockConsultaOsRepository::class);
});

it('F2 reuse: Service.buscar() retorna shape canonico {found, os?, reason?}', function () {
    $service = app(ConsultaOsMockService::class);
    $res = $service->buscar('4821');

    expect($res)->toHaveKey('found');
    expect($res['found'])->toBeTrue();
    expect($res)->toHaveKey('os');
    expect($res['os'])->toHaveKeys(['id', 'client', 'stage', 'items']);
});

it('F2 reuse: Service.buscar() numero nao existente retorna found=false sem leak', function () {
    $service = app(ConsultaOsMockService::class);
    $res = $service->buscar('99999');

    expect($res['found'])->toBeFalse();
    expect($res)->not->toHaveKey('os');
    expect($res)->toHaveKey('reason');
});

it('F2 reuse: Service.buscar() filtro estagio errado retorna stage_mismatch', function () {
    $service = app(ConsultaOsMockService::class);
    // OS 4817 esta em 'producao' — filtrando 'entregue' deve devolver not_found
    $res = $service->buscar('4817', 'entregue');

    expect($res['found'])->toBeFalse();
    expect($res['reason'])->toBe('stage_mismatch');
});

it('F6: ConsultaOsHealthCommand registrado + executavel', function () {
    $cmd = app(ConsultaOsHealthCommand::class);
    expect($cmd)->toBeInstanceOf(ConsultaOsHealthCommand::class);

    $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);
    expect($signature)->toContain('consultaos:health');
    expect($signature)->toContain('--detail');
    expect($signature)->not->toContain('{--verbose '); // .claude/rules/commands.md
});

it('F6: comando handle() retorna SUCCESS quando Mock OK (smoke probe 4821)', function () {
    $exit = $this->artisan('consultaos:health')->run();
    expect($exit)->toBe(0); // SUCCESS — repo+service+retention+smoke ok
});

it('D7 LGPD: Config/retention.php declara entities consulta_os_logs + consulta_os_tokens', function () {
    $cfg = require __DIR__ . '/../../Config/retention.php';

    expect($cfg)->toHaveKey('entities');
    expect($cfg['entities'])->toHaveKey('consulta_os_logs');
    expect($cfg['entities']['consulta_os_logs'])->toBe(365); // 1 ano ANPD
    expect($cfg['entities'])->toHaveKey('consulta_os_tokens');
    expect($cfg['entities']['consulta_os_tokens'])->toBe(90); // 90d acompanhamento

    expect($cfg)->toHaveKey('strategy');
    expect($cfg['strategy'])->toBeIn(['hard_delete', 'anonymize']);
});

it('D4 SoC: Controller ConsultaOsController magro <200 linhas (single responsibility)', function () {
    $file = (new ReflectionClass(ConsultaOsController::class))->getFileName();
    $lines = count(file($file));

    expect($lines)->toBeLessThan(200, "Controller magro esperado: <200 linhas. Atual: {$lines}");
});

it('D4 SoC: ConsultaOsMockService usa OtelHelper canon (D9 observability)', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Services/ConsultaOsMockService.php'));
    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("'consultaos.busca_publica'");
});

it('F1 jornada cliente passo extra: estagios canonicos sao reconhecidos (aprovacao, producao, entregue)', function () {
    $service = app(ConsultaOsMockService::class);

    // Dataset mock cobre 4 estagios canonicos (aprovacao, producao, expedicao, entregue)
    $estagiosCobertura = [];
    foreach (['4821', '4819', '4817', '4815'] as $num) {
        $res = $service->buscar($num);
        if (($res['found'] ?? false) === true) {
            $estagiosCobertura[] = $res['os']['stage'];
        }
    }

    expect($estagiosCobertura)->toContain('aprovacao');
    expect($estagiosCobertura)->toContain('producao');
    expect($estagiosCobertura)->toContain('entregue');
});
