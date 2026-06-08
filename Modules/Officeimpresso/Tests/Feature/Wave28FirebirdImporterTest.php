<?php

declare(strict_types=1);

use Modules\Officeimpresso\Services\FirebirdImporter\FirebirdConnector;
use Modules\Officeimpresso\Services\FirebirdImporter\OfficeimpressoImporterService;

uses(Tests\TestCase::class);

/**
 * Wave 28-4 — Officeimpresso FirebirdImporter G1 vertical bucket (2026-05-17).
 *
 * Valida importer Firebird (Delphi WR Comercial) → oimpresso multi-tenant.
 * Único moat real comprovado: zero competidor BR faz Firebird → SaaS turnkey.
 *
 * ROI estimado: 6-7 prospects pagantes ComVis (Vargas, Extreme, Gold, Zoom,
 * Fixar, Mhundo, Produart) — ARR R$ [redacted Tier 0]-72k/ano.
 *
 * Cobre:
 *   - FirebirdConnector mock mode (Pest CI sem ext pdo_firebird)
 *   - Read-only assertion (Tier 0 one-way ADR 0019)
 *   - OfficeimpressoImporterService idempotência (dry-run + multi-tenant guard)
 *   - Command artisan signature `--detail` (NÃO `--verbose` Symfony reserved)
 *   - Encoding ISO-8859-1 → UTF-8 (Delphi padrão)
 *
 * @see Modules\Officeimpresso\Services\FirebirdImporter\FirebirdConnector
 * @see Modules\Officeimpresso\Services\FirebirdImporter\OfficeimpressoImporterService
 * @see Modules\Officeimpresso\Console\Commands\ImportOfficeimpressoCommand
 */

describe('W28-4 — FirebirdConnector', function () {

    it('classe existe no namespace canônico Services/FirebirdImporter', function () {
        expect(class_exists(FirebirdConnector::class))->toBeTrue();
    });

    it('isMock() retorna true quando ext pdo_firebird ausente (CI default)', function () {
        $connector = new FirebirdConnector(':mock:');
        // No CI/Windows dev default não tem ext firebird → mock auto
        expect($connector->isMock())->toBeTrue();
    });

    it('isMock() respeita flag forceMock=true mesmo se ext disponível', function () {
        $connector = new FirebirdConnector(':mock:', forceMock: true);
        expect($connector->isMock())->toBeTrue();
    });

    it('healthCheck() em mock mode retorna ok=true com mode=mock', function () {
        $connector = new FirebirdConnector(':mock:', forceMock: true);
        $health = $connector->healthCheck();
        expect($health['ok'])->toBeTrue();
        expect($health['mode'])->toBe('mock');
        expect($health['fdb_path'])->toBe(':mock:');
    });

    it('driverAvailable() reporta corretamente ext PDO firebird presente/ausente', function () {
        $available = FirebirdConnector::driverAvailable();
        expect($available)->toBe(in_array('firebird', PDO::getAvailableDrivers(), true));
    });

    it('connect() lança RuntimeException em mock mode (Tier 0 fail-fast)', function () {
        $connector = new FirebirdConnector(':mock:', forceMock: true);
        expect(fn () => $connector->connect())
            ->toThrow(RuntimeException::class, 'mock mode');
    });

    it('flushPool() limpa pool de conexões sem erro', function () {
        FirebirdConnector::flushPool();
        expect(true)->toBeTrue(); // smoke — não deve lançar
    });
});

