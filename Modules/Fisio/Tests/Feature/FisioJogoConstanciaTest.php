<?php

declare(strict_types=1);
// @covers-us US-FISIO-008
// Cobre UC-FJG-06, UC-FJG-07, UC-FJG-08, UC-FJG-09 (memory/requisitos/Fisio/_telas/jogo-constancia.casos.md)
// e UC-FPP-33, UC-FPP-34 (Fisio/Public/Portal.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Missões, desafio semanal e medalhas (T-08c) pelo caminho real: o check-in do portal dispara
 * `CheckinRegistrado`, e só o JogoService lança no ledger. Fixtures: missão 10, desafio 40, bônus
 * semanal 100, meta 3x/semana. Segunda 12/10/2026 (semana 12..18). Tenant 98 contra 99.
 * ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.jogo.xp.missao' => 10]);
    Carbon::setTestNow('2026-10-12 10:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 3]);
    $this->prog = F::programa($this->p, [
        F::exercicio(null, 'Ponte', ['times_per_day' => 1]),
        F::exercicio(null, 'Respiração diafragmática', ['times_per_day' => 2]),
    ], $this->u98->id);
    [$this->ponte, $this->resp] = F::itens($this->prog);
    $this->token = F::portalPronto($this, $this->p);
});

afterEach(fn () => Carbon::setTestNow());

function fjcCheckin($t, array $itens, array $vezes = [], ?int $dor = 2)
{
    return $t->postJson('/fisio/p/'.$t->token.'/checkin', [
        'done_item_ids' => $itens, 'vezes' => (object) $vezes, 'pain' => $dor, 'effort' => 'adequado',
    ]);
}

function fjcLedger($t, string $razao): array
{
    return DB::table('fisio_game_ledger')->where('patient_id', $t->p->id)->where('reason', $razao)->orderBy('id')
        ->get(['ref', 'xp', 'business_id'])->map(fn ($l) => [(string) $l->ref, (int) $l->xp, (int) $l->business_id])->all();
}

it('UC-FJG-06 · as missões de hoje vêm do programa prescrito e não mudam ao recarregar', function () {
    $c = F::portalProps($this, $this->token)['constancia'];
    $permitidas = ['registro', "exercicio:{$this->ponte}", "exercicio:{$this->resp}", "series:{$this->resp}"];

    expect($c['missoes'])->toHaveCount(3)
        ->and(array_diff(array_column($c['missoes'], 'chave'), $permitidas))->toBe([])
        ->and(array_column(F::portalProps($this, $this->token)['constancia']['missoes'], 'chave'))->toBe(array_column($c['missoes'], 'chave'))
        ->and(collect($c['missoes'])->every(fn ($m) => $m['cumprida'] === false))->toBeTrue();
});

it('UC-FJG-07 · missão cumprida lança +10 uma vez; o reenvio não lança de novo', function () {
    fjcCheckin($this, [$this->ponte, $this->resp], [$this->resp => 2])->assertCreated();
    $missoes = F::portalProps($this, $this->token)['constancia']['missoes'];

    expect(collect($missoes)->every(fn ($m) => $m['cumprida']))->toBeTrue()
        ->and(fjcLedger($this, 'missao'))->toHaveCount(3)
        ->and(array_sum(array_column(fjcLedger($this, 'missao'), 1)))->toBe(30)
        ->and(collect(fjcLedger($this, 'missao'))->every(fn ($l) => $l[2] === $this->b98))->toBeTrue();

    fjcCheckin($this, [$this->ponte, $this->resp], [$this->resp => 2])->assertOk();
    expect(fjcLedger($this, 'missao'))->toHaveCount(3);
});

it('UC-FJG-07 · missões param no teto de bônus da semana (a missão cumprida fica registrada com 0)', function () {
    config(['fisio.jogo.teto_semanal_bonus' => 20]);
    fjcCheckin($this, [$this->ponte, $this->resp], [$this->resp => 2])->assertCreated();

    expect(array_sum(array_column(fjcLedger($this, 'missao'), 1)))->toBe(20)
        ->and(fjcLedger($this, 'missao'))->toHaveCount(3);
});

