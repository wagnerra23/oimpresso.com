<?php

declare(strict_types=1);
// Cobre UC-FPS-06, UC-FPS-08, UC-FPS-09, UC-FPS-10 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Editor de programa (T-06): criar, adicionar item com a dose padrão do exercício, reordenar,
 * editar dose, remover — e o alerta de contraindicação, que AVISA (não bloqueia) e grava que o
 * profissional confirmou. As escritas são JSON (o editor é interativo, como o apontamento).
 *
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    F::como($this, $this->u98);
    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->ponte = F::exercicio(null, 'Ponte', ['default_sets' => 3, 'default_reps' => 12, 'default_hold_s' => 0]);
    $this->prancha = F::exercicio(null, 'Prancha', ['default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 30]);
    $this->gato = F::exercicio(null, 'Gato-camelo', ['default_sets' => 2, 'default_reps' => 10]);
});

function fpeCriar($t, int $pacienteId, array $mudar = []): int
{
    return (int) $t->postJson("/fisio/pacientes/{$pacienteId}/programas", array_merge([
        'phase' => 'fase_1',
        'starts_at' => now()->toDateString(),
        'stop_instruction' => 'Pare, aplique gelo e me avise pelo WhatsApp.',
    ], $mudar))->assertCreated()->json('id');
}

it('UC-FPS-06 · cria programa manual ativo, com created_by da sessão e limiar de dor padrão 7', function () {
    $id = fpeCriar($this, $this->p->id, ['origin' => 'protocolo', 'created_by' => 999]);

    $prog = Program::query()->findOrFail($id);
    expect($prog->status)->toBe('ativo')
        ->and($prog->origin)->toBe('manual')                     // F1: só manual pela tela; protocolo vem por rota própria
        ->and((int) $prog->created_by)->toBe($this->u98->id)
        ->and((int) $prog->business_id)->toBe($this->b98)
        ->and((int) ($prog->pain_alert_threshold ?? config('fisio.pain_alert_threshold_padrao')))->toBe(7)
        ->and($prog->stop_instruction)->toBe('Pare, aplique gelo e me avise pelo WhatsApp.');
});

it('UC-FPS-06 · programa sem instrução de parada é recusado (o paciente precisa do texto do profissional)', function () {
    $this->postJson("/fisio/pacientes/{$this->p->id}/programas", ['phase' => 'fase_1', 'starts_at' => now()->toDateString()])
        ->assertStatus(422)->assertJsonValidationErrors('stop_instruction');
});

it('UC-FPS-06 · novo programa ativo encerra o anterior — só um ativo por paciente', function () {
    $primeiro = fpeCriar($this, $this->p->id);
    $segundo = fpeCriar($this, $this->p->id, ['phase' => 'fase_2']);

    expect(Program::query()->findOrFail($primeiro)->status)->not->toBe('ativo')
        ->and(Program::query()->where('patient_id', $this->p->id)->where('status', 'ativo')->pluck('id')->all())->toBe([$segundo]);
});

it('UC-FPS-08 · adicionar item herda a dose padrão; position vai para o fim', function () {
    $id = fpeCriar($this, $this->p->id);

    $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->ponte->id])->assertCreated();
    $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->prancha->id])->assertCreated();

    $itens = ProgramItem::query()->where('program_id', $id)->orderBy('position')->get();
    expect($itens->pluck('exercise_id')->map('intval')->all())->toBe([$this->ponte->id, $this->prancha->id])
        ->and($itens->pluck('position')->map('intval')->all())->toBe([1, 2])
        ->and([(int) $itens[1]->sets, (int) $itens[1]->reps, (int) $itens[1]->hold_s])->toBe([3, 1, 30]);
});

it('UC-FPS-08 · editar dose valida limites e não toca outro item', function () {
    $id = fpeCriar($this, $this->p->id);
    $a = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->ponte->id])->json('id');
    $b = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->gato->id])->json('id');

    $this->putJson("/fisio/programas/{$id}/itens/{$a}", ['sets' => 4, 'reps' => 8, 'hold_s' => 5, 'times_per_day' => 2, 'notes' => 'Devagar'])->assertOk();
    $this->putJson("/fisio/programas/{$id}/itens/{$a}", ['sets' => 0])->assertStatus(422)->assertJsonValidationErrors('sets');
    $this->putJson("/fisio/programas/{$id}/itens/{$a}", ['reps' => -1])->assertStatus(422)->assertJsonValidationErrors('reps');

    $ia = ProgramItem::query()->findOrFail($a);
    expect([(int) $ia->sets, (int) $ia->reps, (int) $ia->hold_s, (int) $ia->times_per_day, $ia->notes])->toBe([4, 8, 5, 2, 'Devagar'])
        ->and((int) ProgramItem::query()->findOrFail($b)->sets)->toBe(2);
});

