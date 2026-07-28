<?php
// @covers-us US-INFRA-002

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\VozDoCliente\Entities\Sinal;

uses(Tests\TestCase::class);

/**
 * Tier 0 ({@see ADR 0093}) — isolamento multi-tenant do sinal de Voz do Cliente.
 *
 * Contrato defendido (não derivado do código): `memory/proibicoes.md`
 * §"Multi-tenant Tier 0 IRREVOGÁVEL" — toda Model que toca dado de negócio tem
 * global scope de `business_id`. Um sinal relatado no business A NUNCA pode
 * aparecer pra quem está logado no business B.
 *
 * biz=1 e biz=99 de propósito ({@see ADR 0101}): nunca biz=4, que é cliente real
 * (ROTA LIVRE) — teste não encosta em dado de cliente.
 *
 * Estratégia de tabela sintética sqlite-friendly (espelha
 * CoworkHandoffCrossTenantTest / IngestHeartbeatTest): monta a tabela sob demanda,
 * sem RefreshDatabase, pra conviver com o MySQL persistente do nightly.
 */
function ensureVozSinaisTable(): void
{
    if (Schema::hasTable('voz_sinais')) {
        Schema::drop('voz_sinais');
    }

    Schema::create('voz_sinais', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('user_id')->nullable();
        $t->string('autor_nome', 120)->nullable();
        $t->string('canal', 24)->default('sistema');
        $t->text('texto');
        $t->unsignedTinyInteger('severidade')->nullable();
        $t->string('url_vista', 500)->nullable();
        $t->string('modulo_sugerido', 40)->nullable();
        $t->string('status', 16)->default('pending');
        $t->string('triado_para_us', 40)->nullable();
        $t->unsignedInteger('triado_por')->nullable();
        $t->char('hash_origem', 64);
        $t->timestamp('created_at')->nullable();
        $t->timestamp('triado_em')->nullable();
        $t->unique(['business_id', 'hash_origem'], 'voz_sinais_biz_hash_uq');
    });
}

function criarSinal(int $businessId, string $texto, string $status = 'pending'): Sinal
{
    return Sinal::withoutGlobalScope('business_id')->create([
        // SUPERADMIN: setup de fixture precisa semear os DOIS businesses; é o
        // próprio isolamento que está sob teste, então o scope não pode filtrar
        // a semeadura — senão o teste provaria a si mesmo.
        'business_id' => $businessId,
        'texto'       => $texto,
        'status'      => $status,
        'canal'       => 'sistema',
        'hash_origem' => Sinal::hashDe($businessId, $texto),
        'created_at'  => now(),
    ]);
}

beforeEach(function () {
    ensureVozSinaisTable();
    session()->flush();
});

it('nao vaza sinal de um business para a sessao de outro', function () {
    criarSinal(1, 'Tela de vendas travou ao salvar');
    criarSinal(99, 'Relatorio de estoque veio zerado');

    session(['user.business_id' => 1]);
    $doBusiness1 = Sinal::all();

    expect($doBusiness1)->toHaveCount(1)
        ->and($doBusiness1->first()->business_id)->toBe(1)
        ->and($doBusiness1->first()->texto)->toBe('Tela de vendas travou ao salvar');

    session(['user.business_id' => 99]);
    $doBusiness99 = Sinal::all();

    expect($doBusiness99)->toHaveCount(1)
        ->and($doBusiness99->first()->business_id)->toBe(99)
        ->and($doBusiness99->first()->texto)->toBe('Relatorio de estoque veio zerado');
});

it('nao encontra por id um sinal que pertence a outro business', function () {
    $alheio = criarSinal(99, 'Sinal do vizinho');

    session(['user.business_id' => 1]);

    expect(Sinal::find($alheio->id))->toBeNull();
});

it('conta apenas os pendentes do proprio business', function () {
    criarSinal(1, 'Primeiro problema');
    criarSinal(1, 'Segundo problema');
    criarSinal(1, 'Ja resolvido', 'closed');
    criarSinal(99, 'Problema do vizinho');

    session(['user.business_id' => 1]);

    expect(Sinal::pendentes()->count())->toBe(2);
});

it('trata o mesmo relato do mesmo business como um sinal so', function () {
    $hashA = Sinal::hashDe(1, 'A tela  TRAVOU  ao salvar ');
    $hashB = Sinal::hashDe(1, 'a tela travou ao salvar');

    // Normaliza caixa e espaço: impaciência de quem clica duas vezes não pode
    // virar duas linhas na caixa de triagem.
    expect($hashA)->toBe($hashB);
});

it('nao confunde o mesmo texto vindo de businesses diferentes', function () {
    expect(Sinal::hashDe(1, 'Tela travou'))
        ->not->toBe(Sinal::hashDe(99, 'Tela travou'));
});
