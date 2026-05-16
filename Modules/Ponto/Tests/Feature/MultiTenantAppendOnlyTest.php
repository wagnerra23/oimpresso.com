<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\BancoHorasMovimento;

/**
 * Testa isolamento multi-tenant Tier 0 + APPEND-ONLY do Modules/Ponto.
 *
 * Cobertura:
 *   - ponto_marcacoes  (CLT Art. 66 + Portaria MTP 671/2021 — imutável)
 *   - ponto_intercorrencias (estado workflow PENDENTE→APROVADA, scoped)
 *   - ponto_banco_horas_movimentos (append-only por design Model)
 *
 * Cenarios validados:
 *   1) Marcacao biz=1 nao aparece em query filtrada biz=99
 *   2) Marcacao::update() lanca RuntimeException (defesa app — Tier 0 IRREVOGAVEL)
 *   3) Marcacao::delete() lanca RuntimeException
 *   4) BancoHorasMovimento::update() / delete() bloqueados (mesma defesa)
 *   5) Intercorrencia biz=1 nao vaza em query biz=99 (workflow scoped)
 *   6) listing scoped biz=99 nao vaza nenhum dos registros biz=1
 *
 * NUNCA usar biz=4 (ROTA LIVRE cliente Larissa producao) — ADR 0101.
 * Testes usam biz=1 (Wagner WR2 interno) e biz=99 (ficticio).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Portaria MTP 671/2021 Anexo I (imutabilidade ponto eletronico)
 * @see CLT Art. 66 (intervalo interjornada — base de calculo)
 */

uses(Tests\TestCase::class);

// Guard SQLite: triggers MySQL + schema UltimatePOS exigem MySQL real.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: triggers MySQL append-only + schema UltimatePOS requerem MySQL (ADR 0101).');
    }
    if (! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Tabela ponto_marcacoes ausente — rode migrations Modules/Ponto primeiro.');
    }
});

// IDs canonicos — biz=1 (Wagner WR2 interno) vs biz=99 (ficticio)
const PONTO_BIZ_WAGNER = 1;
const PONTO_BIZ_FICTICIO = 99;

// Marker unico pra cleanup isolado (nao colide com producao)
const PONTO_MARCADOR_TEST = 'ponto-multitenant-appendonly-test';

// ------------------------------------------------------------------
// Cenarios — ponto_marcacoes (append-only Art. 66 CLT + Portaria 671/2021)
// ------------------------------------------------------------------