it('UC-FPS-09 · reordenar grava a ordem pedida; lista com item alheio ou faltando é recusada', function () {
    $id = fpeCriar($this, $this->p->id);
    $a = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->ponte->id])->json('id');
    $b = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->prancha->id])->json('id');
    $c = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->gato->id])->json('id');

    $this->putJson("/fisio/programas/{$id}/itens/ordem", ['ids' => [$c, $a, $b]])->assertOk();
    expect(ProgramItem::query()->where('program_id', $id)->orderBy('position')->pluck('id')->map('intval')->all())->toBe([$c, $a, $b]);

    // Controle negativo: item de outro programa, ou lista incompleta → 422 e a ordem não muda.
    $outro = fpeCriar($this, F::paciente($this->b98, $this->u98->id)->id);
    $alheio = $this->postJson("/fisio/programas/{$outro}/itens", ['exercise_id' => $this->ponte->id])->json('id');
    $this->putJson("/fisio/programas/{$id}/itens/ordem", ['ids' => [$a, $b, $alheio]])->assertStatus(422);
    $this->putJson("/fisio/programas/{$id}/itens/ordem", ['ids' => [$a, $b]])->assertStatus(422);
    expect(ProgramItem::query()->where('program_id', $id)->orderBy('position')->pluck('id')->map('intval')->all())->toBe([$c, $a, $b]);
});

it('UC-FPS-09 · remover item recompacta as posições e não apaga check-in que já o citou', function () {
    $id = fpeCriar($this, $this->p->id);
    $a = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->ponte->id])->json('id');
    $b = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->prancha->id])->json('id');
    $c = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $this->gato->id])->json('id');
    F::checkin($this->p, Program::query()->find($id), now()->subDay()->toDateString(), [$a, $b], 2);

    $this->deleteJson("/fisio/programas/{$id}/itens/{$b}")->assertOk();

    expect(ProgramItem::query()->where('program_id', $id)->orderBy('position')->get(['id', 'position'])->map(fn ($i) => [(int) $i->id, (int) $i->position])->all())
        ->toBe([[$a, 1], [$c, 2]])
        ->and(DB::table('fisio_checkins')->where('patient_id', $this->p->id)->value('done_item_ids'))->toContain((string) $b);
});

it('UC-FPS-08 · exercício próprio de outro business não entra no programa', function () {
    $id = fpeCriar($this, $this->p->id);
    $da99 = F::exercicio((int) $this->seededSupportClientTenant()->id, 'Secreto da 99');

    $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $da99->id])->assertStatus(422)->assertJsonValidationErrors('exercise_id');
    expect(ProgramItem::query()->where('program_id', $id)->count())->toBe(0);
});

it('UC-FPS-10 · exercício contraindicado para o marcador do paciente AVISA sem bloquear e grava a confirmação', function () {
    $this->p->update(['condition_flags' => ['protese_quadril_posterior', 'hipertensao_nao_controlada']]);
    $flexaoQuadril = F::exercicio(null, 'Flexão de quadril sentado', ['contraindication_flags' => ['protese_quadril_posterior']]);
    $id = fpeCriar($this, $this->p->id);

    // 1ª tentativa sem confirmar: 409 com o aviso, nada gravado (não é bloqueio — é pergunta).
    $r = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $flexaoQuadril->id])
        ->assertStatus(409)->assertJsonPath('erro', 'contraindicacao');
    expect($r->json('flags'))->toBe(['protese_quadril_posterior'])
        ->and(ProgramItem::query()->where('program_id', $id)->count())->toBe(0);

    // Profissional confirma: grava o item e quem/quando confirmou o cruzamento.
    $item = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $flexaoQuadril->id, 'confirmar_contraindicacao' => true])
        ->assertCreated()->json('id');
    $gravado = ProgramItem::query()->findOrFail($item);
    expect($gravado->contraindication_ack_flags)->toBe(['protese_quadril_posterior'])
        ->and((int) $gravado->contraindication_ack_by)->toBe($this->u98->id)
        ->and($gravado->contraindication_ack_at)->not->toBeNull();
});

it('UC-FPS-10 · sem cruzamento de marcadores, adiciona direto e sem confirmação registrada (controle negativo)', function () {
    $this->p->update(['condition_flags' => ['gestante']]);
    $flexaoQuadril = F::exercicio(null, 'Flexão de quadril sentado', ['contraindication_flags' => ['protese_quadril_posterior']]);
    $id = fpeCriar($this, $this->p->id);

    $item = $this->postJson("/fisio/programas/{$id}/itens", ['exercise_id' => $flexaoQuadril->id])->assertCreated()->json('id');

    expect(ProgramItem::query()->findOrFail($item)->contraindication_ack_at)->toBeNull();
});
