<?php

declare(strict_types=1);

use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Competencia;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do domínio do fechamento — tabela `ponto_competencias` (ADR 0413 W1).
 *
 * A regra vem da ADR, não do código: a competência fechada é GRAVADA UMA VEZ.
 *  - "Reabrir" não existe na v1 (ADR 0413 D1) → UPDATE proibido;
 *  - correção pós-fechamento é anulação com trilha (Portaria MTP 671/2021) → DELETE proibido;
 *  - unicidade (business_id, mês) — um empregador fecha um mês uma vez só.
 * Defesa dupla: model (vale em qualquer driver) + trigger MySQL (vale até pra `DB::table`).
 *
 * Tier 0 (ADR 0093): tenant fictício 98 × adversário 99 (ADR 0358). NUNCA biz=4, nunca biz=1.
 * Cada caso roda numa transação REVERTIDA: a tabela recusa DELETE por construção, então a
 * limpeza por `delete` é impossível — e no CT 100 a base persiste entre runs (§5 2026-09-18).
 * Mês 2099-xx: fora de qualquer competência real.
 */

const COMP_BIZ = 98;

function compPrecondicoes(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('Schema UltimatePOS + FK exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_competencias')) {
        test()->markTestSkipped('Tabela ponto_competencias ausente — migrations do Ponto não rodaram.');
    }
    if (! DB::table('business')->where('id', COMP_BIZ)->exists()) {
        test()->markTestSkipped('Tenant fictício 98 ausente — seed do pest-mysql-setup não rodou.');
    }
}

function compUsuario(int $bizId): int
{
    $id = DB::table('users')->where('business_id', $bizId)->value('id');
    if (! $id) {
        test()->markTestSkipped("Nenhum user no biz {$bizId}.");
    }

    return (int) $id;
}

function compGravar(int $bizId, string $mes): Competencia
{
    return Competencia::forceCreate([
        'business_id'       => $bizId,
        'competencia'       => $mes,
        'fechada_por'       => compUsuario(COMP_BIZ),
        'fechada_em'        => now(),
        'bloqueios_aceitos' => [['tipo' => 'DIVERGENCIA', 'colaborador' => 'teste']],
    ]);
}

/**
 * Afirma que o MODEL barrou — não o trigger. `QueryException` também é RuntimeException,
 * então um `toThrow(RuntimeException)` passaria só pelo trigger: medido no CT 100 em
 * 2026-09-25, o caso ficava verde com o guard do model removido (defesa dupla mascara).
 */
function compModelBarra(callable $acao): void
{
    try {
        $acao();
    } catch (QueryException $e) {
        test()->fail('Quem barrou foi o banco (trigger), não o model: ' . $e->getMessage());
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('ADR 0413 D1');

        return;
    }
    test()->fail('Nenhuma exceção: a competência fechada foi alterada.');
}

beforeEach(function () {
    compPrecondicoes();
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('grava a competência fechada com autor, momento e bloqueios aceitos (ADR 0413 D2 + W3)', function () {
    $c = compGravar(COMP_BIZ, '2099-01-01');

    $linha = DB::table('ponto_competencias')->where('id', $c->id)->first();

    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe(COMP_BIZ);
    expect((int) $linha->fechada_por)->toBe(compUsuario(COMP_BIZ));
    expect($linha->fechada_em)->not->toBeNull();
    expect(json_decode($linha->bloqueios_aceitos, true))->toHaveCount(1);
});

it('UC-PTF-04: update() e save() em competência fechada lançam — não existe reabrir (ADR 0413 D1)', function () {
    $c = compGravar(COMP_BIZ, '2099-02-01');

    compModelBarra(fn () => $c->update(['bloqueios_aceitos' => []]));

    // save() em registro existente chama performUpdate() direto — escaparia de um
    // override só de update() (lição US-PONTO-011, BancoHorasMovimento).
    $c->fechada_em = now()->addDay();
    compModelBarra(fn () => $c->save());

    $this->assertDatabaseHas('ponto_competencias', ['id' => $c->id, 'competencia' => '2099-02-01']);
});

it('UC-PTF-04: delete() em competência fechada lança (ADR 0413 D1)', function () {
    $c = compGravar(COMP_BIZ, '2099-03-01');

    compModelBarra(fn () => $c->delete());
    $this->assertDatabaseHas('ponto_competencias', ['id' => $c->id]);
});

it('trigger MySQL recusa UPDATE e DELETE mesmo por DB::table (defesa sem o model)', function () {
    $c = compGravar(COMP_BIZ, '2099-04-01');

    expect(fn () => DB::table('ponto_competencias')->where('id', $c->id)->update(['fechada_em' => now()]))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('ponto_competencias')->where('id', $c->id)->delete())
        ->toThrow(QueryException::class);

    expect(DB::table('ponto_competencias')->where('id', $c->id)->exists())->toBeTrue();
});

it('o mesmo empregador não fecha o mesmo mês duas vezes (unique business_id + competencia)', function () {
    compGravar(COMP_BIZ, '2099-05-01');

    expect(fn () => compGravar(COMP_BIZ, '2099-05-01'))->toThrow(QueryException::class);
});

it('competência do tenant 98 não aparece para o tenant 99 (Tier 0 — ADR 0093)', function () {
    $c = compGravar(COMP_BIZ, '2099-06-01');

    $alheio = $this->garantirBizAlheio();
    $userAlheio = User::query()->where('business_id', '!=', COMP_BIZ)->first()
        ?? User::query()->first();
    session(['user.business_id' => $alheio]);
    $this->actingAs($userAlheio);

    expect(Competencia::query()->whereKey($c->id)->exists())->toBeFalse();

    session(['user.business_id' => COMP_BIZ]);
    expect(Competencia::query()->whereKey($c->id)->exists())->toBeTrue();
});
