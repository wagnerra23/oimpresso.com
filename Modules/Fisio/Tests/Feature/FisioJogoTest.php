<?php

declare(strict_types=1);
// Cobre UC-FPP-14..UC-FPP-18 (Fisio/Public/Portal.casos.md).

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Services\JogoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Jogo (ADR 0437 §6, T-08) pelo caminho real: o check-in do portal é quem lança no
 * `fisio_game_ledger` (append-only, razão em enum fechado). XP e nível são DERIVADOS do
 * ledger; sequência é DERIVADA dos check-ins. A regra pura de sequência e nível está nos Unit
 * (SequenciaCalculatorTest, NivelCalculatorTest); aqui se prova a integração e a idempotência.
 *
 * Decisões que estes testes FIXAM (o implementador não escolhe outra sem mudar o teste):
 *  - XP do exercício: config('fisio.xp.exercicio_concluido') por vez feita, até o
 *    `times_per_day` do item. Vez a mais não dá XP ("nada por exceder o prescrito").
 *  - XP do registro do dia: config('fisio.xp.checkin'), 1x por dia, INCLUSIVE com dor alta.
 *  - Reenvio no mesmo dia: lança só a diferença positiva; nada é estornado (append-only).
 *
 * Tenant 98. ⚠️ SKIP em SQLite (trigger append-only é MySQL) (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 19:00:00');
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

function fjgCheckin($t, array $itens, array $vezes = [], ?int $dor = 2, array $redFlags = [])
{
    return $t->postJson('/fisio/p/'.$t->token.'/checkin', [
        'done_item_ids' => $itens, 'vezes' => (object) $vezes, 'pain' => $dor, 'effort' => 'adequado', 'red_flags' => $redFlags,
    ]);
}

it('UC-FPP-14 · XP por exercício feito e pelo registro do dia, com razões do enum fechado', function () {
    fjgCheckin($this, [$this->ponte, $this->resp], [$this->resp => 2])->assertCreated();

    // ponte 1x + respiração 2x = 3 vezes × 10, + 5 do registro do dia.
    expect(F::xp($this->p))->toBe(3 * F::XP_EXERCICIO + F::XP_CHECKIN)
        ->and(DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->distinct()->pluck('reason')->sort()->values()->all())
        ->toBe(['checkin', 'exercicio_concluido'])
        ->and((int) DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->value('business_id'))->toBe($this->b98);
});

it('UC-FPP-15 · além do prescrito não dá XP (vezes acima de times_per_day são ignoradas)', function () {
    fjgCheckin($this, [$this->ponte, $this->resp], [$this->ponte => 5, $this->resp => 9])->assertCreated();

    expect(F::xp($this->p))->toBe((1 + 2) * F::XP_EXERCICIO + F::XP_CHECKIN);
});

it('UC-FPP-15 · item fora do programa é recusado e não lança XP', function () {
    $alheio = F::itens(F::programa(F::paciente($this->b98, $this->u98->id), [F::exercicio(null)], $this->u98->id))[0];

    fjgCheckin($this, [$this->ponte, $alheio])->assertStatus(422);

    expect(F::xp($this->p))->toBe(0);
});

it('UC-FPP-16 · reenvio no mesmo dia não duplica XP; só a diferença positiva entra; nada é estornado', function () {
    fjgCheckin($this, [$this->ponte])->assertCreated();
    $primeiro = F::xp($this->p);
    expect($primeiro)->toBe(F::XP_EXERCICIO + F::XP_CHECKIN);

    fjgCheckin($this, [$this->ponte])->assertOk();
    expect(F::xp($this->p))->toBe($primeiro);

    fjgCheckin($this, [$this->ponte, $this->resp], [$this->resp => 1])->assertOk();
    expect(F::xp($this->p))->toBe($primeiro + F::XP_EXERCICIO);

    fjgCheckin($this, [])->assertOk(); // desmarcou tudo: o XP ganho fica (ledger append-only)
    expect(F::xp($this->p))->toBe($primeiro + F::XP_EXERCICIO)
        ->and((int) DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->min('xp'))->toBeGreaterThan(0);
});

it('UC-FPP-16 · registrar o dia com dor alta também dá o XP do registro', function () {
    fjgCheckin($this, [], [], 9)->assertCreated();

    expect(F::xp($this->p))->toBe(F::XP_CHECKIN);
});

it('UC-FPP-14 · ledger é append-only no banco e reason fora do enum é recusada', function () {
    fjgCheckin($this, [$this->ponte])->assertCreated();
    $id = DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->value('id');

    expect(fn () => DB::table('fisio_game_ledger')->where('id', $id)->update(['xp' => 9999]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_game_ledger')->where('id', $id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_game_ledger')->insert(['business_id' => $this->b98, 'patient_id' => $this->p->id,
            'date' => '2026-10-14', 'reason' => 'bonus_do_dono', 'xp' => 1000, 'created_at' => now()]))->toThrow(QueryException::class);
});

it('UC-FPP-17 · nível é derivado do total de XP e aparece no portal', function () {
    config(['fisio.niveis' => [1 => 0, 2 => 20, 3 => 60]]);

    fjgCheckin($this, [$this->ponte, $this->resp], [$this->resp => 2])->assertCreated(); // 35 XP

    $jogo = F::portalProps($this, $this->token)['jogo'];
    expect($jogo['xp'])->toBe(35)
        ->and($jogo['nivel'])->toBe(2)
        ->and($jogo['nivel'])->toBe(app(JogoService::class)->nivel($this->p))
        ->and($jogo)->not->toHaveKey('ranking');
});

it('UC-FPP-18 · sequência conta dias prescritos: descanso dentro da folga não quebra', function () {
    // sessions_per_week=3 → folga de até 2 dias seguidos sem registro (ver SequenciaCalculatorTest).
    foreach (['2026-10-06', '2026-10-08', '2026-10-10', '2026-10-12'] as $d) {
        F::checkin($this->p, $this->prog, $d, [$this->ponte], 2);
    }
    fjgCheckin($this, [$this->ponte])->assertCreated(); // 14/10

    expect(F::portalProps($this, $this->token)['jogo']['sequencia'])->toBe(5);
});

it('UC-FPP-18 · dia com dor alta ou sinal de alerta não quebra a sequência (pausa, não falta)', function () {
    foreach (['2026-10-10', '2026-10-12'] as $d) {
        F::checkin($this->p, $this->prog, $d, [$this->ponte], 2);
    }
    fjgCheckin($this, [], [], 9)->assertCreated(); // 14/10: dor 9, nada feito

    $jogo = F::portalProps($this, $this->token)['jogo'];
    expect($jogo['sequencia'])->toBe(2)            // não soma o dia de pausa…
        ->and($jogo['em_pausa'])->toBeTrue();      // …mas também não zera

    // Controle negativo: um buraco maior que a folga zera.
    Carbon::setTestNow('2026-10-18 19:00:00');
    fjgCheckin($this, [$this->ponte])->assertCreated();
    expect(F::portalProps($this, $this->token)['jogo']['sequencia'])->toBe(1);
});
