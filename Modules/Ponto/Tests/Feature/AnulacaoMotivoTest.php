<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MarcacaoService;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Motivo da anulação guardado em TEXTO — decisão [W] 2026-09-29 (thread 06 do playbook Ponto).
 *
 * Antes o Marcacao::anular() gravava só um md5 do motivo no `dispositivo_id`. Agora a marcação de
 * anulação carrega o texto em `motivo_anulacao`, no INSERT. A original não muda (append-only,
 * Portaria MTP 671/2021 — os triggers barram UPDATE/DELETE).
 *
 * Tier 0: tenant fictício 98 (ADR 0358). Transação revertida por caso.
 */

const AMO_BIZ = 98;

function amoMarcacao(): Marcacao
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'AMO teste', 'username' => 'amo_' . uniqid(), 'password' => 'x',
        'business_id' => AMO_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $colab = (int) DB::table('ponto_colaborador_config')->insertGetId([
        'business_id' => AMO_BIZ, 'user_id' => $userId, 'matricula' => 'AMO-' . uniqid(),
        'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return app(MarcacaoService::class)->registrar([
        'business_id' => AMO_BIZ, 'colaborador_config_id' => $colab, 'rep_id' => null,
        'origem' => Marcacao::ORIGEM_MANUAL, 'tipo' => Marcacao::TIPO_ENTRADA, 'usuario_criador_id' => $userId,
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! DB::table('business')->where('id', AMO_BIZ)->exists()) {
        $this->markTestSkipped('Schema do Ponto ou tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('a coluna motivo_anulacao existe em ponto_marcacoes (migration rodou)', function () {
    expect(Schema::hasColumn('ponto_marcacoes', 'motivo_anulacao'))->toBeTrue();
});

it('anular grava o motivo em TEXTO na marcação de anulação; a original fica igual e sem motivo', function () {
    $original = amoMarcacao();
    $antes = (array) DB::table('ponto_marcacoes')->where('id', $original->id)->first();
    $motivo = 'Marcação duplicada por falha de sinal — texto neutro de teste.';

    $anulacao = $original->anular($original->usuario_criador_id, $motivo);

    $linha = DB::table('ponto_marcacoes')->where('id', $anulacao->id)->first();
    expect($linha->origem)->toBe(Marcacao::ORIGEM_ANULACAO);
    expect($linha->marcacao_anulada_id)->toBe((string) $original->id);
    expect($linha->motivo_anulacao)->toBe($motivo);

    expect((array) DB::table('ponto_marcacoes')->where('id', $original->id)->first())->toBe($antes);
    expect($antes['motivo_anulacao'])->toBeNull();
});
