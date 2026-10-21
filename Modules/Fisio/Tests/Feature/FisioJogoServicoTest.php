<?php

declare(strict_types=1);
// @covers-us US-FISIO-008
// Cobre UC-FJG-01, UC-FJG-02, UC-FJG-03, UC-FJG-04, UC-FJG-05 (memory/requisitos/Fisio/_telas/jogo-constancia.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Events\CheckinRegistrado;
use Modules\Fisio\Services\JogoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Jogo de constância pelo PONTO DE ENTRADA ÚNICO (US-FISIO-008): o evento `CheckinRegistrado`
 * → listener `LancarXpDoCheckin` → `JogoService` → `fisio_game_ledger`. Não depende do portal
 * (a rota do check-in é da US-FISIO-007): o check-in nasce pela fixture de histórico e o teste
 * dispara o mesmo evento que o controller do portal vai disparar. O caminho HTTP completo fica
 * no FisioJogoTest (UC-FPP-14..18), que entra na lane com o portal.
 *
 * Números (FisioFixtures::configurar): 60 XP de exercício e 30 do registro, base de
 * normalização 3 com meta 3x/semana (fator 1). Hoje = quarta 14/10/2026 (semana 12..18).
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 19:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 3]);
    $this->prog = F::programa($this->p, [
        F::exercicio(null, 'Ponte', ['times_per_day' => 1]),
        F::exercicio(null, 'Respiração diafragmática', ['times_per_day' => 2]),
    ], $this->u98->id, ['starts_at' => '2026-10-01']);
    [$this->ponte, $this->resp] = F::itens($this->prog);
});

afterEach(fn () => Carbon::setTestNow());

/** Grava (ou regrava) o check-in do dia e dispara o evento, como o controller do portal fará. */
function fjsRegistrar($t, string $dia, array $feitos, array $vezes = [], ?int $dor = 2, array $flags = [], bool $crise = false): Checkin
{
    // SUPERADMIN: fixture de check-in do paciente da 98 (o portal não tem sessão de usuário).
    $c = Checkin::withoutGlobalScopes()->updateOrCreate(
        ['patient_id' => $t->p->id, 'date' => $dia],
        ['business_id' => $t->b98, 'program_id' => $t->prog->id, 'done_item_ids' => $feitos, 'pain' => $dor,
            'effort' => 'adequado', 'red_flags' => $flags, 'crisis' => $crise],
    );
    event(new CheckinRegistrado($t->b98, (int) $c->id, $vezes));

    return $c;
}

function fjsLedger($t): array
{
    return DB::table('fisio_game_ledger')->where('patient_id', $t->p->id)->orderBy('id')->get(['date', 'reason', 'xp'])
        ->map(fn ($l) => [substr((string) $l->date, 0, 10), $l->reason, (int) $l->xp])->all();
}

it('UC-FJG-01 · dia cumprido inteiro: exercício proporcional ao prescrito + registro do dia', function () {
    fjsRegistrar($this, '2026-10-14', [$this->ponte, $this->resp], [$this->resp => 2]);

    expect(F::xp($this->p))->toBe(F::XP_EXERCICIO + F::XP_CHECKIN)
        ->and(collect(fjsLedger($this))->pluck(1)->sort()->values()->all())->toBe(['checkin', 'exercicio_concluido'])
        ->and((int) DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->value('business_id'))->toBe($this->b98);
});

it('UC-FJG-01 · além do prescrito não dá XP: vezes a mais e item fora do programa valem 0', function () {
    $alheio = F::itens(F::programa(F::paciente($this->b98, $this->u98->id), [F::exercicio(null)], $this->u98->id))[0];

    fjsRegistrar($this, '2026-10-14', [$this->ponte, $this->resp, $alheio], [$this->ponte => 5, $this->resp => 9, $alheio => 4]);

    expect(F::xp($this->p))->toBe(F::XP_EXERCICIO + F::XP_CHECKIN);
});

it('UC-FJG-01 · registrar o dia com dor alta e sem fazer nada dá o XP do registro', function () {
    fjsRegistrar($this, '2026-10-14', [], [], 9);

    expect(fjsLedger($this))->toBe([['2026-10-14', 'checkin', F::XP_CHECKIN]]);
});

it('UC-FJG-02 · reenvio só lança a diferença positiva; desmarcar não estorna (ledger append-only)', function () {
    fjsRegistrar($this, '2026-10-14', [$this->ponte]);                          // 1 de 3 vezes
    $primeiro = F::xp($this->p);
    expect($primeiro)->toBe(intdiv(F::XP_EXERCICIO, 3) + F::XP_CHECKIN);

    fjsRegistrar($this, '2026-10-14', [$this->ponte]);                          // mesmo relato
    expect(F::xp($this->p))->toBe($primeiro);

    fjsRegistrar($this, '2026-10-14', [$this->ponte, $this->resp], [$this->resp => 1]); // 2 de 3
    expect(F::xp($this->p))->toBe(intdiv(2 * F::XP_EXERCICIO, 3) + F::XP_CHECKIN);

    fjsRegistrar($this, '2026-10-14', []);                                      // desmarcou tudo
    expect(F::xp($this->p))->toBe(intdiv(2 * F::XP_EXERCICIO, 3) + F::XP_CHECKIN)
        ->and((int) DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->min('xp'))->toBeGreaterThan(0)
        ->and(DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->count())->toBe(3);
});

