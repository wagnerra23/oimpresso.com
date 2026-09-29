<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MarcacaoService;
use Modules\Ponto\Services\NsrService;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * NSR sequencial e hash encadeado POR COLABORADOR no REP-P — decisões [W] 2026-09-29 (thread 06).
 *
 * O REP-P não tem REP físico; a sequência sem lacunas da Portaria MTP 671/2021 é contada por
 * (business_id, colaborador) sobre as marcações `REP_P` sem REP. Antes era `microtime`.
 * As outras origens sem REP (MANUAL, INTEGRACAO, ANULACAO) seguem como estavam.
 *
 * Tier 0: tenant fictício 98 × adversário 99 (ADR 0358). Transação revertida por caso
 * (`ponto_marcacoes` recusa DELETE por trigger).
 */

const RNS_BIZ = 98;

function rnsColaborador(int $bizId): array
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'RNS teste', 'username' => 'rns_' . uniqid(), 'password' => 'x',
        'business_id' => $bizId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $colab = (int) DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => $bizId, 'user_id' => $userId, 'matricula' => 'RNS-' . uniqid(),
        'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$colab, $userId];
}

function rnsMarcar(int $bizId, array $colab, string $origem = Marcacao::ORIGEM_REP_P): Marcacao
{
    return app(MarcacaoService::class)->registrar([
        'business_id' => $bizId, 'colaborador_config_id' => $colab[0], 'rep_id' => null,
        'origem' => $origem, 'tipo' => Marcacao::TIPO_ENTRADA, 'dispositivo_id' => 'mobile:rns',
        'usuario_criador_id' => $colab[1],
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! DB::table('business')->where('id', RNS_BIZ)->exists()) {
        $this->markTestSkipped('Schema do Ponto ou tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('REP-P: NSR sai 1, 2, 3 para o mesmo colaborador — sem lacuna', function () {
    $a = rnsColaborador(RNS_BIZ);

    expect([rnsMarcar(RNS_BIZ, $a)->nsr, rnsMarcar(RNS_BIZ, $a)->nsr, rnsMarcar(RNS_BIZ, $a)->nsr])->toBe([1, 2, 3]);
});

it('REP-P: a sequência é de cada colaborador — outro colaborador e outro empregador começam em 1 (Tier 0)', function () {
    $a = rnsColaborador(RNS_BIZ);
    $b = rnsColaborador(RNS_BIZ);
    $alheio = rnsColaborador($this->garantirBizAlheio());

    rnsMarcar(RNS_BIZ, $a);
    rnsMarcar(RNS_BIZ, $a);

    expect((int) rnsMarcar(RNS_BIZ, $b)->nsr)->toBe(1);
    expect((int) rnsMarcar(99, $alheio)->nsr)->toBe(1);
    expect((int) rnsMarcar(RNS_BIZ, $a)->nsr)->toBe(3);
});

it('REP-P: NSR legado de microtime (≥ 10⁹) não entra no max — a sequência nova começa em 1', function () {
    $a = rnsColaborador(RNS_BIZ);
    DB::table('ponto_marcacoes')->insert([
        'id' => (string) Illuminate\Support\Str::uuid(), 'business_id' => RNS_BIZ, 'colaborador_config_id' => $a[0],
        'rep_id' => null, 'nsr' => 1727600000000, 'momento' => now()->subDay(), 'origem' => Marcacao::ORIGEM_REP_P,
        'tipo' => Marcacao::TIPO_ENTRADA, 'hash' => str_repeat('0', 64), 'usuario_criador_id' => $a[1],
    ]);

    expect((int) rnsMarcar(RNS_BIZ, $a)->nsr)->toBe(1);
    expect(NsrService::NSR_LEGADO_MICROTIME)->toBe(1_000_000_000);
});

it('MANUAL sem REP segue o NSR virtual; a anulação de uma REP-P ENTRA na sequência do colaborador ([W] 2026-09-29)', function () {
    $a = rnsColaborador(RNS_BIZ);

    expect((int) rnsMarcar(RNS_BIZ, $a, Marcacao::ORIGEM_MANUAL)->nsr)->toBeGreaterThanOrEqual(NsrService::NSR_LEGADO_MICROTIME);

    $primeira = rnsMarcar(RNS_BIZ, $a);
    $anulacao = $primeira->anular($a[1], 'teste de sequência');
    expect((int) $anulacao->nsr)->toBe(2);

    expect((int) rnsMarcar(RNS_BIZ, $a)->nsr)->toBe(3);
});

it('a anulação de uma MANUAL não entra na sequência REP-P', function () {
    $a = rnsColaborador(RNS_BIZ);
    $manual = rnsMarcar(RNS_BIZ, $a, Marcacao::ORIGEM_MANUAL);

    expect((int) $manual->anular($a[1], 'teste')->nsr)->toBeGreaterThanOrEqual(NsrService::NSR_LEGADO_MICROTIME);
    expect((int) rnsMarcar(RNS_BIZ, $a)->nsr)->toBe(1);
});

// ============================================================================
// Hash encadeado por colaborador no REP-P — decisão [W] 2026-09-29
// ============================================================================

it('REP-P: hash encadeado por colaborador — a 1ª abre a cadeia, cada uma aponta a anterior, e a cadeia confere', function () {
    $a = rnsColaborador(RNS_BIZ);
    $m1 = rnsMarcar(RNS_BIZ, $a);
    $m2 = rnsMarcar(RNS_BIZ, $a);
    $m3 = rnsMarcar(RNS_BIZ, $a);

    expect($m1->hash_anterior)->toBeNull();
    expect($m2->hash_anterior)->toBe($m1->hash);
    expect($m3->hash_anterior)->toBe($m2->hash);
    expect(app(MarcacaoService::class)->verificarIntegridadeRepP(RNS_BIZ, $a[0]))->toBe(['ok' => true, 'quebrados' => []]);
});

it('REP-P: a cadeia é de cada colaborador — colega e outro empregador abrem a própria (Tier 0)', function () {
    $a = rnsColaborador(RNS_BIZ);
    $b = rnsColaborador(RNS_BIZ);
    $alheio = rnsColaborador($this->garantirBizAlheio());
    rnsMarcar(RNS_BIZ, $a);

    expect(rnsMarcar(RNS_BIZ, $b)->hash_anterior)->toBeNull();
    expect(rnsMarcar(99, $alheio)->hash_anterior)->toBeNull();
});

it('REP-P: legado (NSR microtime) fica fora da cadeia; a anulação ENTRA nela ([W] 2026-09-29)', function () {
    $a = rnsColaborador(RNS_BIZ);
    DB::table('ponto_marcacoes')->insert([
        'id' => (string) Illuminate\Support\Str::uuid(), 'business_id' => RNS_BIZ, 'colaborador_config_id' => $a[0],
        'rep_id' => null, 'nsr' => 1727600000000, 'momento' => now()->subDay(), 'origem' => Marcacao::ORIGEM_REP_P,
        'tipo' => Marcacao::TIPO_ENTRADA, 'hash' => str_repeat('a', 64), 'usuario_criador_id' => $a[1],
    ]);

    $m1 = rnsMarcar(RNS_BIZ, $a);
    expect($m1->hash_anterior)->toBeNull();

    $anulacao = $m1->anular($a[1], 'teste de cadeia');
    expect($anulacao->hash_anterior)->toBe($m1->hash);
    expect(rnsMarcar(RNS_BIZ, $a)->hash_anterior)->toBe($anulacao->hash);
    expect(app(MarcacaoService::class)->verificarIntegridadeRepP(RNS_BIZ, $a[0]))->toBe(['ok' => true, 'quebrados' => []]);
});

it('REP-P: a verificação MORDE — uma marcação forjada com hash_anterior errado aparece como quebra', function () {
    $a = rnsColaborador(RNS_BIZ);
    $m1 = rnsMarcar(RNS_BIZ, $a);
    DB::table('ponto_marcacoes')->insert([
        'id' => (string) Illuminate\Support\Str::uuid(), 'business_id' => RNS_BIZ, 'colaborador_config_id' => $a[0],
        'rep_id' => null, 'nsr' => 2, 'momento' => now(), 'origem' => Marcacao::ORIGEM_REP_P,
        'tipo' => Marcacao::TIPO_SAIDA, 'hash_anterior' => str_repeat('f', 64), 'hash' => str_repeat('b', 64),
        'usuario_criador_id' => $a[1],
    ]);

    $r = app(MarcacaoService::class)->verificarIntegridadeRepP(RNS_BIZ, $a[0]);
    expect($r['ok'])->toBeFalse();
    expect(collect($r['quebrados'])->pluck('nsr')->map(fn ($n) => (int) $n)->unique()->values()->all())->toBe([2]);
    expect($m1->hash_anterior)->toBeNull();
});
