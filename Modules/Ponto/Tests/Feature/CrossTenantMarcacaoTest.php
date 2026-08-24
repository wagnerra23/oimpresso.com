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

    // DELTA, nao valor absoluto (corrigido 2026-08-24).
    //
    // O assert era `expect($totalBiz99)->toBe(0)`. Ele passava por ACIDENTE: os casos
    // biz=99 deste arquivo skipavam (fixture ausente), entao ninguem nunca inseria la com
    // este marcador. Ao criar a fixture do tenant ficticio, o caso do bulk passou a inserir
    // 4 — e como o marcador e COMPARTILHADO entre os casos do arquivo, este aqui passou a
    // enxergar sobra dependendo da ORDEM (Pest roda em ordem aleatoria). Reprovou no CI
    // com `4 is identical to 0` e passou no CT100 na mesma rodada: a marca de flaky.
    //
    // O que a isolacao de fato afirma nao e "biz=99 esta zerado" — e "inserir em biz=1 nao
    // aumenta biz=99". Medir o DELTA diz exatamente isso, e e imune a ordem e a sobra.
    $biz99Antes = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_FICTICIO)
        ->where('ip', CTM_MARCADOR)
        ->count();

    // Insere 5 marcacoes biz=1
    for ($i = 0; $i < 5; $i++) {
        ctmInsertMarcacao(CTM_BIZ_WAGNER, $colab);
    }

    $totalBiz99 = DB::table('ponto_marcacoes')
        ->where('business_id', CTM_BIZ_FICTICIO)
        ->where('ip', CTM_MARCADOR)
        ->count();

    expect($totalBiz99)->toBe($biz99Antes);

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

/**
 * Colaborador do tenant — CRIA a fixture quando falta, em vez de skipar.
 *
 * ── POR QUE MUDOU (2026-08-24) ──────────────────────────────────────────────
 * Antes este helper so LIA. Sem linha pra biz=99, os dois cenarios reversos
 * (`biz=99 nao vaza pra biz=1` e o bulk bidirecional) chamavam markTestSkipped —
 * e skip sai com exit 0. Ou seja: DOIS guards cross-tenant Tier 0 (ADR 0093)
 * anunciavam protecao que nunca foi exercida. Medido no CT100 em 2026-08-23:
 * `2 skipped` num arquivo que ja estava fora de qualquer lane — invisivel duas vezes.
 *
 * Criar a fixture e o mesmo padrao do irmao `Wave27CrossTenantEscalaTest`, que RODA
 * na lane e passa. As FKs de `ponto_colaborador_config` sao `business_id → business`
 * e `user_id → users`, entao o stub sobe os tres na ordem. `forceCreate` guardado por
 * existencia — nunca sobrescreve dado real.
 *
 * biz=99 e a convencao de tenant ficticio deste arquivo (nunca biz=4, que e cliente).
 */
function ctmEnsureColab(int $businessId, bool $optional = false): ?int
{
    $row = DB::table('ponto_colaborador_config')
        ->where('business_id', $businessId)
        ->first();

    if ($row) {
        return (int) $row->id;
    }

    // So fabricamos o tenant FICTICIO. Ausencia de colaborador no tenant REAL e
    // ambiente mal semeado — ali skipar continua sendo a resposta honesta.
    if ($businessId !== CTM_BIZ_FICTICIO) {
        if ($optional) {
            return null;
        }
        test()->markTestSkipped("Sem ponto_colaborador_config seedado pra biz={$businessId}.");
    }

    return ctmStubColabFicticio();
}

/** Sobe business + user + colaborador do tenant ficticio, na ordem das FKs. Idempotente. */
function ctmStubColabFicticio(): int
{
    if (! App\Business::find(CTM_BIZ_FICTICIO)) {
        App\Business::forceCreate([
            'id' => CTM_BIZ_FICTICIO,
            'name' => 'CTM Test Biz Adversario#99',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => 1,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }

    $user = App\User::where('business_id', CTM_BIZ_FICTICIO)->first();
    if (! $user) {
        $user = App\User::forceCreate([
            'business_id' => CTM_BIZ_FICTICIO,
            'surname'     => 'CTM',
            'first_name'  => 'Adversario99',
            'username'    => 'ctm_adversario_99',
            'email'       => 'ctm-adversario-99@example.invalid',
            'password'    => bcrypt(Str::uuid()->toString()),
            'language'    => 'pt_BR',
            'user_type'   => 'user',
        ]);
    }

    return (int) DB::table('ponto_colaborador_config')->insertGetId([
        'business_id'     => CTM_BIZ_FICTICIO,
        'user_id'         => $user->id,
        'controla_ponto'  => 1,
        'usa_banco_horas' => 0,
        'admissao'        => now()->toDateString(),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
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