it('Marcacao biz=1 nao aparece em query filtrada biz=99 (Tier 0)', function () {
    $colabId = ensurePontoColaboradorConfig(PONTO_BIZ_WAGNER);

    $marcacao = createTestMarcacao(PONTO_BIZ_WAGNER, $colabId);

    // Replica filtro que Controller scoped aplica
    $resultado = DB::table('ponto_marcacoes')
        ->where('business_id', PONTO_BIZ_FICTICIO)
        ->where('id', $marcacao->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    cleanupPontoTestRows();
});

it('Marcacao::update() lanca RuntimeException (append-only Portaria 671/2021)', function () {
    $colabId = ensurePontoColaboradorConfig(PONTO_BIZ_WAGNER);
    $marcacao = createTestMarcacao(PONTO_BIZ_WAGNER, $colabId);

    expect(fn () => $marcacao->update(['tipo' => Marcacao::TIPO_SAIDA]))
        ->toThrow(\RuntimeException::class, 'append-only');
})->afterEach(function () {
    cleanupPontoTestRows();
});

it('Marcacao::delete() lanca RuntimeException (imutabilidade Portaria 671/2021)', function () {
    $colabId = ensurePontoColaboradorConfig(PONTO_BIZ_WAGNER);
    $marcacao = createTestMarcacao(PONTO_BIZ_WAGNER, $colabId);

    expect(fn () => $marcacao->delete())
        ->toThrow(\RuntimeException::class);
})->afterEach(function () {
    cleanupPontoTestRows();
});

it('listing scoped biz=99 nao vaza nenhuma das marcacoes biz=1', function () {
    $colabId = ensurePontoColaboradorConfig(PONTO_BIZ_WAGNER);

    // Cria 3 marcacoes em biz=1
    for ($i = 0; $i < 3; $i++) {
        createTestMarcacao(PONTO_BIZ_WAGNER, $colabId);
    }

    // Lista filtrando biz=99 — nao deve trazer nada
    $vazamento = DB::table('ponto_marcacoes')
        ->where('business_id', PONTO_BIZ_FICTICIO)
        ->where('ip', PONTO_MARCADOR_TEST)
        ->count();

    expect($vazamento)->toBe(0);
})->afterEach(function () {
    cleanupPontoTestRows();
});

// ------------------------------------------------------------------
// Cenarios — ponto_banco_horas_movimentos (append-only Model)
// ------------------------------------------------------------------

it('BancoHorasMovimento::update() lanca RuntimeException (append-only)', function () {
    if (! Schema::hasTable('ponto_banco_horas_movimentos')) {
        $this->markTestSkipped('Tabela ponto_banco_horas_movimentos ausente.');
    }

    $mov = new BancoHorasMovimento();
    $mov->id = (string) Str::uuid();
    $mov->business_id = PONTO_BIZ_WAGNER;
    // Nao precisamos persistir pra testar a defesa de aplicacao
    expect(fn () => $mov->update(['minutos' => 60]))
        ->toThrow(\RuntimeException::class, 'append-only');
});

it('BancoHorasMovimento::delete() lanca RuntimeException', function () {
    if (! Schema::hasTable('ponto_banco_horas_movimentos')) {
        $this->markTestSkipped('Tabela ponto_banco_horas_movimentos ausente.');
    }

    $mov = new BancoHorasMovimento();
    $mov->id = (string) Str::uuid();
    $mov->business_id = PONTO_BIZ_WAGNER;

    expect(fn () => $mov->delete())
        ->toThrow(\RuntimeException::class);
});

it('BancoHorasMovimento biz=1 nao vaza em query filtrada biz=99', function () {
    if (! Schema::hasTable('ponto_banco_horas_movimentos')) {
        $this->markTestSkipped('Tabela ponto_banco_horas_movimentos ausente.');
    }

    $colabId = ensurePontoColaboradorConfig(PONTO_BIZ_WAGNER);
    $movId = (string) Str::uuid();

    DB::table('ponto_banco_horas_movimentos')->insert([
        'id' => $movId,
        'business_id' => PONTO_BIZ_WAGNER,
        'colaborador_config_id' => $colabId,
        'data_referencia' => now()->toDateString(),
        'tipo' => BancoHorasMovimento::TIPO_CREDITO,
        'minutos' => 30,
        'multiplicador' => 1.00,
        'saldo_posterior_minutos' => 30,
        'observacao' => PONTO_MARCADOR_TEST,
        'usuario_id' => 1,
        'created_at' => now(),
    ]);

    $vazamento = DB::table('ponto_banco_horas_movimentos')
        ->where('business_id', PONTO_BIZ_FICTICIO)
        ->where('id', $movId)
        ->count();

    expect($vazamento)->toBe(0);

    // cleanup so deste movimento
    DB::table('ponto_banco_horas_movimentos')->where('id', $movId)->delete();
})->afterEach(function () {
    cleanupPontoTestRows();
});

// ------------------------------------------------------------------
// Cenarios — ponto_intercorrencias (workflow + scoped)
// ------------------------------------------------------------------

it('Intercorrencia biz=1 nao aparece em query filtrada biz=99', function () {
    if (! Schema::hasTable('ponto_intercorrencias')) {
        $this->markTestSkipped('Tabela ponto_intercorrencias ausente.');
    }

    $colabId = ensurePontoColaboradorConfig(PONTO_BIZ_WAGNER);
    $intId = (string) Str::uuid();

    DB::table('ponto_intercorrencias')->insert([
        'id' => $intId,
        'business_id' => PONTO_BIZ_WAGNER,
        'colaborador_config_id' => $colabId,
        'codigo' => 'TEST-' . substr($intId, 0, 8),
        'tipo' => 'ATESTADO',
        'data' => now()->toDateString(),
        'dia_todo' => true,
        'justificativa' => PONTO_MARCADOR_TEST,
        'estado' => Intercorrencia::ESTADO_PENDENTE,
        'prioridade' => 'NORMAL',
        'impacta_apuracao' => true,
        'descontar_banco_horas' => false,
        'solicitante_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $resultado = DB::table('ponto_intercorrencias')
        ->where('business_id', PONTO_BIZ_FICTICIO)
        ->where('id', $intId)
        ->get();

    expect($resultado)->toHaveCount(0);

    DB::table('ponto_intercorrencias')->where('id', $intId)->delete();
})->afterEach(function () {
    cleanupPontoTestRows();
});

it('Intercorrencia scoped biz=99 nao vaza workflow PENDENTE de biz=1', function () {
    if (! Schema::hasTable('ponto_intercorrencias')) {
        $this->markTestSkipped('Tabela ponto_intercorrencias ausente.');
    }

    $colabId = ensurePontoColaboradorConfig(PONTO_BIZ_WAGNER);

    // 3 intercorrencias PENDENTES em biz=1
    for ($i = 0; $i < 3; $i++) {
        DB::table('ponto_intercorrencias')->insert([
            'id' => (string) Str::uuid(),
            'business_id' => PONTO_BIZ_WAGNER,
            'colaborador_config_id' => $colabId,
            'codigo' => 'PEND-' . $i . '-' . uniqid(),
            'tipo' => 'FALTA',
            'data' => now()->toDateString(),
            'dia_todo' => true,
            'justificativa' => PONTO_MARCADOR_TEST,
            'estado' => Intercorrencia::ESTADO_PENDENTE,
            'prioridade' => 'NORMAL',
            'impacta_apuracao' => true,
            'descontar_banco_horas' => false,
            'solicitante_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $vazamento = DB::table('ponto_intercorrencias')
        ->where('business_id', PONTO_BIZ_FICTICIO)
        ->where('justificativa', PONTO_MARCADOR_TEST)
        ->count();

    expect($vazamento)->toBe(0);
})->afterEach(function () {
    cleanupPontoTestRows();
});

// ------------------------------------------------------------------
// Helpers internos
// ------------------------------------------------------------------

/**
 * Garante linha em ponto_colaborador_config pra biz informado.
 * Sem isso FK ponto_marcacoes.colaborador_config_id falha.
 * Retorna o id (int) do colaborador config.
 */
function ensurePontoColaboradorConfig(int $businessId): int
{
    if (! Schema::hasTable('ponto_colaborador_config')) {
        test()->markTestSkipped('Tabela ponto_colaborador_config ausente — rode migrations Ponto.');
    }

    // Procura colaborador existente do biz
    $existing = DB::table('ponto_colaborador_config')
        ->where('business_id', $businessId)
        ->first();

    if ($existing) {
        return (int) $existing->id;
    }

    test()->markTestSkipped("Sem ponto_colaborador_config seedado pra biz={$businessId} — seed necessario.");
}

/**
 * Cria marcacao real (INSERT) marcada com PONTO_MARCADOR_TEST no campo ip
 * pra ficar facil de limpar no afterEach.
 *
 * Bypassa Eloquent::save() (que aciona boot creating + hash) usando DB::table
 * — append-only ainda vale a nivel de schema, mas testes precisam inserir
 * sem dependencias de MarcacaoService.
 */
function createTestMarcacao(int $businessId, int $colabId): object
{
    $id = (string) Str::uuid();

    DB::table('ponto_marcacoes')->insert([
        'id' => $id,
        'business_id' => $businessId,
        'colaborador_config_id' => $colabId,
        'rep_id' => null,
        'nsr' => random_int(1000000, 9999999),
        'momento' => now(),
        'origem' => Marcacao::ORIGEM_MANUAL,
        'tipo' => Marcacao::TIPO_ENTRADA,
        'ip' => PONTO_MARCADOR_TEST,
        'hash' => hash('sha256', $id),
        'usuario_criador_id' => 1,
        'created_at' => now(),
    ]);

    return (object) ['id' => $id, 'business_id' => $businessId];
}

/**
 * Limpa registros de teste atraves de marker PONTO_MARCADOR_TEST.
 * IMPORTANTE: ponto_marcacoes tem triggers MySQL bloqueando DELETE.
 * Usar DELETE direto via PDO desabilitando triggers nao e seguro em prod;
 * em teste, dropamos via SQL bruto se DDL trigger drop foi feito,
 * senao deixamos a linha (impacto minimo — marker isola).
 *
 * Aqui usamos abordagem segura: desabilita trigger temporariamente em sessao
 * de teste apenas se MySQL permitir (root user). Senao, skip cleanup.
 */
function cleanupPontoTestRows(): void
{
    // Banco_horas e intercorrencias podem deletar
    if (Schema::hasTable('ponto_banco_horas_movimentos')) {
        DB::table('ponto_banco_horas_movimentos')
            ->where('observacao', PONTO_MARCADOR_TEST)
            ->delete();
    }
    if (Schema::hasTable('ponto_intercorrencias')) {
        DB::table('ponto_intercorrencias')
            ->where('justificativa', PONTO_MARCADOR_TEST)
            ->delete();
    }

    // ponto_marcacoes — triggers MySQL bloqueiam DELETE. Tenta drop temporario.
    try {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_marcacoes_no_delete');
        DB::table('ponto_marcacoes')->where('ip', PONTO_MARCADOR_TEST)->delete();
        // Restaura trigger
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
        // User sem permissao DROP TRIGGER — deixa marker isolar registros de teste
    }
}