it('UC-FJG-02 · teto semanal: o que passa do teto não entra; a semana seguinte recomeça', function () {
    config(['fisio.jogo.teto_semanal' => 150]);

    fjsRegistrar($this, '2026-10-12', [$this->ponte, $this->resp], [$this->resp => 2]); // 90
    fjsRegistrar($this, '2026-10-14', [$this->ponte, $this->resp], [$this->resp => 2]); // só 60 cabem
    expect(F::xp($this->p))->toBe(150)
        ->and(fjsLedger($this)[2])->toBe(['2026-10-14', 'checkin', 30]);           // o registro entra antes

    Carbon::setTestNow('2026-10-19 19:00:00');                                       // segunda seguinte
    fjsRegistrar($this, '2026-10-19', [$this->ponte, $this->resp], [$this->resp => 2]);
    expect(F::xp($this->p))->toBe(240);
});

it('UC-FJG-03 · dor alta pausa o jogo sem quebrar a sequência; dia de crise conta como cumprido', function () {
    $jogo = app(JogoService::class);
    fjsRegistrar($this, '2026-10-10', [$this->ponte]);
    fjsRegistrar($this, '2026-10-12', [], [], 5, [], true);                   // crise: versão do profissional
    fjsRegistrar($this, '2026-10-14', [], [], 9);                             // dor 9, nada feito

    $estado = $jogo->estado($this->p);
    expect($estado['sequencia'])->toBe(2)                                      // 10 + crise do 12; o 14 não soma…
        ->and($estado['em_pausa'])->toBeTrue()                                 // …mas pausa, e não zera
        ->and($estado['dias_cumpridos'])->toBe(2)
        ->and($estado)->not->toHaveKey('ranking');

    // A clínica é sinalizada pelo mesmo limiar: o paciente aparece na fila de atenção.
    $fila = collect(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'atencao'))->keyBy('patient_id');
    expect($fila[$this->p->id]['motivos'])->toContain('dor_alta');
});

it('UC-FJG-04 · programa sobre_basal: pausa pela dor acima da base da própria paciente, não pelo número absoluto', function () {
    DB::table('fisio_programs')->where('id', $this->prog->id)->update(['pain_alert_mode' => 'sobre_basal']);
    foreach (['2026-10-01' => 5, '2026-10-03' => 6, '2026-10-05' => 5, '2026-10-06' => 6] as $d => $dor) {
        F::checkin($this->p, $this->prog, $d, [$this->ponte], $dor);           // 1ª semana: base 5,5
    }
    $jogo = app(JogoService::class);

    fjsRegistrar($this, '2026-10-14', [$this->ponte], [], 7);                  // 7 < 5,5 + 2 → segue o jogo
    expect($jogo->estado($this->p)['em_pausa'])->toBeFalse()
        ->and(collect(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'atencao'))->keyBy('patient_id')->get($this->p->id)['motivos'] ?? [])
        ->not->toContain('dor_alta');

    fjsRegistrar($this, '2026-10-14', [$this->ponte], [], 8);                  // 8 ≥ 7,5 → pausa e sinaliza
    expect($jogo->estado($this->p)['em_pausa'])->toBeTrue()
        ->and(collect(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'atencao'))->keyBy('patient_id')[$this->p->id]['motivos'])
        ->toContain('dor_alta');

    // Controle negativo: o mesmo 7 num programa absoluto já pausa.
    DB::table('fisio_programs')->where('id', $this->prog->id)->update(['pain_alert_mode' => 'absoluto']);
    fjsRegistrar($this, '2026-10-14', [$this->ponte], [], 7);
    expect($jogo->estado($this->p)['em_pausa'])->toBeTrue();
});

it('UC-FJG-05 · nível derivado do ledger pela tabela da config, sem coluna editável', function () {
    config(['fisio.jogo.niveis' => [1 => 0, 2 => 50, 3 => 120]]);
    $jogo = app(JogoService::class);

    fjsRegistrar($this, '2026-10-14', [$this->ponte, $this->resp], [$this->resp => 2]); // 90

    expect($jogo->nivel($this->p))->toBe(2)
        ->and($jogo->estado($this->p))->toMatchArray(['xp' => 90, 'nivel' => 2, 'xp_no_nivel' => 40, 'xp_para_proximo' => 30])
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('fisio_patients', 'xp'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('fisio_patients', 'nivel'))->toBeFalse();
});

it('UC-FJG-05 · evento com business de outra clínica ou paciente bloqueado não lança nada', function () {
    $b99 = (int) $this->seededSupportClientTenant()->id;
    // SUPERADMIN: fixture do check-in da 98.
    $c = Checkin::withoutGlobalScopes()->create(['business_id' => $this->b98, 'patient_id' => $this->p->id, 'program_id' => $this->prog->id,
        'date' => '2026-10-14', 'done_item_ids' => [$this->ponte], 'pain' => 2, 'red_flags' => []]);

    event(new CheckinRegistrado($b99, (int) $c->id));
    expect(F::xp($this->p))->toBe(0);

    DB::table('fisio_patients')->where('id', $this->p->id)->update(['blocked_at' => now()]);
    event(new CheckinRegistrado($this->b98, (int) $c->id));
    expect(F::xp($this->p))->toBe(0);

    // Controle positivo: desbloqueado, o mesmo evento lança.
    DB::table('fisio_patients')->where('id', $this->p->id)->update(['blocked_at' => null]);
    event(new CheckinRegistrado($this->b98, (int) $c->id));
    expect(F::xp($this->p))->toBe(intdiv(F::XP_EXERCICIO, 3) + F::XP_CHECKIN);
});