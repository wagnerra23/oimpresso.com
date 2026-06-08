<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Services\AfdParserService;
use Modules\Ponto\Services\BancoHorasService;
use Modules\Ponto\Services\IntercorrenciaService;
use Modules\Ponto\Services\MarcacaoService;

/**
 * D9 Wave 16 — Observability smoke (governance v3 — sobe D9 4/7 -> >=6/7).
 *
 * Valida 3 capacidades:
 *   1) Services criticos do Ponto carregam o uso `App\Util\OtelHelper` (instrumentacao
 *      OTel D9.a — span_biz em hot-paths). NAO assertamos export OTel (driver
 *      default no-op em test); validamos source-level que wrap existe.
 *   2) Comando `ponto:health` registrado e executa sem fatal (5 checks SQL).
 *   3) Health command com --json produz JSON valido + 5 checks com status.
 *
 * NUNCA biz=4 (ROTA LIVRE prod cliente Larissa — ADR 0101). Testes usam biz=1.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Portaria MTP 671/2021 Art. 85 (append-only marcacoes)
 */

uses(Tests\TestCase::class);

// Guard MySQL apenas pros testes que tocam Artisan/DB schema. Source-level
// assertions abaixo (reflexao + file_get_contents) rodam tambem em SQLite.
const OBS_BIZ_WAGNER = 1;

function obsRequireMysql(\PHPUnit\Framework\TestCase $tc): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        $tc->markTestSkipped('SQLite-incompativel: ponto:health acessa information_schema MySQL.');
    }
}

// ------------------------------------------------------------------
// Cenario 1: instrumentacao OTel — source-level em 4 services criticos
// ------------------------------------------------------------------

it('MarcacaoService instrumenta OtelHelper em registrar() + anular() (D9.a Wave 16)', function () {
    $source = file_get_contents((new ReflectionClass(MarcacaoService::class))->getFileName());

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("OtelHelper::span('ponto.marcacao.registrar'");
    expect($source)->toContain("OtelHelper::span('ponto.marcacao.anular'");
});

it('ApuracaoService instrumenta OtelHelper em apurar() (D9.a Wave 16)', function () {
    $source = file_get_contents((new ReflectionClass(\Modules\Ponto\Services\ApuracaoService::class))->getFileName());

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("OtelHelper::span('ponto.apuracao.apurar'");
});

it('BancoHorasService instrumenta OtelHelper em movimentar() + expirarSaldosAntigos() (D9.a Wave 16)', function () {
    $source = file_get_contents((new ReflectionClass(BancoHorasService::class))->getFileName());

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("OtelHelper::span('ponto.banco_horas.movimentar'");
    expect($source)->toContain("OtelHelper::span('ponto.banco_horas.expirar_saldos'");
});

it('IntercorrenciaService instrumenta OtelHelper em criar() + aprovar() (D9.a Wave 16)', function () {
    $source = file_get_contents((new ReflectionClass(IntercorrenciaService::class))->getFileName());

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("OtelHelper::span('ponto.intercorrencia.criar'");
    expect($source)->toContain("OtelHelper::span('ponto.intercorrencia.aprovar'");
});

it('AfdParserService instrumenta OtelHelper em processar() + emite log estruturado (D9.a/D9.b Wave 16)', function () {
    $source = file_get_contents((new ReflectionClass(AfdParserService::class))->getFileName());

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain('use Illuminate\Support\Facades\Log;');
    expect($source)->toContain("OtelHelper::span('ponto.afd.processar'");
    expect($source)->toContain("Log::info('ponto.afd.processar.concluido'");
});

// ------------------------------------------------------------------
// Cenario 2: Jobs emitem log estruturado entry com business_id (Tier 0)
// ------------------------------------------------------------------

it('Jobs Ponto emitem log estruturado entry com business_id (Tier 0 ADR 0093 — D9.b Wave 16)', function () {
    $afdJob = file_get_contents(
        (new ReflectionClass(\Modules\Ponto\Jobs\ProcessarImportacaoAfdJob::class))->getFileName()
    );
    $reapurarJob = file_get_contents(
        (new ReflectionClass(\Modules\Ponto\Jobs\ReapurarDiaJob::class))->getFileName()
    );

    expect($afdJob)->toContain("Log::info('ponto.afd.job.iniciado'");
    expect($afdJob)->toContain("'business_id'");
    expect($reapurarJob)->toContain("Log::info('ponto.apuracao.job.iniciado'");
    expect($reapurarJob)->toContain("'business_id'");
});

// ------------------------------------------------------------------
// Cenario 3: comando ponto:health registrado e executa
// ------------------------------------------------------------------

it('comando ponto:health esta registrado no kernel (D9 Wave 16)', function () {
    $artisan = app(\Illuminate\Contracts\Console\Kernel::class);
    $commands = $artisan->all();

    expect($commands)->toHaveKey('ponto:health');
    expect($commands['ponto:health'])->toBeInstanceOf(\Modules\Ponto\Console\Commands\PontoHealthCommand::class);
});

it('comando ponto:health executa sem fatal e retorna 0 sem --alert (info-only)', function () {
    obsRequireMysql($this);
    if (! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Tabela ponto_marcacoes ausente — rode migrations Modules/Ponto.');
    }

    $exit = Artisan::call('ponto:health', ['--business-id' => OBS_BIZ_WAGNER]);

    expect($exit)->toBe(0); // info-only sem --alert
    $output = Artisan::output();
    expect($output)->toContain('trigger_imutabilidade');
    expect($output)->toContain('ultima_marcacao_recente');
});

it('comando ponto:health --json produz JSON valido com 5 checks', function () {
    obsRequireMysql($this);
    if (! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Tabela ponto_marcacoes ausente — rode migrations Modules/Ponto.');
    }

    Artisan::call('ponto:health', ['--business-id' => OBS_BIZ_WAGNER, '--json' => true]);
    $output = trim(Artisan::output());

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKey('checks')
        ->and($decoded['module'])->toBe('Ponto')
        ->and($decoded['business_id'])->toBe(OBS_BIZ_WAGNER)
        ->and($decoded['checks'])->toHaveKeys([
            'trigger_imutabilidade',
            'ultima_marcacao_recente',
            'hash_chain_integro',
            'apuracao_pendente_lag',
            'nsr_sequencial',
        ]);

    foreach ($decoded['checks'] as $name => $check) {
        expect($check)->toHaveKey('status', "Check {$name} sem status");
        expect($check['status'])->toBeIn(['OK', 'WARN', 'FAIL']);
    }
});

it('comando ponto:health emite log estruturado ponto.health.check.executado', function () {
    obsRequireMysql($this);
    if (! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Tabela ponto_marcacoes ausente.');
    }

    Log::spy();

    Artisan::call('ponto:health', ['--business-id' => OBS_BIZ_WAGNER, '--json' => true]);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'ponto.health.check.executado'
                && is_array($context)
                && array_key_exists('business_id', $context)
                && array_key_exists('worst_status', $context);
        })
        ->atLeast()->once();
});
