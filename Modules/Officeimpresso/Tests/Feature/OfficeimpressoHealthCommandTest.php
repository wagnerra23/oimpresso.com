<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * `officeimpresso:health` — os checks de tabela olham as tabelas que existem.
 *
 * Até 2026-11-18 o comando procurava `licenca_computadores` e `licenca_logs` (plural);
 * as tabelas reais são `licenca_computador` e `licenca_log` (Entities/Licenca_Computador
 * e Entities/LicencaLog). Resultado: WARN com as tabelas presentes e nunca FAIL com
 * elas ausentes — o comando não conseguia acusar o que existe para acusar.
 *
 * Os dois casos discriminam: com o nome no plural, o caso "presente" cai (WARN/FAIL
 * em vez de OK); se a ausência voltar a ser WARN, o caso "ausente" cai.
 *
 * Read-only: o comando só lê; nenhuma fixture é gravada.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0101).');
    }
});

function oiHealthJson(array $args = []): array
{
    $exit = Artisan::call('officeimpresso:health', array_merge(['--json' => true], $args));
    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toBeArray();

    $checks = [];
    foreach ($payload['checks'] as $c) {
        $checks[$c['name']] = $c;
    }

    return ['exit' => $exit, 'checks' => $checks, 'summary' => $payload['summary']];
}

it('dá OK nos checks de tabela quando licenca_computador e licenca_log existem', function () {
    expect(Schema::hasTable('licenca_computador'))->toBeTrue();
    expect(Schema::hasTable('licenca_log'))->toBeTrue();

    $r = oiHealthJson();

    expect($r['checks']['licencas_table_present']['status'])->toBe('OK');
    expect($r['checks']['licencas_table_present']['details'])->toContain('licenca_computador');
    expect($r['checks']['licenca_logs_table_present']['status'])->toBe('OK');
    expect($r['checks']['licenca_logs_table_present']['details'])->toContain('licenca_log');
    expect($r['checks']['bloqueadas_count']['status'])->toBe('OK');
    // pings 24h depende de tráfego: OK ou WARN, nunca FAIL com a tabela presente.
    expect($r['checks']['desktop_pings_24h']['status'])->not->toBe('FAIL');
    expect($r['summary']['fail'])->toBe(0);
});

it('dá FAIL e exit 2 com --alert quando as tabelas estão ausentes', function () {
    Schema::shouldReceive('hasTable')->zeroOrMoreTimes()->andReturnFalse();

    $r = oiHealthJson(['--alert' => true]);

    expect($r['checks']['licencas_table_present']['status'])->toBe('FAIL');
    expect($r['checks']['licenca_logs_table_present']['status'])->toBe('FAIL');
    expect($r['checks']['desktop_pings_24h']['status'])->toBe('FAIL');
    expect($r['checks']['bloqueadas_count']['status'])->toBe('FAIL');
    expect($r['summary']['fail'])->toBe(4);
    expect($r['exit'])->toBe(2);
});
