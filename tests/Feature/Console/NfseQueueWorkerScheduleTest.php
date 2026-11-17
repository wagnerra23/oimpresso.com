<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

/**
 * Consumidor da fila `nfse` (auditoria NFSe, 2026-11-17).
 *
 * O EmitirNfseJob vai para a fila `nfse` (NfseEmissaoService, ->onQueue('nfse')). Em leitura
 * de produção de 2026-11-17, nenhum queue:work do schedule lia essa fila: a primeira emissão
 * real ficaria parada na tabela `jobs`. Estes casos leem o Schedule montado pelo Kernel
 * (Schedule::events), não o texto do arquivo.
 *
 * DB-less: só inspeciona o Schedule. Não declarar uses(TestCase): tests/Pest.php já registra.
 */

/** Filas lidas por cada queue:work agendado, a partir do --queue= do comando. */
function filasDoEvento(object $evento): array
{
    $comando = (string) ($evento->command ?? '');
    if (! str_contains($comando, 'queue:work') || ! preg_match('/--queue=(\S+)/', $comando, $m)) {
        return [];
    }

    return array_map('trim', explode(',', trim($m[1], "'\"")));
}

function eventosQueLeemNfse(): \Illuminate\Support\Collection
{
    return collect(app(Schedule::class)->events())
        ->filter(fn ($evento) => in_array('nfse', filasDoEvento($evento), true));
}

it('existe queue:work agendado lendo a fila nfse', function () {
    expect(eventosQueLeemNfse())->not->toBeEmpty();
});

it('o consumidor da fila nfse roda em producao (live) e não depende do gate de backlog', function () {
    // Neste teste queue.backlog_worker_enabled é o default (false): se a única entrada que lê
    // `nfse` fosse a do backlog, ela nem seria registrada e a lista viria vazia.
    expect(config('queue.backlog_worker_enabled'))->toBeFalsy();

    $vivos = eventosQueLeemNfse()->filter(fn ($evento) => $evento->runsInEnvironment('live'));

    expect($vivos)->not->toBeEmpty();
});

it('o consumidor da fila nfse tem um processo por vez e roda a cada minuto', function () {
    $evento = eventosQueLeemNfse()->first();

    expect($evento)->not->toBeNull();
    expect($evento->withoutOverlapping)->toBeTrue();
    expect($evento->expression)->toBe('* * * * *');
});

it('o --timeout do consumidor nfse fica abaixo do retry_after da conexão database', function () {
    $comando = (string) eventosQueLeemNfse()->first()->command;

    expect(preg_match('/--timeout=(\d+)/', $comando, $m))->toBe(1);

    // timeout >= retry_after deixaria outro worker reservar o job ainda em curso.
    expect((int) $m[1])->toBeLessThan((int) config('queue.connections.database.retry_after'));
    // abaixo de 60 cortaria a emissão na 2ª tentativa (Http::timeout(30) por tentativa).
    expect((int) $m[1])->toBeGreaterThan(60);
});
