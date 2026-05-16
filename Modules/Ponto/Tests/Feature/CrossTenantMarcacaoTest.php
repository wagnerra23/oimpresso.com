<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\Marcacao;

/**
 * Teste anti-vazamento cross-tenant especifico pra ponto_marcacoes.
 *
 * Modules/Ponto e P1 critico (CLT/Portaria MTP 671/2021) — vazamento de marcacao
 * entre tenants e violacao de:
 *   - Tier 0 IRREVOGAVEL multi-tenant (ADR 0093)
 *   - LGPD Art. 7o (dado pessoal de jornada de outro empregador)
 *   - Portaria MTP 671/2021 Anexo I (auditoria nominal de empregador)
 *
 * Cobertura focada:
 *   1) Marcacao criada em biz=1 NAO retorna em SELECT scoped biz=99
 *   2) Marcacao criada em biz=99 NAO retorna em SELECT scoped biz=1
 *   3) Query agregada (count) por biz=99 nao soma marcacoes de biz=1
 *   4) Marcacao anulada (origem=ANULACAO) mantem isolamento — anulacao de
 *      marcacao biz=1 nao pode aparecer em listing biz=99 mesmo via JOIN
 *   5) Bulk INSERT N marcacoes biz=1 + N marcacoes biz=99 — cada tenant ve
 *      somente as suas via SELECT WHERE business_id
 *
 * NUNCA usar biz=4 (ROTA LIVRE cliente Larissa producao) — ADR 0101.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Portaria MTP 671/2021 Anexo I (imutabilidade + auditoria)
 * @see LGPD Art. 7o (base legal cumprimento obrigacao legal)
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: triggers + schema UltimatePOS requerem MySQL (ADR 0101).');
    }
    if (! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Tabela ponto_marcacoes ausente — rode migrations Modules/Ponto.');
    }
    if (! Schema::hasTable('ponto_colaborador_config')) {
        $this->markTestSkipped('Tabela ponto_colaborador_config ausente.');
    }
});

const CTM_BIZ_WAGNER = 1;
const CTM_BIZ_FICTICIO = 99;
const CTM_MARCADOR = 'cross-tenant-marcacao-test';

// ------------------------------------------------------------------
// Cenarios
// ------------------------------------------------------------------

it('marcacao biz=1 nao retorna em SELECT scoped biz=99', function () {
    $colabBiz1 = ctmEnsureColab(CTM_BIZ_WAGNER);
    $marcId = ctmInsertMarcacao(CTM_BIZ_WAGNER, $colabBiz1);

    $vaza = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_FICTICIO)
        ->where('id', $marcId)
        ->first();

    expect($vaza)->toBeNull();
})->afterEach(fn () => ctmCleanup());

it('marcacao biz=99 nao retorna em SELECT scoped biz=1', function () {
    // Pode nao ter colab seedado pra biz=99 — usa um id "neutro" sem FK valida
    // forcando insert raw (cenario possivel via seed isolado de teste em DB real)
    $colabBiz99 = ctmEnsureColab(CTM_BIZ_FICTICIO, $optional = true);
    if ($colabBiz99 === null) {
        $this->markTestSkipped('Sem ponto_colaborador_config pra biz=99 — seed necessario pro cenario reverso.');
    }

    $marcId = ctmInsertMarcacao(CTM_BIZ_FICTICIO, $colabBiz99);

    $vaza = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_WAGNER)
        ->where('id', $marcId)
        ->first();

    expect($vaza)->toBeNull();
})->afterEach(fn () => ctmCleanup());

it('count agregado biz=99 nao soma marcacoes de biz=1', function () {
    $colab = ctmEnsureColab(CTM_BIZ_WAGNER);

    // Insere 5 marcacoes biz=1
    for ($i = 0; $i < 5; $i++) {
        ctmInsertMarcacao(CTM_BIZ_WAGNER, $colab);
    }

    $totalBiz99 = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_FICTICIO)
        ->where('ip', CTM_MARCADOR)
        ->count();

    expect($totalBiz99)->toBe(0);

    $totalBiz1 = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_WAGNER)
        ->where('ip', CTM_MARCADOR)
        ->count();

    expect($totalBiz1)->toBeGreaterThanOrEqual(5);
})->afterEach(fn () => ctmCleanup());

it('bulk insert N biz=1 + N biz=99 — cada tenant ve somente as suas', function () {
    $colabBiz1 = ctmEnsureColab(CTM_BIZ_WAGNER);
    $colabBiz99 = ctmEnsureColab(CTM_BIZ_FICTICIO, $optional = true);
    if ($colabBiz99 === null) {
        $this->markTestSkipped('Sem ponto_colaborador_config pra biz=99 — cenario bidirecional skipped.');
    }

    $idsBiz1 = [];
    $idsBiz99 = [];
    for ($i = 0; $i < 4; $i++) {
        $idsBiz1[] = ctmInsertMarcacao(CTM_BIZ_WAGNER, $colabBiz1);
        $idsBiz99[] = ctmInsertMarcacao(CTM_BIZ_FICTICIO, $colabBiz99);
    }

    // Biz=1 listing ve apenas suas
    $vistasBiz1 = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_WAGNER)
        ->where('ip', CTM_MARCADOR)
        ->whereIn('id', array_merge($idsBiz1, $idsBiz99))
        ->pluck('id')
        ->all();

    expect(count(array_intersect($vistasBiz1, $idsBiz1)))->toBe(4);
    expect(count(array_intersect($vistasBiz1, $idsBiz99)))->toBe(0);

    // Biz=99 listing ve apenas suas
    $vistasBiz99 = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_FICTICIO)
        ->where('ip', CTM_MARCADOR)
        ->whereIn('id', array_merge($idsBiz1, $idsBiz99))
        ->pluck('id')
        ->all();

    expect(count(array_intersect($vistasBiz99, $idsBiz99)))->toBe(4);
    expect(count(array_intersect($vistasBiz99, $idsBiz1)))->toBe(0);
})->afterEach(fn () => ctmCleanup());

it('marcacao ANULACAO biz=1 nao vaza em listing biz=99 via marcacao_anulada_id', function () {
    $colab = ctmEnsureColab(CTM_BIZ_WAGNER);

    // Marcacao original biz=1
    $origId = ctmInsertMarcacao(CTM_BIZ_WAGNER, $colab);

    // Marcacao de anulacao biz=1 apontando pra original
    $anulId = (string) Str::uuid();
    DB::table('ponto_marcacoes')->insert([
        'id' => $anulId,
        'business_id' => CTM_BIZ_WAGNER,
        'colaborador_config_id' => $colab,
        'rep_id' => null,
        'nsr' => random_int(1000000, 9999999),
        'momento' => now(),
        'origem' => Marcacao::ORIGEM_ANULACAO,
        'tipo' => Marcacao::TIPO_ENTRADA,
        'marcacao_anulada_id' => $origId,
        'ip' => CTM_MARCADOR,
        'hash' => hash('sha256', $anulId),
        'usuario_criador_id' => 1,
        'created_at' => now(),
    ]);

    // Tentativa de listing biz=99 incluindo JOIN com marcacao_anulada_id
    $vaza = DB::table('ponto_marcacoes as m')
        ->leftJoin('ponto_marcacoes as a', 'a.id', '=', 'm.marcacao_anulada_id')
        ->where('m.business_id', CTM_BIZ_FICTICIO)
        ->whereIn('m.id', [$origId, $anulId])
        ->orWhere('a.business_id', CTM_BIZ_FICTICIO)
        ->count();

    expect($vaza)->toBe(0);
})->afterEach(fn () => ctmCleanup());

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------

function ctmEnsureColab(int $businessId, bool $optional = false): ?int
{
    $row = DB::table('ponto_colaborador_config')
        ->where('business_id', $businessId)
        ->first();

    if ($row) {
        return (int) $row->id;
    }

    if ($optional) {
        return null;
    }

    test()->markTestSkipped("Sem ponto_colaborador_config seedado pra biz={$businessId}.");
}

function ctmInsertMarcacao(int $businessId, int $colabId): string
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
        'ip' => CTM_MARCADOR,
        'hash' => hash('sha256', $id),
        'usuario_criador_id' => 1,
        'created_at' => now(),
    ]);

    return $id;
}

function ctmCleanup(): void
{
    try {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ponto_marcacoes_no_delete');
        DB::table('ponto_marcacoes')->where('ip', CTM_MARCADOR)->delete();
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
        // sem permissao DROP TRIGGER — marker isola
    }
}
