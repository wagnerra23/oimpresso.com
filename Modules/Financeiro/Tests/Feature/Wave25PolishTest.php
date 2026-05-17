<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Financeiro\Console\Commands\FinanceiroHealthCommand;
use Modules\Financeiro\Services\UnificadoService;

uses(Tests\TestCase::class);

/**
 * Wave 25 Financeiro POLISH — D9 (UnificadoService span) + Health 9+10.
 *
 * Cobre:
 *   - UnificadoService wrap em OtelHelper::spanBiz('financeiro.unificado.kpis')
 *   - financeiro:health total agora 10 checks (8 Wave 23 + 2 Wave 25)
 *   - Checks novos: contas_bancarias_ativas + caixa_movimento_freshness
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO toca session nem DB real.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 25 Financeiro Polish', function () {
    it('D9 — UnificadoService::kpis wrap em OtelHelper::spanBiz', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/UnificadoService.php');

        expect($source)->toContain('use App\Util\OtelHelper');
        expect($source)->toContain("OtelHelper::spanBiz('financeiro.unificado.kpis'");
        expect($source)->toContain('private function kpisInternal(');
    });

    it('D9 — UnificadoService classe existe e tem método kpis público', function () {
        expect(class_exists(UnificadoService::class))->toBeTrue();

        $reflection = new ReflectionClass(UnificadoService::class);
        expect($reflection->hasMethod('kpis'))->toBeTrue();
        expect($reflection->getMethod('kpis')->isPublic())->toBeTrue();
    });

    it('Health command total agora 10 checks (8 Wave 23 + 2 Wave 25)', function () {
        $exitCode = Artisan::call('financeiro:health', ['--json' => true]);
        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        $decoded = json_decode($output, true);

        expect($decoded)->toBeArray();
        expect(count($decoded['checks']))->toBe(10);

        $names = collect($decoded['checks'])->pluck('name')->toArray();
        expect($names)->toContain('contas_bancarias_ativas', 'caixa_movimento_freshness');
    });

    it('Health command Wave 25 checks aparecem em --detail sem crash', function () {
        $exitCode = Artisan::call('financeiro:health', ['--detail' => true]);
        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('contas_bancarias_ativas');
        expect($output)->toContain('caixa_movimento_freshness');
    });

    it('FinanceiroHealthCommand classe registrada', function () {
        expect(Artisan::all())->toHaveKey('financeiro:health');
        expect(Artisan::all()['financeiro:health'])->toBeInstanceOf(FinanceiroHealthCommand::class);
    });
});
