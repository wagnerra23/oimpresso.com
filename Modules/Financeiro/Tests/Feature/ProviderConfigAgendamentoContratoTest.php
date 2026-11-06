<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Financeiro\Providers\FinanceiroServiceProvider;

uses(Tests\TestCase::class);

/**
 * Auditoria do Financeiro 2026-11-05 (lote 1.3) — configuração do FinanceiroServiceProvider.
 *
 * Três contratos, um por defeito encontrado:
 *  1. `Config/retention.php` é lido em `financeiro.retention` — o `FinanceiroHealthCommand`
 *     (check 6) lê `config('financeiro.retention.enabled')`, e sem o merge a chave nunca existia.
 *  2. A publicação de config não aponta para `config/financeiro.php`: esse arquivo já existe
 *     na raiz com OUTRAS chaves (pluggy, conciliacao, extrato_bancos) e um
 *     `vendor:publish --tag=config --force` o trocaria pelo do módulo.
 *  3. Os 3 comandos agendados só rodam em produção (`APP_ENV=live`, mesmo padrão do
 *     app/Console/Kernel.php). Prova por `runsInEnvironment()`, a função que o
 *     `schedule:run` chama — `schedule:list` lista o comando em qualquer ambiente
 *     (§5 2026-09-05).
 */
function finConfigEventos(): array
{
    $alvos = ['financeiro:regua-cobranca', 'financeiro:pluggy-sync', 'financeiro:pagamentos-banco-sync'];
    $achados = [];
    foreach (app(Schedule::class)->events() as $evento) {
        foreach ($alvos as $alvo) {
            if (str_contains((string) $evento->command, $alvo)) {
                $achados[$alvo] = $evento;
            }
        }
    }

    return $achados;
}

it('retention.php é carregado em financeiro.retention', function () {
    expect(config('financeiro.retention'))->toBeArray();
    expect((int) config('financeiro.retention.titulos.days'))->toBe(1825);
    expect(config('financeiro.retention.titulos.tables'))->toBe(['fin_titulos', 'fin_titulo_baixas']);
    // As chaves da raiz (config/financeiro.php) e do módulo convivem.
    expect(config('financeiro.pluggy'))->toBeArray();
    expect(config('financeiro.link_pagamento'))->toBeArray();
});

it('a publicação de config do módulo não sobrescreve config/financeiro.php da raiz', function () {
    $destinos = array_values(ServiceProvider::pathsToPublish(FinanceiroServiceProvider::class));

    expect($destinos)->not->toContain(config_path('financeiro.php'));
    expect(array_values(ServiceProvider::pathsToPublish(FinanceiroServiceProvider::class, 'config')))
        ->not->toContain(config_path('financeiro.php'));
});

it('os 3 comandos agendados do Financeiro só rodam em produção (live)', function () {
    $eventos = finConfigEventos();

    expect(array_keys($eventos))->toEqualCanonicalizing([
        'financeiro:regua-cobranca', 'financeiro:pluggy-sync', 'financeiro:pagamentos-banco-sync',
    ]);

    foreach ($eventos as $comando => $evento) {
        expect($evento->runsInEnvironment('live'))->toBeTrue("{$comando} deveria rodar em live");
        expect($evento->runsInEnvironment('staging'))->toBeFalse("{$comando} não pode rodar em staging");
        expect($evento->runsInEnvironment('local'))->toBeFalse("{$comando} não pode rodar em local");
        expect($evento->runsInEnvironment('testing'))->toBeFalse("{$comando} não pode rodar em testing");
    }
});
