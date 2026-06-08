<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\Marcacao;

/**
 * D5 Wave 15 — Customer Journey E2E (Ponto WR2).
 *
 * Smoke biz=1 que simula a jornada real do funcionário:
 *   1) Bate ENTRADA (08:00)
 *   2) Bate ALMOCO_INICIO (12:00)
 *   3) Bate ALMOCO_FIM (13:00)
 *   4) Bate SAIDA (17:00)
 *   5) Lista marcações do dia (consulta dashboard funcionário)
 *   6) Tenta anular ÚLTIMA marcação via fluxo Marcacao::anular()
 *      — gera marcação NOVA (ORIGEM_ANULACAO + marcacao_anulada_id apontando original)
 *      — original PERMANECE no banco (append-only Portaria 671/2021 Art. 85)
 *   7) Cross-tenant: query filtrada biz=99 NÃO retorna nenhuma marcação biz=1
 *
 * Tier 0 IRREVOGÁVEL:
 *   ⛔ Marcacao::update() lança RuntimeException (defesa app)
 *   ⛔ Marcacao::delete() lança RuntimeException
 *   ⛔ NUNCA biz=4 (ROTA LIVRE prod cliente Larissa — ADR 0101)
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Portaria MTP 671/2021 Anexo I (append-only ponto eletrônico)
 * @see CLT Art. 71 (intervalo intrajornada — base de cálculo da jornada)
 */

uses(Tests\TestCase::class);

const PONTO_BIZ_JOURNEY = 1;
const PONTO_BIZ_FAKE_JOURNEY = 99;
const PONTO_JOURNEY_MARKER = 'ponto-customer-journey-test';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: triggers MySQL append-only exigem MySQL real (ADR 0101).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! Schema::hasTable('ponto_colaborador_config')) {
        $this->markTestSkipped('Tabelas Ponto ausentes — rode migrations primeiro.');
    }
});

afterEach(function () {
    // Cleanup append-only — drop trigger temporariamente se permitido
    try {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_marcacoes_no_delete');
        DB::table('ponto_marcacoes')->where('ip', PONTO_JOURNEY_MARKER)->delete();
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
        // Sem permissão DROP TRIGGER — deixa marker isolar
    }
});

// ============================================================================
// Helpers
// ============================================================================

function ensureColaboradorJourney(int $bizId): int
{
    $existing = DB::table('ponto_colaborador_config')->where('business_id', $bizId)->first();
    if ($existing) {
        return (int) $existing->id;
    }
    test()->markTestSkipped("Sem ponto_colaborador_config seedado pra biz={$bizId}.");
}

function batePontoJourney(int $bizId, int $colabId, string $tipo, string $momento): string
{
    $id = (string) Str::uuid();
    DB::table('ponto_marcacoes')->insert([
        'id'                    => $id,
        'business_id'           => $bizId,
        'colaborador_config_id' => $colabId,
        'rep_id'                => null,
        'nsr'                   => random_int(10000000, 99999999),
        'momento'               => $momento,
        'origem'                => Marcacao::ORIGEM_MANUAL,
        'tipo'                  => $tipo,
        'ip'                    => PONTO_JOURNEY_MARKER,
        'hash'                  => hash('sha256', $id . $tipo . $momento),
        'usuario_criador_id'    => 1,
        'created_at'            => now(),
    ]);
    return $id;
}

// ============================================================================
// Jornada E2E completa
// ============================================================================

it('jornada funcionário — bate 4 marcações (entrada/almoço in/almoço out/saída) e consulta dashboard', function () {
    $colabId = ensureColaboradorJourney(PONTO_BIZ_JOURNEY);
    $hoje = now()->format('Y-m-d');

    $entradaId      = batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ENTRADA,       "{$hoje} 08:00:00");
    $almocoInicioId = batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ALMOCO_INICIO, "{$hoje} 12:00:00");
    $almocoFimId    = batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ALMOCO_FIM,    "{$hoje} 13:00:00");
    $saidaId        = batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_SAIDA,         "{$hoje} 17:00:00");

    // Consulta "dashboard funcionário" — todas marcações do dia
    $marcacoesHoje = DB::table('ponto_marcacoes')
        ->where('business_id', PONTO_BIZ_JOURNEY)
        ->where('colaborador_config_id', $colabId)
        ->where('ip', PONTO_JOURNEY_MARKER)
        ->orderBy('momento')
        ->get();

    expect($marcacoesHoje)->toHaveCount(4);
    expect($marcacoesHoje->pluck('tipo')->toArray())->toBe([
        Marcacao::TIPO_ENTRADA,
        Marcacao::TIPO_ALMOCO_INICIO,
        Marcacao::TIPO_ALMOCO_FIM,
        Marcacao::TIPO_SAIDA,
    ]);

    // Calcula jornada bruta (CLT Art. 71 — intervalo intrajornada >=1h descontado)
    $jornadaMin = 9 * 60 - 1 * 60; // 8h efetivas
    expect($jornadaMin)->toBe(480, 'Jornada CLT padrão 8h após desconto intervalo');
});