it('UC-FJG-09 · UC-FPP-34 · recusar o desafio não tira XP nem lança nada; a semana só se decide uma vez', function () {
    fjcCheckin($this, [$this->ponte])->assertCreated();
    $xpAntes = F::xp($this->p);
    $d = F::portalProps($this, $this->token)['constancia']['desafio'];
    expect($d)->toMatchArray(['oferecido' => true, 'decidido' => false, 'opcoes' => [2, 3]]);

    $this->postJson("/fisio/p/{$this->token}/desafio", ['aceitar' => false])->assertOk()
        ->assertJsonPath('constancia.desafio.decidido', true)->assertJsonPath('constancia.desafio.aceito', false);

    expect(F::xp($this->p))->toBe($xpAntes)
        ->and(fjcLedger($this, 'desafio_semanal'))->toBe([]);
    $this->postJson("/fisio/p/{$this->token}/desafio", ['aceitar' => true, 'meta' => 2])->assertStatus(409);
});

it('UC-FJG-09 · UC-FPP-34 · desafio aceito e batido paga +40 uma vez; meta fora das opções é recusada', function () {
    $this->postJson("/fisio/p/{$this->token}/desafio", ['aceitar' => true, 'meta' => 1])->assertStatus(422);
    $this->postJson("/fisio/p/{$this->token}/desafio", ['aceitar' => true, 'meta' => 2])->assertOk();

    fjcCheckin($this, [$this->ponte])->assertCreated();
    expect(fjcLedger($this, 'desafio_semanal'))->toBe([]);

    Carbon::setTestNow('2026-10-13 10:00:00');
    fjcCheckin($this, [$this->ponte])->assertCreated();
    Carbon::setTestNow('2026-10-14 10:00:00');
    fjcCheckin($this, [$this->ponte])->assertCreated();

    expect(fjcLedger($this, 'desafio_semanal'))->toBe([['2026-10-12', 40, $this->b98]])
        ->and(F::portalProps($this, $this->token)['constancia']['desafio'])->toMatchArray(['aceito' => true, 'meta' => 2, 'cumpridos' => 3, 'pago' => true, 'oferecido' => false]);
});

it('UC-FJG-09 · fora da segunda não há oferta', function () {
    Carbon::setTestNow('2026-10-14 10:00:00');

    expect(F::portalProps($this, $this->token)['constancia']['desafio']['oferecido'])->toBeFalse();
    $this->postJson("/fisio/p/{$this->token}/desafio", ['aceitar' => false])->assertStatus(409);
    expect(DB::table('fisio_game_desafios')->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FJG-08 · UC-FPP-33 · o primeiro registro dá a primeira medalha, que aparece no portal', function () {
    expect(F::portalProps($this, $this->token)['constancia']['medalhas'])->toBe([]);

    fjcCheckin($this, [$this->ponte])->assertCreated();

    expect(F::portalProps($this, $this->token)['constancia']['medalhas'])->toBe([['codigo' => 'primeiro_registro', 'nome' => 'Primeiro registro']]);
});

it('UC-FJG-09 · decisão de desafio é append-only no banco', function () {
    $this->postJson("/fisio/p/{$this->token}/desafio", ['aceitar' => false])->assertOk();
    $id = (int) DB::table('fisio_game_desafios')->where('patient_id', $this->p->id)->value('id');

    expect(fn () => DB::table('fisio_game_desafios')->where('id', $id)->update(['accepted' => true]))->toThrow(Illuminate\Database\QueryException::class)
        ->and(fn () => DB::table('fisio_game_desafios')->where('id', $id)->delete())->toThrow(Illuminate\Database\QueryException::class);
});

it('UC-FPP-33 · no dia de pausa o portal não mostra missões, desafio nem medalhas', function () {
    fjcCheckin($this, [], [], 8)->assertCreated()->assertJsonMissingPath('constancia');
    $props = F::portalProps($this, $this->token);

    expect($props['modo'])->toBe('pausa')
        ->and($props)->not->toHaveKey('constancia');
});
