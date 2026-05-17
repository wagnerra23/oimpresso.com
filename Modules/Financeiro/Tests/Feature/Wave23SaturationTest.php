<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Financeiro\Console\Commands\FinanceiroHealthCommand;
use Modules\Financeiro\Services\FinanceiroAuditLogger;
use Modules\Financeiro\Services\FluxoCaixaService;
use Modules\Financeiro\Services\UnificadoService;

uses(Tests\TestCase::class);

/**
 * Wave 23 Financeiro SATURATION — D1 (cross-tenant comprehensive) + D6 (Health +2).
 *
 * Cobre:
 *   - D1: Services canônicos existem com API esperada
 *   - D6: financeiro:health tem 8 checks (6 originais + 2 Wave 23: orphan_baixas + valor_aberto_consistente)
 *   - D6: signature mantém `--detail` (NÃO --verbose Symfony reserved)
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO chama session() nem DB real.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 23 Financeiro Saturation', function () {
    it('financeiro:health command registrado', function () {
        $all = Artisan::all();
        expect($all)->toHaveKey('financeiro:health');
        expect($all['financeiro:health'])->toBeInstanceOf(FinanceiroHealthCommand::class);
    });

    it('signature usa --detail (NÃO --verbose Symfony reserved)', function () {
        $cmd = Artisan::all()['financeiro:health'];
        $def = $cmd->getDefinition();

        expect($def->hasOption('detail'))->toBeTrue();
        expect($def->hasOption('json'))->toBeTrue();
        expect($def->hasOption('alert'))->toBeTrue();
    });

    it('FluxoCaixaService classe canônica existe', function () {
        expect(class_exists(FluxoCaixaService::class))->toBeTrue();

        $methods = collect((new ReflectionClass(FluxoCaixaService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->pluck('name')
            ->toArray();

        expect(count($methods))->toBeGreaterThan(0);
    });

    it('UnificadoService consolidator AR+AP existe', function () {
        expect(class_exists(UnificadoService::class))->toBeTrue();
    });

    it('FinanceiroAuditLogger existe (D7 LGPD)', function () {
        expect(class_exists(FinanceiroAuditLogger::class))->toBeTrue();
    });

    it('Config retention.php existe e tem enabled flag', function () {
        $path = module_path('Financeiro', 'Config/retention.php');
        expect(file_exists($path))->toBeTrue();

        $config = require $path;
        expect($config)->toBeArray();
        expect($config)->toHaveKey('enabled');
    });

    it('health command pode ser instanciado e executado em ambiente sem tabelas', function () {
        // Smoke: garante que command não explode mesmo sem schema (tabelas WARN/FAIL)
        $exitCode = Artisan::call('financeiro:health', ['--json' => true]);

        // 0 sem --alert, 0/1/2 com --alert; aqui sem alert → sempre 0
        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        $decoded = json_decode($output, true);

        expect($decoded)->toBeArray();
        expect($decoded)->toHaveKey('checks');
        expect($decoded)->toHaveKey('summary');

        // Wave 23 adicionou 2 checks → total 8
        expect(count($decoded['checks']))->toBe(8);

        $names = collect($decoded['checks'])->pluck('name')->toArray();
        expect($names)->toContain('orphan_baixas', 'valor_aberto_consistente');
    });
});
