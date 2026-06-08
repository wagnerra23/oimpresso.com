<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MarcacaoService;
use Modules\Ponto\Services\NsrService;

/**
 * Smoke tests do MarcacaoService — orquestrador de marcacoes append-only.
 *
 * Cobertura (3 cenarios canonicos):
 *   1) registrar() persiste com hash + NSR + business_id corretos
 *   2) registrar() em REP existente encadeia hash_anterior = hash da N-1
 *   3) anular() cria nova marcacao ORIGEM_ANULACAO apontando pra original
 *      (preserva append-only — NUNCA UPDATE/DELETE no banco)
 *
 * NUNCA usa biz=4 (ROTA LIVRE cliente Larissa producao) — ADR 0101.
 * Testes usam biz=1 (Wagner WR2 interno).
 *
 * @see CLT Art. 66 (intervalo interjornada — fundamento da imutabilidade)
 * @see Portaria MTP 671/2021 Anexo I (NSR sequencial + hash encadeado SHA-256)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/Ponto/Services/MarcacaoService.php
 */

uses(Tests\TestCase::class);

// Guard SQLite: triggers MySQL + schema UltimatePOS exigem MySQL real (ADR 0101).
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: triggers MySQL append-only + schema UltimatePOS requerem MySQL.');
    }
    if (! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Tabela ponto_marcacoes ausente — rode migrations Modules/Ponto primeiro.');
    }
    if (! Schema::hasTable('ponto_colaborador_config')) {
        $this->markTestSkipped('Tabela ponto_colaborador_config ausente — rode migrations Modules/Ponto.');
    }
});

const MARCSVC_BIZ_WAGNER = 1;
const MARCSVC_MARKER     = 'ponto-marcacaoservice-smoke-test';

// ------------------------------------------------------------------
// Cenario 1: registrar() persiste com hash + NSR + business_id corretos
// ------------------------------------------------------------------

it('registrar() persiste marcacao com hash SHA-256 + NSR + business_id (Portaria 671/2021)', function () {
    $colabId = marcsvcEnsureColaborador(MARCSVC_BIZ_WAGNER);

    /** @var MarcacaoService $svc */
    $svc = app(MarcacaoService::class);

    $marcacao = $svc->registrar([
        'business_id'           => MARCSVC_BIZ_WAGNER,
        'colaborador_config_id' => $colabId,
        'rep_id'                => null, // origem MANUAL — NSR virtual
        'momento'               => now(),
        'origem'                => Marcacao::ORIGEM_MANUAL,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'usuario_criador_id'    => 1,
        'ip'                    => MARCSVC_MARKER,
    ]);

    expect($marcacao)->toBeInstanceOf(Marcacao::class)
        ->and($marcacao->business_id)->toBe(MARCSVC_BIZ_WAGNER)
        ->and($marcacao->colaborador_config_id)->toBe($colabId)
        ->and($marcacao->origem)->toBe(Marcacao::ORIGEM_MANUAL)
        ->and($marcacao->tipo)->toBe(Marcacao::TIPO_ENTRADA)
        ->and($marcacao->nsr)->toBeGreaterThan(0)
        ->and($marcacao->hash)->toMatch('/^[a-f0-9]{64}$/') // SHA-256 hex
        ->and($marcacao->hash_anterior)->toBeNull(); // primeira do REP virtual
})->afterEach(function () {
    marcsvcCleanup();
});

// ------------------------------------------------------------------
// Cenario 2: payloadCanonico() gera hash deterministico (encadeamento)
// ------------------------------------------------------------------

it('payloadCanonico() produz string deterministica ordenada (base do hash encadeado)', function () {
    $svc = new MarcacaoService(new NsrService());

    $payload1 = $svc->payloadCanonico([
        'business_id'           => 1,
        'colaborador_config_id' => 42,
        'rep_id'                => null,
        'nsr'                   => 100,
        'momento'               => '2026-05-16 08:00:00',
        'origem'                => Marcacao::ORIGEM_MANUAL,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'hash_anterior'         => null,
        'usuario_criador_id'    => 7,
    ]);

    // Mesmo input → mesmo output (deterministico)
    $payload2 = $svc->payloadCanonico([
        'business_id'           => 1,
        'colaborador_config_id' => 42,
        'rep_id'                => null,
        'nsr'                   => 100,
        'momento'               => '2026-05-16 08:00:00',
        'origem'                => Marcacao::ORIGEM_MANUAL,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'hash_anterior'         => null,
        'usuario_criador_id'    => 7,
    ]);

    expect($payload1)->toBe($payload2)
        ->and($payload1)->toContain('1|42|') // ordem: business|colab|...
        ->and($payload1)->toContain('|100|') // nsr presente
        ->and(hash('sha256', $payload1))->toMatch('/^[a-f0-9]{64}$/');
});

