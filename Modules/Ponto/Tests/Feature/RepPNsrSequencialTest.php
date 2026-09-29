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
 * NSR do REP-P sequencial POR COLABORADOR — decisão [W] 2026-09-29 (thread 06 do playbook Ponto).
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

it('fora do escopo: MANUAL sem REP segue o NSR virtual, e a anulação de uma REP-P não consome a sequência', function () {
    $a = rnsColaborador(RNS_BIZ);

    expect((int) rnsMarcar(RNS_BIZ, $a, Marcacao::ORIGEM_MANUAL)->nsr)->toBeGreaterThanOrEqual(NsrService::NSR_LEGADO_MICROTIME);

    $primeira = rnsMarcar(RNS_BIZ, $a);
    $anulacao = $primeira->anular($a[1], 'teste de sequência');
    expect((int) $anulacao->nsr)->toBeGreaterThanOrEqual(NsrService::NSR_LEGADO_MICROTIME);

    expect((int) rnsMarcar(RNS_BIZ, $a)->nsr)->toBe(2);
});
