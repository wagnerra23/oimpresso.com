<?php

declare(strict_types=1);

use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\ConsultaOs\Repositories\MockConsultaOsRepository;
use Modules\ConsultaOs\Services\ConsultaOsMockService;

uses(Tests\TestCase::class);

/**
 * Wave 18 D4 — Repository pattern smoke + Service contract.
 *
 * Valida que:
 *   - Provider bind `ConsultaOsRepositoryInterface` → `MockConsultaOsRepository`.
 *   - Service `ConsultaOsMockService` resolve via container.
 *   - Service::buscar() retorna estrutura esperada (found/os ou found:false/reason).
 *   - Substituir bind = trocar fonte sem mexer no Controller (US-CONSULTA-001).
 *
 * Sem MySQL — testa Service em memoria via mock fake (sem DB).
 *
 * @see Modules\ConsultaOs\Services\ConsultaOsMockService
 * @see ADR 0155 module-grade v3 D4
 */

it('Provider faz bind ConsultaOsRepositoryInterface → MockConsultaOsRepository', function () {
    $instance = app(ConsultaOsRepositoryInterface::class);

    expect($instance)->toBeInstanceOf(MockConsultaOsRepository::class);
});

it('ConsultaOsMockService resolve via container com Repository injetado', function () {
    $service = app(ConsultaOsMockService::class);

    expect($service)->toBeInstanceOf(ConsultaOsMockService::class);
});

it('Service buscar() retorna found=true + payload pra numero conhecido (4821)', function () {
    $service = app(ConsultaOsMockService::class);
    $resultado = $service->buscar('4821');

    expect($resultado)->toBe([
        'found' => true,
        'os'    => app(ConsultaOsRepositoryInterface::class)->buscarPorNumero('4821'),
    ]);

    expect($resultado['os']['client'])->toBe('Acme Comercio Ltda');
    expect($resultado['os']['stage'])->toBe('aprovacao');
});

it('Service buscar() retorna found=false + reason=not_found pra numero inexistente', function () {
    $service = app(ConsultaOsMockService::class);
    $resultado = $service->buscar('99999');

    expect($resultado)->toBe([
        'found'  => false,
        'reason' => 'not_found',
    ]);
});

it('Service buscar() retorna found=false + reason=stage_mismatch pra filtro errado', function () {
    $service = app(ConsultaOsMockService::class);
    // 4817 esta em 'producao' — filtrar por 'entregue' = mismatch
    $resultado = $service->buscar('4817', 'entregue');

    expect($resultado)->toBe([
        'found'  => false,
        'reason' => 'stage_mismatch',
    ]);
});

it('Service buscar() com estagio=todos retorna OS qualquer estagio', function () {
    $service = app(ConsultaOsMockService::class);
    $resultado = $service->buscar('4815', 'todos');

    expect($resultado['found'])->toBeTrue();
    expect($resultado['os']['stage'])->toBe('entregue');
});

it('Repository contrato — buscarPorNumero retorna array OU null (nunca exception)', function () {
    $repo = app(ConsultaOsRepositoryInterface::class);

    expect($repo->buscarPorNumero('4821'))->toBeArray();
    expect($repo->buscarPorNumero('inexistente-xyz'))->toBeNull();
});

it('Repository pode ser swapped via app->bind() — Wave 18 D4 swappability', function () {
    // Demonstracao: substituir bind por fake e Service usa fake transparente.
    $fakeRepo = new class implements ConsultaOsRepositoryInterface
    {
        public function buscarPorNumero(string $numero): ?array
        {
            return $numero === 'FAKE-001'
                ? ['id' => 'FAKE-001', 'client' => 'Fake Co', 'stage' => 'producao', 'items' => []]
                : null;
        }
    };

    app()->bind(ConsultaOsRepositoryInterface::class, fn () => $fakeRepo);

    $service = app(ConsultaOsMockService::class);
    $resultado = $service->buscar('FAKE-001');

    expect($resultado['found'])->toBeTrue();
    expect($resultado['os']['client'])->toBe('Fake Co');
});
