<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Services\Memoria\SupersedeDetector;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Consolidação APPEND-ONLY do supersede event-time (ADR 0295 slice 3) contra o
 * schema REAL. Verifica que SupersedeDetector::consolidar():
 *   - FECHA o antigo (valid_until system-time + event_valid_until event-time);
 *   - NUNCA edita o conteúdo do antigo (append-only — `fato` intacto);
 *   - cria o novo já linkado por supersedes_id, com event_valid_from aberto.
 *
 * MySQL-ONLY (lane jana-pest.yml): a tabela jana_memoria_facts + as colunas
 * event-time da migration slice 1 precisam existir no schema migrado. No SQLite
 * :memory: do harness per-PR isso pode faltar → markTestSkipped (igual padrão da
 * lane financeiro-pest / ImmutabilityTriggersTest).
 *
 * Isolamento: DatabaseTransactions (rollback). Scout desligado por
 * withoutSyncingToSearch (sem rede). Activitylog desligado (sem ruído de log).
 * biz=1/user=1 (sem FK na tabela — create direto; ADR 0101 convenção de teste).
 */
beforeEach(function () {
    $driver = DB::connection()->getDriverName();
    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
        test()->markTestSkipped('MySQL-only: schema event-time real (lane jana-pest.yml).');
    }

    if (! Schema::hasTable('jana_memoria_facts')) {
        test()->markTestSkipped('Tabela jana_memoria_facts ausente — rode migrate.');
    }

    foreach (['event_valid_from', 'event_valid_until', 'supersedes_id'] as $col) {
        if (! Schema::hasColumn('jana_memoria_facts', $col)) {
            test()->markTestSkipped("Coluna {$col} ausente — migration slice 1 (ADR 0295) não rodou.");
        }
    }

    // Sem ruído de activity log na consolidação (e sem depender da tabela).
    config(['activitylog.enabled' => false]);
});

it('consolidar fecha o antigo (system+event), linka o novo e preserva o conteúdo (append-only)', function () {
    $detector = new SupersedeDetector();

    $antigo = MemoriaFato::withoutSyncingToSearch(fn () => MemoriaFato::create([
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'A meta de faturamento é R$ 50 mil/mês',
        'metadata' => ['categoria' => 'meta'],
        'valid_from' => now()->subDay(),
        'event_valid_from' => now()->subDay(),
    ]));

    $novo = MemoriaFato::withoutSyncingToSearch(fn () => $detector->consolidar(
        $antigo,
        'A meta de faturamento agora é R$ 80 mil/mês',
        ['categoria' => 'meta', 'origem' => 'pest'],
    ));

    $antigoFresco = MemoriaFato::withoutGlobalScopes()->findOrFail($antigo->id);

    // APPEND-ONLY: conteúdo do antigo INTACTO; só as janelas fecharam.
    expect($antigoFresco->fato)->toBe('A meta de faturamento é R$ 50 mil/mês')
        ->and($antigoFresco->valid_until)->not->toBeNull()
        ->and($antigoFresco->event_valid_until)->not->toBeNull();

    // Novo: linkado + janelas abertas.
    expect($novo->supersedes_id)->toBe($antigo->id)
        ->and($novo->fato)->toBe('A meta de faturamento agora é R$ 80 mil/mês')
        ->and($novo->business_id)->toBe(1)
        ->and($novo->user_id)->toBe(1)
        ->and($novo->valid_until)->toBeNull()
        ->and($novo->event_valid_until)->toBeNull()
        ->and($novo->event_valid_from)->not->toBeNull();

    // O novo é uma OUTRA linha (append, não update in-place).
    expect($novo->id)->not->toBe($antigo->id);
})->group('jana', 'memoria', 'bitemporal');
