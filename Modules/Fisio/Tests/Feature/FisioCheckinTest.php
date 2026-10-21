<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-08..UC-FPP-13 (Fisio/Public/Portal.casos.md) e UC-FPN-04 (Fisio/Painel.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Fisio\Notifications\SinalDeAlertaNotification;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * "Como foi hoje" — check-in do portal.
 *
 * - Idempotente por dia: único por paciente+data; reenvio ATUALIZA, não duplica.
 * - Cada check-in gera UM `fisio_records` kind='telemonitoramento' (Res. COFFITO 619/2025)
 *   com modality='assincrona' e channel='portal'.
 *   ESCOLHA DOCUMENTADA (pedida pelo coordenador): reenvio no mesmo dia NÃO duplica o
 *   telemonitoramento — cria um registro kind='adendo' com amends_id apontando o
 *   telemonitoramento do dia. Motivo: o registro original é append-only (assinado pelo
 *   sistema no 1º envio) e o prontuário precisa mostrar que o paciente mudou o relato.
 *   Reenvio IDÊNTICO não cria nada (nem adendo).
 * - Dor fora de 0–10, esforço fora de {facil, adequado, dificil}, item fora do programa:
 *   recusados, nada gravado.
 * - Sinal de alerta: notifica o profissional e entra na fila de atenção NA HORA.
 *
 * Rota pública sem auth. Tenant 98. ⚠️ SKIP em SQLite (LC-13).
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
    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte'), F::exercicio(null, 'Prancha')], $this->u98->id);
    $this->itens = F::itens($this->prog);
    $this->token = F::portalPronto($this, $this->p);
});

afterEach(fn () => Carbon::setTestNow());

function fciEnviar($t, array $mudar = [])
{
    return $t->postJson('/fisio/p/'.$t->token.'/checkin', array_merge([
        'done_item_ids' => $t->itens,
        'pain' => 3,
        'effort' => 'adequado',
        'note' => 'Senti o glúteo trabalhar',
        'red_flags' => [],
    ], $mudar));
}

it('UC-FPP-08 · check-in grava o dia com business, programa, itens, dor e esforço', function () {
    fciEnviar($this)->assertCreated();

    $c = DB::table('fisio_checkins')->where('patient_id', $this->p->id)->first();
    expect((int) $c->business_id)->toBe($this->b98)
        ->and((int) $c->program_id)->toBe($this->prog->id)
        ->and(Carbon::parse($c->date)->toDateString())->toBe('2026-10-14')
        ->and(array_map('intval', json_decode($c->done_item_ids, true)))->toBe($this->itens)
        ->and((int) $c->pain)->toBe(3)
        ->and($c->effort)->toBe('adequado');
});

