<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Console\Commands\NfeHealthCommand;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * Wave 16 D9 — Observabilidade NfeBrasil (OTel spans + log estruturado +
 * health command).
 *
 * Rubrica D9 (ADR 0155 module-grade-v3):
 *   1. OtelHelper::spanBiz wrap em ops críticas SEFAZ (emit/cancel/inutilizar/
 *      manifestar/status/distribuicao)
 *   2. Log estruturado biz/chave em retornos SEFAZ
 *   3. Health command + schedule
 *
 * Smoke test: zero custo (OTel default-off via `config('otel.enabled')`,
 * helper retorna no-op early; health command consulta DB local sem tocar SEFAZ).
 *
 * Multi-tenant Tier 0 (ADR 0093): biz=1 nunca biz=4 (ROTA LIVRE).
 *
 * @see ADR 0155 module-grade-v3 D9
 * @see app/Util/OtelHelper.php
 */

const NFE_OBS_BIZ_WAGNER = 1;

/**
 * Helper: pula se rodando em SQLite ou schema NfeBrasil ausente.
 * Aplicado SÓ a testes que tocam Eloquent/Artisan call — testes "puros"
 * reflection rodam sempre.
 */
function nfeObsSkipSeSqlite(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_certificados')) {
        test()->markTestSkipped('Schema NfeBrasil ausente — rode migrations primeiro');
    }
}

test('OtelHelper::spanBiz é no-op quando otel.enabled=false (zero cost)', function () {
    config(['otel.enabled' => false]);

    $resultado = OtelHelper::spanBiz('nfe.smoke', function () {
        return 'callback-executed';
    }, ['module' => 'NfeBrasil', 'chave_44' => str_repeat('1', 44)]);

    expect($resultado)->toBe('callback-executed');
});

test('OtelHelper::spanBiz propaga exception sem mascarar (callback throws → throws)', function () {
    config(['otel.enabled' => false]);

    expect(fn () => OtelHelper::spanBiz('nfe.smoke', function () {
        throw new RuntimeException('boom-fiscal');
    }, ['module' => 'NfeBrasil']))->toThrow(RuntimeException::class, 'boom-fiscal');
});

test('NfeService::consultarStatusSefaz é wrapped em OTel span', function () {
    // Reflection — confirma a função pública envolve OtelHelper::spanBiz('nfe.status_sefaz', ...)
    $reflection = new ReflectionMethod(NfeService::class, 'consultarStatusSefaz');
    $source = file_get_contents($reflection->getFileName());
    $start = $reflection->getStartLine() - 1;
    $end   = $reflection->getEndLine();
    $body  = implode("\n", array_slice(explode("\n", $source), $start, $end - $start));

    expect($body)->toContain("OtelHelper::spanBiz('nfe.status_sefaz'");
});

test('NfeService::emitir + NfeService::cancelar estão wrapped em OTel spans', function () {
    $source = file_get_contents((new ReflectionClass(NfeService::class))->getFileName());

    expect($source)
        ->toContain("OtelHelper::spanBiz('nfe.emitir'")
        ->toContain("OtelHelper::spanBiz('nfe.cancelar'")
        ->toContain("OtelHelper::spanBiz('nfe.status_sefaz'");
});

test('NfeInutilizacaoService::inutilizar wrap span nfe.inutilizar', function () {
    $source = file_get_contents((new ReflectionClass(NfeInutilizacaoService::class))->getFileName());
    expect($source)->toContain("OtelHelper::spanBiz('nfe.inutilizar'");
});

test('ManifestacaoService::aplicarEvento wrap span nfe.manifestar', function () {
    $source = file_get_contents((new ReflectionClass(ManifestacaoService::class))->getFileName());
    expect($source)->toContain("OtelHelper::spanBiz('nfe.manifestar'");
});

test('DistribuicaoDfeService::puxarLote wrap span nfe.distribuicao_dfe', function () {
    $source = file_get_contents((new ReflectionClass(DistribuicaoDfeService::class))->getFileName());
    expect($source)->toContain("OtelHelper::spanBiz('nfe.distribuicao_dfe'");
});

test('NfeService.processarRetorno usa log estruturado nfe.retorno_sefaz com chave/cstat', function () {
    $source = file_get_contents((new ReflectionClass(NfeService::class))->getFileName());

    // Verifica nova convenção log estruturado (D9.b) — 3 status (autorizada/denegada/rejeitada)
    expect($source)
        ->toContain("Log::info('nfe.retorno_sefaz'")
        ->toContain("Log::warning('nfe.retorno_sefaz'")
        ->toContain("'chave'")
        ->toContain("'cstat'");
});

test('nfe:health command está registrado no Artisan', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('nfe:health');
    expect($commands['nfe:health'])->toBeInstanceOf(NfeHealthCommand::class);
});

test('nfe:health executa sem erro contra DB local (smoke)', function () {
    nfeObsSkipSeSqlite();

    // Executa o command — não testa output específico (depende do banco), só
    // garante que não explode (no-throw smoke). Exit code 0 ou 1 ambos OK
    // (depende se há cert vencido no ambiente de teste).
    $exitCode = Artisan::call('nfe:health');

    expect($exitCode)->toBeIn([0, 1]);

    $output = Artisan::output();
    expect($output)->toContain('nfe:health');
});

test('nfe:health respeita --business-id filter sem erro', function () {
    nfeObsSkipSeSqlite();

    $exitCode = Artisan::call('nfe:health', [
        '--business-id' => NFE_OBS_BIZ_WAGNER,
    ]);

    expect($exitCode)->toBeIn([0, 1]);
});

test('nfe:health NÃO faz ping SEFAZ por default (custo zero diário)', function () {
    // Garante que o pattern --ping-sefaz é opcional, não default.
    // Se default tivesse ping, smoke acima já teria tentado tocar SEFAZ real
    // (ou explodido sem cert).
    $command = app(NfeHealthCommand::class);
    $definition = $command->getDefinition();

    expect($definition->hasOption('ping-sefaz'))->toBeTrue();
    expect($definition->getOption('ping-sefaz')->getDefault())->toBeFalsy();
});

test('nfe:health usa --detail (NÃO --verbose Symfony reserved)', function () {
    // .claude/rules/commands.md regra IRREVOGÁVEL: custom --verbose explode
    // (LogicException). Garantir --detail é opção custom presente.
    $command = app(NfeHealthCommand::class);
    $definition = $command->getDefinition();

    expect($definition->hasOption('detail'))->toBeTrue();
    expect($definition->hasOption('notify'))->toBeTrue();
});
