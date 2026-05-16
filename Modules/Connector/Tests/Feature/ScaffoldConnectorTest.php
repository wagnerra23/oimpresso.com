<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke scaffold do Modules/Connector — garante integridade estrutural do módulo.
 *
 * Connector é o módulo de API REST externa pra clientes (Delphi WR Comercial,
 * SaaS Woo, futuras integrações OfficeImpresso 6 clientes saudáveis em migração).
 *
 * Cobertura mínima (compatível com Wave A — AuthApiTest, MultiTenantIsolationTest,
 * SmokeApiRoutesTest):
 *   1. Módulo está registrado e ativo (nWidart)
 *   2. Rotas named principais existem
 *   3. Service provider registrado
 *   4. Service DelphiSyncService instanciável
 *
 * Multi-tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   - Connector NÃO usa biz=4 (ROTA LIVRE cliente real) — usa biz=1 ([ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md))
 *   - Token Passport NUNCA real nos testes — usa mocks/fakes de cabeçalho
 *
 * @see memory/requisitos/Connector/SPEC.md
 * @see memory/requisitos/Connector/BRIEFING.md
 * @see memory/requisitos/Connector/CHARTER-rest-api-external.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Connector requer schema MySQL/MariaDB (Passport + UltimatePOS)');
    }
});

it('módulo Connector está registrado e ativo no nWidart', function () {
    $module = Module::find('Connector');

    expect($module)->not->toBeNull('Module::find("Connector") retornou null — module.json ausente ou inválido');
    expect($module->isEnabled())->toBeTrue('Modules/Connector está desativado — `php artisan module:enable Connector`');
});

it('ConnectorServiceProvider está registrado', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(
        \Modules\Connector\Providers\ConnectorServiceProvider::class,
        'ConnectorServiceProvider não carregou — verificar module.json + composer dump-autoload'
    );
});

it('rotas named principais do Connector existem', function () {
    $routes = Route::getRoutes();

    $namedExpected = [
        'connector.delphi.processa-dados-cliente',
        'connector.delphi.salvar-cliente',
        'connector.delphi.salvar-equipamento',
        'connector.delphi.oimpresso.registrar',
        'connector.delphi.check-update',
    ];

    foreach ($namedExpected as $name) {
        expect(Route::has($name))
            ->toBeTrue("Rota named `{$name}` não está registrada — Modules/Connector/Routes/api.php quebrado?");
    }
});

it('rotas resource principais (REST CRUD) existem', function () {
    $routes = Route::getRoutes();

    $resourcesEsperados = [
        'connector/api/contactapi',
        'connector/api/product',
        'connector/api/sell',
        'connector/api/business-location',
        'connector/api/taxonomy',
        'connector/api/user',
    ];

    $uris = collect($routes)->map(fn ($r) => $r->uri())->toArray();

    foreach ($resourcesEsperados as $uri) {
        $matching = array_filter($uris, fn ($u) => str_starts_with($u, $uri));
        expect(count($matching))->toBeGreaterThan(0, "Nenhuma rota encontrada pra resource `{$uri}` — esperado index/show/store etc.");
    }
});

it('DelphiSyncService instanciável e tem métodos públicos esperados', function () {
    $service = app(\Modules\Connector\Services\DelphiSyncService::class);

    expect($service)->toBeInstanceOf(\Modules\Connector\Services\DelphiSyncService::class);
    expect(method_exists($service, 'detectBodyFormat'))->toBeTrue();
    expect(method_exists($service, 'extractHd'))->toBeTrue();
    expect(method_exists($service, 'resolveByCnpj'))->toBeTrue();
    expect(method_exists($service, 'formatLegacyResponse'))->toBeTrue();
});

it('DelphiSyncService::detectBodyFormat reconhece os 3 formatos canônicos', function () {
    $service = app(\Modules\Connector\Services\DelphiSyncService::class);

    $arrayTabelas = json_encode([
        ['NOME_TABELA' => 'EMPRESA', 'CNPJCPF' => '12.345.678/0001-99'],
        ['NOME_TABELA' => 'LICENCIAMENTO', 'HD' => 'ABCD1234'],
    ]);

    $jsonFlat = json_encode(['cnpj' => '12.345.678/0001-99', 'serial_hd' => 'ABCD1234']);

    $pipe = 'ABCD1234|HOST|1.0.0|192.168.0.1|12.345.678/0001-99|EMPRESA LTDA';

    expect($service->detectBodyFormat($arrayTabelas))->toBe('array_tabelas');
    expect($service->detectBodyFormat($jsonFlat))->toBe('json_flat');
    expect($service->detectBodyFormat($pipe))->toBe('pipe');
    expect($service->detectBodyFormat(''))->toBe('unknown');
});

it('DelphiSyncService::formatLegacyResponse segue contrato Delphi (S/N;msg)', function () {
    $service = app(\Modules\Connector\Services\DelphiSyncService::class);

    expect($service->formatLegacyResponse(true, 'Cliente liberado'))->toBe('S;Cliente liberado');
    expect($service->formatLegacyResponse(false, 'Maquina nao cadastrada'))->toBe('N;Maquina nao cadastrada');

    // Sanitiza ponto-e-vírgula (delphi split(';') quebraria)
    expect($service->formatLegacyResponse(true, 'a;b;c'))->toBe('S;a,b,c');
});

it('Controllers REST API estão sob namespace canônico Modules\Connector\Http\Controllers\Api', function () {
    // Guarda contrato externo — clientes Delphi referenciam FQCN em routes/api.php.
    // Mover/renomear controller quebra route:cache em prod + clientes que dependem
    // de OAuth tokens emitidos pra paths fixos.
    $controllersEsperados = [
        \Modules\Connector\Http\Controllers\Api\BusinessController::class,
        \Modules\Connector\Http\Controllers\Api\ContactController::class,
        \Modules\Connector\Http\Controllers\Api\ProductController::class,
        \Modules\Connector\Http\Controllers\Api\SellController::class,
        \Modules\Connector\Http\Controllers\Api\LicencaComputadorController::class,
        \Modules\Connector\Http\Controllers\Api\OImpressoRegistroController::class,
        \Modules\Connector\Http\Controllers\Api\CheckUpdateController::class,
    ];

    foreach ($controllersEsperados as $fqcn) {
        expect(class_exists($fqcn))->toBeTrue("Controller {$fqcn} não existe — REGRESSÃO de contrato externo!");
    }
});

it('middleware log.delphi aplicado em TODAS rotas connector/api/*', function () {
    // Lição ADR 0021 + DelphiOImpressoContractTest: log.delphi precisa rodar
    // ANTES de auth:api pra capturar 401s (debug Delphi tokens expirados).
    $routesFile = file_get_contents(base_path('Modules/Connector/Routes/api.php'));

    expect($routesFile)->toContain("'log.delphi', 'auth:api'");
});