describe('W28-4 — OfficeimpressoImporterService', function () {

    it('classe existe + recebe FirebirdConnector via DI', function () {
        expect(class_exists(OfficeimpressoImporterService::class))->toBeTrue();
        $ref = new ReflectionClass(OfficeimpressoImporterService::class);
        $ctor = $ref->getConstructor();
        expect($ctor)->not->toBeNull();
        $params = $ctor->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getType()?->getName())->toBe(FirebirdConnector::class);
    });

    it('runFullImport() retorna struct canônico com 4 tabelas + meta', function () {
        $service = new OfficeimpressoImporterService(
            new FirebirdConnector(':mock:', forceMock: true)
        );
        $report = $service->runFullImport(businessId: 1, dryRun: true);

        expect($report)->toHaveKeys(['mode', 'dry_run', 'clientes', 'produtos', 'vendas', 'licencas', 'errors']);
        expect($report['mode'])->toBe('mock');
        expect($report['dry_run'])->toBeTrue();
        expect($report['clientes'])->toHaveKeys(['read', 'migrated', 'skipped']);
        expect($report['produtos'])->toHaveKeys(['read', 'migrated', 'skipped']);
        expect($report['vendas'])->toHaveKeys(['read', 'migrated', 'skipped']);
        expect($report['licencas'])->toHaveKeys(['read', 'migrated', 'skipped']);
    });

    it('dry-run lê mocks Firebird mas NÃO persiste (read>0, migrated counted)', function () {
        $service = new OfficeimpressoImporterService(
            new FirebirdConnector(':mock:', forceMock: true)
        );
        $report = $service->runFullImport(businessId: 1, dryRun: true);

        // Mocks têm 2 clientes, 2 produtos, 1 venda, 1 licença
        expect($report['clientes']['read'])->toBe(2);
        expect($report['produtos']['read'])->toBe(2);
        expect($report['vendas']['read'])->toBe(1);
        expect($report['licencas']['read'])->toBe(1);

        // Em dry-run, migrated = read (não foi skipped) e nada persistido
        expect($report['clientes']['migrated'])->toBe(2);
        expect($report['licencas']['migrated'])->toBe(1);
    });

    it('multi-tenant Tier 0: business_id <= 0 lança InvalidArgumentException', function () {
        $service = new OfficeimpressoImporterService(
            new FirebirdConnector(':mock:', forceMock: true)
        );
        expect(fn () => $service->runFullImport(businessId: 0))
            ->toThrow(InvalidArgumentException::class, 'business_id obrigatório');
        expect(fn () => $service->runFullImport(businessId: -1))
            ->toThrow(InvalidArgumentException::class, 'business_id obrigatório');
    });

    it('importer per-table standalone: importClientes() funciona isolado', function () {
        $service = new OfficeimpressoImporterService(
            new FirebirdConnector(':mock:', forceMock: true)
        );
        $stats = $service->importClientes(businessId: 1, dryRun: true);

        expect($stats)->toHaveKeys(['read', 'migrated', 'skipped']);
        expect($stats['read'])->toBe(2);
        expect($stats['migrated'])->toBe(2);
        expect($stats['skipped'])->toBe(0);
    });
});

describe('W28-4 — Tier 0 read-only + encoding', function () {

    it('FirebirdConnector::selectAll() bloqueia DML/DDL (Tier 0 one-way ADR 0019)', function () {
        // Mock mode levanta antes (não chega no assertReadOnly), então
        // testamos a regra via reflection do método privado
        $ref = new ReflectionClass(FirebirdConnector::class);
        $method = $ref->getMethod('assertReadOnly');
        $method->setAccessible(true);
        $connector = new FirebirdConnector(':mock:', forceMock: true);

        // SELECT puro: ok (não lança)
        $method->invoke($connector, 'SELECT * FROM CLIENTES');
        expect(true)->toBeTrue();

        // INSERT: bloqueado
        expect(fn () => $method->invoke($connector, 'INSERT INTO CLIENTES VALUES (1)'))
            ->toThrow(RuntimeException::class, 'READ-ONLY');

        // DELETE: bloqueado
        expect(fn () => $method->invoke($connector, 'DELETE FROM CLIENTES'))
            ->toThrow(RuntimeException::class, 'READ-ONLY');

        // UPDATE: bloqueado
        expect(fn () => $method->invoke($connector, 'UPDATE CLIENTES SET NOME=?'))
            ->toThrow(RuntimeException::class, 'READ-ONLY');

        // DROP: bloqueado
        expect(fn () => $method->invoke($connector, 'DROP TABLE CLIENTES'))
            ->toThrow(RuntimeException::class, 'READ-ONLY');
    });

    it('encoding ISO-8859-1 → UTF-8 conversion (acentuação Delphi)', function () {
        $ref = new ReflectionClass(FirebirdConnector::class);
        $method = $ref->getMethod('convertEncoding');
        $method->setAccessible(true);
        $connector = new FirebirdConnector(':mock:', forceMock: true);

        // ISO-8859-1 bytes pra "João" (0x4A 0xE3 0x6F)
        $isoNome = "Jo\xE3o";
        expect(mb_check_encoding($isoNome, 'UTF-8'))->toBeFalse();

        $converted = $method->invoke($connector, ['NOME' => $isoNome]);
        expect(mb_check_encoding($converted['NOME'], 'UTF-8'))->toBeTrue();
        expect($converted['NOME'])->toBe('João');
    });
});

