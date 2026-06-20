<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Services\Memoria\HistoricoMemoriaService;

/**
 * Teste DB (MySQL-only) do time-travel buscarHistorico (ADR 0295, T4 slice 2).
 *
 * Roda na lane MySQL (allowlist jana-pest.yml) — exige as colunas event_valid_*
 * adicionadas pela migration do slice 1. Usa DatabaseTransactions (NAO
 * RefreshDatabase: a lane preserva schema + seed biz=1/biz=2; migrate:fresh
 * dropa FK e envenena os demais testes da catraca).
 *
 * Valida: (1) filtro event-time inicio-inclusivo/fim-exclusivo, (2) fato
 * superseded (valid_until != NULL, fora do Scout) reaparece no time-travel,
 * (3) isolamento multi-tenant business_id + user_id (Tier 0, ADR 0093).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    // Lane MySQL (jana-pest.yml): o schema baseline + migration do slice 1 garantem
    // as colunas event_valid_*. Skip defensivo se rodar fora de MySQL/MariaDB.
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('buscarHistorico exercitado na lane MySQL (jana-pest.yml).');
    }

    $this->service = new HistoricoMemoriaService();
});

function fatoTeste(array $attrs): MemoriaFato
{
    return MemoriaFato::create(array_merge([
        'business_id' => 1,
        'user_id' => 777001,
        'fato' => 'fato de teste',
    ], $attrs));
}

it('inclui fato event-valido no as_of e exclui o ja expirado (fim exclusivo)', function () {
    $vigente = fatoTeste([
        'fato' => 'meta trimestral 100k',
        'event_valid_from' => '2026-01-01 00:00:00',
        'event_valid_until' => null,
    ]);
    $expirado = fatoTeste([
        'fato' => 'meta antiga 50k',
        'event_valid_from' => '2025-01-01 00:00:00',
        'event_valid_until' => '2026-01-01 00:00:00', // fim exclusivo: nao vale em/depois de 2026-01-01
        'valid_until' => now(),                        // superseded no sistema => fora do Scout
    ]);

    $ids = $this->service->buscarHistorico(1, 777001, '2026-06-01 00:00:00')->pluck('id')->all();

    expect($ids)->toContain($vigente->id)
        ->and($ids)->not->toContain($expirado->id);
});

it('time-travel ao passado reencontra fato superseded (fora do index Meilisearch)', function () {
    $expirado = fatoTeste([
        'fato' => 'meta antiga 50k',
        'event_valid_from' => '2025-01-01 00:00:00',
        'event_valid_until' => '2026-01-01 00:00:00',
        'valid_until' => now(),
    ]);

    $ids = $this->service->buscarHistorico(1, 777001, '2025-06-01 00:00:00')->pluck('id')->all();

    expect($ids)->toContain($expirado->id);
});

it('isola por business_id e user_id (multi-tenant Tier 0)', function () {
    $meu = fatoTeste(['user_id' => 777001]);
    $outroUser = fatoTeste(['user_id' => 777002]);
    $outroBiz = fatoTeste(['business_id' => 2, 'user_id' => 777001]);

    $ids = $this->service->buscarHistorico(1, 777001, '2026-06-01 00:00:00')->pluck('id')->all();

    expect($ids)->toContain($meu->id)
        ->and($ids)->not->toContain($outroUser->id)
        ->and($ids)->not->toContain($outroBiz->id);
});
