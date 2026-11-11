<?php

declare(strict_types=1);
// Etapa 07 P-04 · estudo, participante, momentos e pendências (UC-FES-01..06).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Entities\StudyParticipant;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Estudo de pesquisa da clínica (etapa 07 P-04, ADR 0437 §7). As rotas exigem `fisio.pesquisa`.
 * O que se mede (código, aceite, pendência, saída) sai do código sob teste; a fixture só monta
 * estudo, paciente e respostas. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_studies', 'fisio_study_moments', 'fisio_study_participants'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-11-01 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->pesq98 = $this->usuarioComPermissoes([F::PERM_ACESSO, 'fisio.pesquisa'], $this->seededTenant());
    $this->clin98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->pesq99 = $this->usuarioComPermissoes([F::PERM_ACESSO, 'fisio.pesquisa'], $this->seededSupportClientTenant());
    $this->p = F::paciente($this->b98, $this->clin98->id, ['main_complaint' => 'Dor difusa']);
    $this->p99 = F::paciente($this->b99, $this->pesq99->id);
    $this->svc = app(EstudoService::class);
});

afterEach(fn () => Carbon::setTestNow());

function fesPayload(): array
{
    return [
        'title' => 'Fibromialgia e exercício', 'cep_opinion_number' => '7.123.456', 'protocol_version' => 'p1',
        'tcle_version' => 'tcle-1', 'starts_on' => '2026-11-01',
        'momentos' => [
            ['label' => 'Entrada', 'day_offset' => 0, 'window_days' => 3, 'instruments' => ['eva']],
            ['label' => '4 semanas', 'day_offset' => 28, 'window_days' => 7, 'instruments' => ['eva', 'rmdq']],
        ],
    ];
}

function fesCriar($t, $user): Study
{
    F::como($t, $user);
    $id = $t->postJson('/fisio/pesquisa/estudos', fesPayload())->assertCreated()->json('id');

    return Study::withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: leitura do teste.
}

function fesResposta(int $biz, int $pacienteId, string $instr, string $quando): void
{
    DB::table('fisio_questionnaires')->insert(['business_id' => $biz, 'patient_id' => $pacienteId, 'instrument' => $instr,
        'answers' => '{}', 'score' => 1, 'answered_at' => $quando, 'created_at' => now(), 'updated_at' => now()]);
}

it('UC-FES-01 · só o pesquisador cria estudo; momentos guardados em ordem; instrumento fora da lista é recusado', function () {
    F::como($this, $this->clin98);
    $this->postJson('/fisio/pesquisa/estudos', fesPayload())->assertForbidden();

    $estudo = fesCriar($this, $this->pesq98);
    expect($estudo->business_id)->toBe($this->b98)
        ->and($estudo->status)->toBe(Study::STATUS_ATIVO)
        ->and($estudo->moments()->withoutGlobalScopes()->pluck('label')->all())->toBe(['Entrada', '4 semanas']);

    $ruim = fesPayload();
    $ruim['momentos'][0]['instruments'] = ['odi'];
    $this->postJson('/fisio/pesquisa/estudos', $ruim)->assertStatus(422);
});

it('UC-FES-02 · incluir gera código aleatório sem nome nem CPF e grava o aceite do TCLE do estudo', function () {
    $estudo = fesCriar($this, $this->pesq98);
    $this->postJson("/fisio/pesquisa/estudos/{$estudo->id}/participantes", ['patient_id' => $this->p->id])->assertStatus(422);
    $r = $this->postJson("/fisio/pesquisa/estudos/{$estudo->id}/participantes", ['patient_id' => $this->p->id, 'tcle_assinado' => true])
        ->assertCreated();

    $code = (string) $r->json('code');
    $c = DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'pesquisa')->first();
    expect($code)->toMatch('/^P-[ACDEFGHJKMNPQRTUVWXY3479]{6}$/')
        ->and($c->study_id)->toBe($estudo->id)
        ->and($c->version)->toBe('tcle-1')
        ->and($c->channel)->toBe('clinica');

    // Incluir de novo não duplica.
    $this->postJson("/fisio/pesquisa/estudos/{$estudo->id}/participantes", ['patient_id' => $this->p->id, 'tcle_assinado' => true])
        ->assertStatus(422);
    expect(StudyParticipant::withoutGlobalScopes()->where('study_id', $estudo->id)->count())->toBe(1);
});