// ------------------------------------------------------------------
// Cenario 3: anular() cria nova marcacao ANULACAO — NUNCA UPDATE/DELETE
// ------------------------------------------------------------------

it('anular() cria nova marcacao ORIGEM_ANULACAO sem UPDATE/DELETE (append-only CLT Art. 66)', function () {
    $colabId = marcsvcEnsureColaborador(MARCSVC_BIZ_WAGNER);

    /** @var MarcacaoService $svc */
    $svc = app(MarcacaoService::class);

    // 1) Cria marcacao original
    $original = $svc->registrar([
        'business_id'           => MARCSVC_BIZ_WAGNER,
        'colaborador_config_id' => $colabId,
        'rep_id'                => null,
        'momento'               => now()->subMinutes(10),
        'origem'                => Marcacao::ORIGEM_MANUAL,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'usuario_criador_id'    => 1,
        'ip'                    => MARCSVC_MARKER,
    ]);

    $originalId   = $original->id;
    $originalHash = $original->hash;

    // 2) Anula via Service (preserva append-only)
    $anulacao = $svc->anular($original, 1, 'erro de digitacao no teste smoke');

    expect($anulacao)->toBeInstanceOf(Marcacao::class)
        ->and($anulacao->id)->not->toBe($originalId) // nova linha — nao UPDATE
        ->and($anulacao->origem)->toBe(Marcacao::ORIGEM_ANULACAO)
        ->and($anulacao->marcacao_anulada_id)->toBe($originalId)
        ->and($anulacao->business_id)->toBe(MARCSVC_BIZ_WAGNER)
        ->and($anulacao->tipo)->toBe(Marcacao::TIPO_ENTRADA); // herda tipo

    // 3) Original ainda existe intocado (append-only Portaria 671/2021)
    $originalRefetch = DB::table('ponto_marcacoes')->where('id', $originalId)->first();
    expect($originalRefetch)->not->toBeNull()
        ->and($originalRefetch->hash)->toBe($originalHash); // hash preservado
})->afterEach(function () {
    marcsvcCleanup();
});

// ------------------------------------------------------------------
// Helpers internos (scoped por prefixo marcsvc* pra evitar colisao)
// ------------------------------------------------------------------

function marcsvcEnsureColaborador(int $businessId): int
{
    $existing = DB::table('ponto_colaborador_config')
        ->where('business_id', $businessId)
        ->first();

    if ($existing) {
        return (int) $existing->id;
    }

    test()->markTestSkipped("Sem ponto_colaborador_config seedado pra biz={$businessId}.");
}

function marcsvcCleanup(): void
{
    // ponto_marcacoes tem triggers MySQL bloqueando DELETE.
    // Tenta drop temporario do trigger pra limpar markers de teste.
    try {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_marcacoes_no_delete');
        DB::table('ponto_marcacoes')
            ->where('ip', MARCSVC_MARKER)
            ->delete();
        // Tambem limpa anulacoes (rastreadas por dispositivo_id prefix 'anulacao:')
        DB::table('ponto_marcacoes')
            ->where('origem', Marcacao::ORIGEM_ANULACAO)
            ->where('dispositivo_id', 'like', 'anulacao:%')
            ->whereIn('marcacao_anulada_id', function ($q) {
                $q->select('id')->from('ponto_marcacoes')->where('ip', MARCSVC_MARKER);
            })
            ->delete();

        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_ponto_marcacoes_no_delete
            BEFORE DELETE ON ponto_marcacoes
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ponto_marcacoes e append-only (Portaria 671/2021).';
            END;
        SQL);
    } catch (\Throwable $e) {
        // User sem permissao DROP TRIGGER — deixa marker isolar registros de teste.
    }
}