it('UC-FPP-09 · reenvio no mesmo dia atualiza o check-in, não duplica', function () {
    fciEnviar($this)->assertCreated();
    fciEnviar($this, ['done_item_ids' => [$this->itens[0]], 'pain' => 5, 'effort' => 'dificil'])->assertOk();

    $linhas = DB::table('fisio_checkins')->where('patient_id', $this->p->id)->get();
    expect($linhas)->toHaveCount(1)
        ->and((int) $linhas[0]->pain)->toBe(5)
        ->and($linhas[0]->effort)->toBe('dificil')
        ->and(array_map('intval', json_decode($linhas[0]->done_item_ids, true)))->toBe([$this->itens[0]]);

    // Controle: no dia seguinte é outro check-in.
    Carbon::setTestNow('2026-10-15 08:00:00');
    fciEnviar($this)->assertCreated();
    expect(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(2);
});

it('UC-FPP-09 · a unicidade paciente+data é do banco, não só do controller', function () {
    F::checkin($this->p, $this->prog, '2026-10-10', $this->itens, 2);

    expect(fn () => F::checkin($this->p, $this->prog, '2026-10-10', $this->itens, 4))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('UC-FPP-10 · cada check-in gera 1 telemonitoramento com modalidade e canal; reenvio vira adendo', function () {
    fciEnviar($this)->assertCreated();

    $tele = DB::table('fisio_records')->where('patient_id', $this->p->id)->where('kind', 'telemonitoramento')->get();
    expect($tele)->toHaveCount(1)
        ->and($tele[0]->modality)->toBe('assincrona')
        ->and($tele[0]->channel)->toBe('portal')
        ->and((int) $tele[0]->business_id)->toBe($this->b98)
        ->and($tele[0]->signed_at)->not->toBeNull()
        ->and(json_decode($tele[0]->body, true)['pain'])->toBe(3);

    // Reenvio igual: nada novo no prontuário.
    fciEnviar($this)->assertOk();
    expect(DB::table('fisio_records')->where('patient_id', $this->p->id)->count())->toBe(1);

    // Reenvio com relato diferente: telemonitoramento continua 1; nasce 1 adendo apontando para ele.
    fciEnviar($this, ['pain' => 6])->assertOk();
    $todos = DB::table('fisio_records')->where('patient_id', $this->p->id)->orderBy('id')->get();
    expect($todos->where('kind', 'telemonitoramento'))->toHaveCount(1)
        ->and($todos->where('kind', 'adendo'))->toHaveCount(1)
        ->and((int) $todos->firstWhere('kind', 'adendo')->amends_id)->toBe((int) $tele[0]->id)
        ->and(json_decode($todos->firstWhere('kind', 'telemonitoramento')->body, true)['pain'])->toBe(3); // original intacto
});

dataset('dor invalida', ['-1' => [-1], '11' => [11], '3.5' => [3.5], 'texto' => ['muita']]);

it('UC-FPP-11 · dor fora de 0–10 é recusada e nada é gravado', function ($dor) {
    fciEnviar($this, ['pain' => $dor])->assertStatus(422)->assertJsonValidationErrors('pain');

    expect(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(0)
        ->and(DB::table('fisio_records')->where('patient_id', $this->p->id)->count())->toBe(0)
        ->and(DB::table('fisio_game_ledger')->where('patient_id', $this->p->id)->count())->toBe(0);
})->with('dor invalida');

it('UC-FPP-11 · dor nos limites 0 e 10 e dor ausente são aceitas', function () {
    fciEnviar($this, ['pain' => 0])->assertCreated();
    fciEnviar($this, ['pain' => 10])->assertOk();
    Carbon::setTestNow('2026-10-15 08:00:00');
    fciEnviar($this, ['pain' => null])->assertCreated();

    expect(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->orderBy('date')->pluck('pain')->map(fn ($v) => $v === null ? null : (int) $v)->all())->toBe([10, null]);
});

it('UC-FPP-11 · esforço fora de facil|adequado|dificil é recusado', function () {
    fciEnviar($this, ['effort' => 'medio'])->assertStatus(422)->assertJsonValidationErrors('effort');
    fciEnviar($this, ['effort' => null])->assertStatus(422)->assertJsonValidationErrors('effort');
    expect(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FPP-12 · item que não pertence ao programa ativo é recusado e nada é gravado', function () {
    $outroPaciente = F::paciente($this->b98, $this->u98->id);
    $alheio = F::itens(F::programa($outroPaciente, [F::exercicio(null)], $this->u98->id))[0];

    fciEnviar($this, ['done_item_ids' => [$this->itens[0], $alheio]])->assertStatus(422)->assertJsonValidationErrors('done_item_ids');
    fciEnviar($this, ['done_item_ids' => [999999999]])->assertStatus(422);

    expect(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FPP-12 · item repetido na lista conta uma vez só', function () {
    fciEnviar($this, ['done_item_ids' => [$this->itens[0], $this->itens[0]]])->assertCreated();

    expect(array_map('intval', json_decode((string) DB::table('fisio_checkins')->where('patient_id', $this->p->id)->value('done_item_ids'), true)))
        ->toBe([$this->itens[0]]);
});

it('UC-FPP-13 · sinal de alerta notifica o profissional do programa na hora', function () {
    Notification::fake();

    fciEnviar($this, ['red_flags' => ['dormencia_progressiva'], 'pain' => 4])->assertCreated();

    Notification::assertSentTo($this->u98, SinalDeAlertaNotification::class,
        fn ($n) => $n->patientId === $this->p->id && $n->businessId === $this->b98 && $n->redFlags === ['dormencia_progressiva']);
    expect(json_decode((string) DB::table('fisio_checkins')->where('patient_id', $this->p->id)->value('red_flags'), true))->toBe(['dormencia_progressiva']);
});

it('UC-FPP-13 · sinal fora da lista fechada é recusado; sem sinal, ninguém é notificado', function () {
    Notification::fake();

    fciEnviar($this, ['red_flags' => ['inventado']])->assertStatus(422)->assertJsonValidationErrors('red_flags.0');
    fciEnviar($this, ['red_flags' => []])->assertCreated();

    Notification::assertNothingSent();
});

it('UC-FPN-04 · sinal de alerta coloca o paciente na fila de atenção imediatamente', function () {
    $antes = collect(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'atencao'))->pluck('patient_id');
    expect($antes)->not->toContain($this->p->id);
    auth()->logout();

    fciEnviar($this, ['red_flags' => ['febre'], 'pain' => 1])->assertCreated();

    $fila = collect(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'atencao'));
    $linha = $fila->firstWhere('patient_id', $this->p->id);
    expect($linha)->not->toBeNull()->and($linha['motivos'])->toContain('sinal_de_alerta');
});