it('UC-FES-03 · saída exige tipo e motivo, uma vez só, e a linha nunca é apagada', function () {
    $estudo = fesCriar($this, $this->pesq98);
    $id = $this->postJson("/fisio/pesquisa/estudos/{$estudo->id}/participantes", ['patient_id' => $this->p->id, 'tcle_assinado' => true])->json('id');
    $url = "/fisio/pesquisa/estudos/{$estudo->id}/participantes/{$id}/saida";

    $this->postJson($url, ['tipo' => 'perda_seguimento'])->assertStatus(422);
    $this->postJson($url, ['tipo' => 'perda_seguimento', 'motivo' => 'Não atende há 5 semanas'])->assertOk()
        ->assertJsonPath('exit_type', 'perda_seguimento');
    $this->postJson($url, ['tipo' => 'outro', 'motivo' => 'de novo'])->assertStatus(422);

    $p = StudyParticipant::withoutGlobalScopes()->findOrFail($id);
    expect($p->exit_reason)->toBe('Não atende há 5 semanas')
        ->and(fn () => $p->delete())->toThrow(DadoClinicoImutavelException::class);
});

it('UC-FES-04 · pendência sai do momento, da entrada e da resposta na janela', function () {
    $estudo = fesCriar($this, $this->pesq98);
    $this->postJson("/fisio/pesquisa/estudos/{$estudo->id}/participantes", ['patient_id' => $this->p->id, 'tcle_assinado' => true]);

    // Entrada aberta até responder a EVA.
    expect(collect($this->svc->pendencias($estudo, Carbon::parse('2026-11-01')))->pluck('situacao', 'moment')->all())
        ->toBe(['Entrada' => 'aberto', '4 semanas' => 'futuro']);
    fesResposta($this->b98, $this->p->id, 'eva', '2026-11-02 10:00:00');

    $hoje = collect($this->svc->pendencias($estudo, Carbon::parse('2026-11-11')));
    expect($hoje->pluck('moment')->all())->toBe(['4 semanas']);

    // 4 semanas = 29/11 ± 7. EVA respondida na janela; RMDQ não. Em 10/12 está vencido.
    fesResposta($this->b98, $this->p->id, 'eva', '2026-11-25 10:00:00');
    fesResposta($this->b98, $this->p->id, 'rmdq', '2026-11-10 10:00:00'); // fora da janela: não conta
    $dez = $this->svc->pendencias($estudo, Carbon::parse('2026-12-10'));
    expect($dez)->toHaveCount(1)
        ->and($dez[0]['situacao'])->toBe('vencido')
        ->and($dez[0]['faltando'])->toBe(['rmdq'])
        ->and([$dez[0]['inicio'], $dez[0]['fim']])->toBe(['2026-11-22', '2026-12-06']);
});

it('UC-FES-05 · revogar a pesquisa pelo portal tira o participante do estudo naquela data', function () {
    $estudo = fesCriar($this, $this->pesq98);
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->clin98->id);
    $this->postJson("/fisio/pesquisa/estudos/{$estudo->id}/participantes", ['patient_id' => $this->p->id, 'tcle_assinado' => true]);

    Carbon::setTestNow('2026-11-20 09:00:00');
    $token = F::portalPronto($this, $this->p);
    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/pesquisa/'.$estudo->id)->assertOk();

    $p = StudyParticipant::withoutGlobalScopes()->where('study_id', $estudo->id)->firstOrFail();
    expect($p->exited_on->toDateString())->toBe('2026-11-20')
        ->and($p->exit_type)->toBe(StudyParticipant::SAIDA_REVOGACAO)
        ->and($this->svc->pendencias($estudo, Carbon::parse('2026-11-25')))->toBe([]);
});

it('UC-FES-06 · estudo, paciente e participante de outra clínica respondem 404 [T0]', function () {
    $estudo98 = fesCriar($this, $this->pesq98);
    $this->postJson("/fisio/pesquisa/estudos/{$estudo98->id}/participantes", ['patient_id' => $this->p99->id, 'tcle_assinado' => true])
        ->assertNotFound();

    $estudo99 = fesCriar($this, $this->pesq99);
    $id99 = $this->postJson("/fisio/pesquisa/estudos/{$estudo99->id}/participantes", ['patient_id' => $this->p99->id, 'tcle_assinado' => true])
        ->assertCreated()->json('id');

    F::como($this, $this->pesq98);
    $this->postJson("/fisio/pesquisa/estudos/{$estudo99->id}/participantes", ['patient_id' => $this->p->id, 'tcle_assinado' => true])
        ->assertNotFound();
    $this->postJson("/fisio/pesquisa/estudos/{$estudo99->id}/participantes/{$id99}/saida", ['tipo' => 'outro', 'motivo' => 'x'])
        ->assertNotFound();
    expect(StudyParticipant::withoutGlobalScopes()->findOrFail($id99)->exited_on)->toBeNull()
        ->and(collect($this->svc->pendencias($estudo98, Carbon::parse('2026-11-01')))->pluck('patient_id')->all())->not->toContain($this->p99->id);
});