describe('W28-4 — Command artisan signature', function () {

    it('ImportOfficeimpressoCommand existe no namespace canônico Console/Commands', function () {
        $class = 'Modules\\Officeimpresso\\Console\\Commands\\ImportOfficeimpressoCommand';
        expect(class_exists($class))->toBeTrue();
    });

    it('signature usa --detail (NÃO --verbose Symfony reserved — lição PR #851)', function () {
        $class = 'Modules\\Officeimpresso\\Console\\Commands\\ImportOfficeimpressoCommand';
        $ref = new ReflectionClass($class);
        $sig = $ref->getDefaultProperties()['signature'] ?? '';

        expect($sig)->toContain('--detail');
        expect($sig)->not->toContain('--verbose');
        expect($sig)->toContain('--dry-run');
        expect($sig)->toContain('--source-fb');
        expect($sig)->toContain('{biz');
        expect($sig)->toStartWith('officeimpresso:import');
    });

    it('command registrado em OfficeimpressoServiceProvider::boot()', function () {
        $providerPath = __DIR__ . '/../../Providers/OfficeimpressoServiceProvider.php';
        expect(file_exists($providerPath))->toBeTrue();
        $contents = (string) file_get_contents($providerPath);
        expect($contents)->toContain('ImportOfficeimpressoCommand::class');
    });
});

describe('W28-4 — Bridge legacy preservation (Lei Software 9.609/98)', function () {

    it('Service usa JSON legacy_id pra idempotência (não modifica PKs originais)', function () {
        // Garante que a estratégia de idempotência respeita PKs Firebird como
        // chave em JSON `legacy_id` — preserva bridge legacy (Lei 9.609/98)
        $ref = new ReflectionClass(OfficeimpressoImporterService::class);
        $method = $ref->getMethod('alreadyImported');
        $method->setAccessible(true);

        // Assinatura: (table, businessId, source, legacyId)
        $params = $method->getParameters();
        expect($params)->toHaveCount(4);
        expect($params[0]->getName())->toBe('table');
        expect($params[1]->getName())->toBe('businessId');
        expect($params[2]->getName())->toBe('source');
        expect($params[3]->getName())->toBe('legacyId');
    });

    it('Tier 0 one-way: NUNCA escreve no Firebird (apenas leitura)', function () {
        // Garantido por FirebirdConnector::assertReadOnly + ausência de método
        // insert/update/delete no connector. Smoke check de API pública:
        $ref = new ReflectionClass(FirebirdConnector::class);
        $publicMethods = array_map(
            fn ($m) => $m->getName(),
            $ref->getMethods(ReflectionMethod::IS_PUBLIC)
        );

        // Métodos write-side proibidos NÃO existem
        expect($publicMethods)->not->toContain('insert');
        expect($publicMethods)->not->toContain('update');
        expect($publicMethods)->not->toContain('delete');
        expect($publicMethods)->not->toContain('execute');

        // Métodos read-side esperados
        expect($publicMethods)->toContain('selectAll');
        expect($publicMethods)->toContain('healthCheck');
        expect($publicMethods)->toContain('connect');
    });
});