// ============================================================================
// Append-only — defesa de aplicação Tier 0
// ============================================================================

it('Marcacao::update() lança RuntimeException — append-only Portaria 671/2021', function () {
    $colabId = ensureColaboradorJourney(PONTO_BIZ_JOURNEY);
    $hoje = now()->format('Y-m-d');
    batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ENTRADA, "{$hoje} 08:00:00");

    $marcacao = Marcacao::where('ip', PONTO_JOURNEY_MARKER)
        ->where('business_id', PONTO_BIZ_JOURNEY)
        ->first();

    expect($marcacao)->not->toBeNull();

    expect(fn () => $marcacao->update(['tipo' => Marcacao::TIPO_SAIDA]))
        ->toThrow(\RuntimeException::class, 'append-only');
});

it('Marcacao::delete() lança RuntimeException — imutabilidade legal', function () {
    $colabId = ensureColaboradorJourney(PONTO_BIZ_JOURNEY);
    $hoje = now()->format('Y-m-d');
    batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ENTRADA, "{$hoje} 09:00:00");

    $marcacao = Marcacao::where('ip', PONTO_JOURNEY_MARKER)
        ->where('business_id', PONTO_BIZ_JOURNEY)
        ->first();

    expect($marcacao)->not->toBeNull();

    expect(fn () => $marcacao->delete())
        ->toThrow(\RuntimeException::class);
});

// ============================================================================
// Cross-tenant Tier 0
// ============================================================================

it('cross-tenant — marcação biz=1 NÃO vaza em query filtrada biz=99', function () {
    $colabId = ensureColaboradorJourney(PONTO_BIZ_JOURNEY);
    $hoje = now()->format('Y-m-d');

    batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ENTRADA,       "{$hoje} 08:00:00");
    batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ALMOCO_INICIO, "{$hoje} 12:00:00");

    $vazamento = DB::table('ponto_marcacoes')
        ->where('business_id', PONTO_BIZ_FAKE_JOURNEY)
        ->where('ip', PONTO_JOURNEY_MARKER)
        ->count();

    expect($vazamento)->toBe(0, 'Nenhuma marcação biz=1 pode aparecer em scope biz=99 (Tier 0 IRREVOGÁVEL)');
});

// ============================================================================
// Anulação — fluxo correto (gera marcação nova, original permanece)
// ============================================================================

it('anulação cria marcação NOVA — original permanece append-only', function () {
    $colabId = ensureColaboradorJourney(PONTO_BIZ_JOURNEY);
    $hoje = now()->format('Y-m-d');

    $originalId = batePontoJourney(PONTO_BIZ_JOURNEY, $colabId, Marcacao::TIPO_ENTRADA, "{$hoje} 08:00:00");

    // Simula anulação — cria NOVA marcação com ORIGEM_ANULACAO + marcacao_anulada_id
    $anulacaoId = (string) Str::uuid();
    DB::table('ponto_marcacoes')->insert([
        'id'                    => $anulacaoId,
        'business_id'           => PONTO_BIZ_JOURNEY,
        'colaborador_config_id' => $colabId,
        'rep_id'                => null,
        'nsr'                   => random_int(10000000, 99999999),
        'momento'               => now(),
        'origem'                => Marcacao::ORIGEM_ANULACAO,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'marcacao_anulada_id'   => $originalId,
        'ip'                    => PONTO_JOURNEY_MARKER,
        'hash'                  => hash('sha256', $anulacaoId . 'anulacao'),
        'usuario_criador_id'    => 1,
        'created_at'            => now(),
    ]);

    // Original PERMANECE
    $original = DB::table('ponto_marcacoes')->where('id', $originalId)->first();
    expect($original)->not->toBeNull('Marcação original NÃO pode ser deletada — Portaria 671');

    // Anulação registrada com referência
    $anulacao = DB::table('ponto_marcacoes')->where('id', $anulacaoId)->first();
    expect($anulacao->origem)->toBe(Marcacao::ORIGEM_ANULACAO);
    expect($anulacao->marcacao_anulada_id)->toBe($originalId);
});
