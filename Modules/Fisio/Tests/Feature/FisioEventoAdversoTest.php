<?php

declare(strict_types=1);
// Etapa 07 P-06 · eventos adversos do estudo (UC-FES-09..11, Fisio/Pesquisa/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\StudyAdverseEvent;
use Modules\Fisio\Events\CheckinRegistrado;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O check-in de quem está no estudo vira evento adverso: crise por 3 dias seguidos (mais de
 * 48 h) e dor no limiar em vigor. O evento é disparado pelo MESMO evento de domínio que o portal
 * dispara (CheckinRegistrado); a fixture só grava o check-in. Não há gravidade no registro.
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_studies', 'fisio_study_participants', 'fisio_study_adverse_events'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-11-01 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->pesq = $this->usuarioComPermissoes([F::PERM_ACESSO, 'fisio.pesquisa'], $this->seededTenant());
    $this->pesq99 = $this->usuarioComPermissoes([F::PERM_ACESSO, 'fisio.pesquisa'], $this->seededSupportClientTenant());
    $svc = app(EstudoService::class);
    $dados = ['title' => 'Fibro', 'cep_opinion_number' => '1', 'protocol_version' => 'p1', 'tcle_version' => 't1', 'starts_on' => '2026-11-01'];
    $this->estudo = $svc->criar($this->b98, $dados, [['label' => 'Entrada', 'day_offset' => 0, 'window_days' => 3, 'instruments' => ['eva']]], null);
    $this->p = F::paciente($this->b98, $this->pesq->id);
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->pesq->id);
    $this->fora = F::paciente($this->b98, $this->pesq->id);
    $this->progFora = F::programa($this->fora, [F::exercicio(null, 'Ponte')], $this->pesq->id);
    $svc->incluir($this->estudo, $this->p, null, null);
});

afterEach(fn () => Carbon::setTestNow());

function feaCheckin($t, $p, $prog, string $dia, ?int $dor = null, bool $crise = false): void
{
    $c = F::checkin($p, $prog, $dia, [], $dor);
    DB::table('fisio_checkins')->where('id', $c->id)->update(['crisis' => $crise]);
    event(new CheckinRegistrado((int) $p->business_id, (int) $c->id));
}

function feaEventos(int $pacienteId): \Illuminate\Support\Collection
{
    return DB::table('fisio_study_adverse_events as e')->join('fisio_study_participants as p', 'p.id', '=', 'e.participant_id')
        ->where('p.patient_id', $pacienteId)->orderBy('e.id')->get(['e.*']);
}

it('UC-FES-09 · crise por 3 dias seguidos vira um evento que acompanha a sequência; 2 dias não', function () {
    feaCheckin($this, $this->p, $this->prog, '2026-11-02', 4, true);
    feaCheckin($this, $this->p, $this->prog, '2026-11-03', 4, true);
    expect(feaEventos($this->p->id))->toHaveCount(0);

    feaCheckin($this, $this->p, $this->prog, '2026-11-04', 4, true);
    feaCheckin($this, $this->p, $this->prog, '2026-11-05', 4, true);
    $ev = feaEventos($this->p->id);
    expect($ev)->toHaveCount(1)
        ->and($ev[0]->origin)->toBe(StudyAdverseEvent::ORIGEM_CRISE)
        ->and($ev[0]->started_on)->toBe('2026-11-02')
        ->and($ev[0]->ended_on)->toBe('2026-11-05')
        ->and(json_decode($ev[0]->detail, true))->toBe(['dias' => 4])
        ->and((array) $ev[0])->not->toHaveKey('severity');

    // Paciente fora do estudo não gera evento, mesmo em crise.
    foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $d) {
        feaCheckin($this, $this->fora, $this->progFora, $d, 4, true);
    }
    expect(DB::table('fisio_study_adverse_events')->count())->toBe(1);
});

it('UC-FES-10 · dor no limiar vira alerta_dor uma vez só; abaixo dele, nada', function () {
    feaCheckin($this, $this->p, $this->prog, '2026-11-02', 5);
    expect(feaEventos($this->p->id))->toHaveCount(0);

    feaCheckin($this, $this->p, $this->prog, '2026-11-03', 8);
    $c = DB::table('fisio_checkins')->where('patient_id', $this->p->id)->where('date', '2026-11-03')->value('id');
    event(new CheckinRegistrado($this->b98, (int) $c)); // reenvio do mesmo check-in
    $ev = feaEventos($this->p->id);
    expect($ev)->toHaveCount(1)
        ->and($ev[0]->origin)->toBe(StudyAdverseEvent::ORIGEM_ALERTA_DOR)
        ->and(json_decode($ev[0]->detail, true))->toBe(['dor' => 8, 'limiar' => 7]);
});

it('UC-FES-11 · a pesquisadora revisa com nota; evento de outra clínica dá 404 e não aparece [T0]', function () {
    feaCheckin($this, $this->p, $this->prog, '2026-11-03', 9);
    $id = (int) feaEventos($this->p->id)[0]->id;

    // Evento na 99.
    $e99 = app(EstudoService::class)->criar($this->b99, ['title' => 'X', 'cep_opinion_number' => '1', 'protocol_version' => 'p1', 'tcle_version' => 't1', 'starts_on' => '2026-11-01'], [], null);
    $p99 = F::paciente($this->b99, $this->pesq99->id);
    $prog99 = F::programa($p99, [F::exercicio(null, 'Ponte')], $this->pesq99->id);
    app(EstudoService::class)->incluir($e99, $p99, null, null);
    feaCheckin($this, $p99, $prog99, '2026-11-03', 9);
    $id99 = (int) feaEventos($p99->id)[0]->id;

    F::como($this, $this->pesq);
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/eventos/{$id}/revisar", ['nota' => ''])->assertStatus(422);
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/eventos/{$id}/revisar", ['nota' => 'Comunicado ao CEP em 04/11'])->assertOk();
    $this->postJson("/fisio/pesquisa/estudos/{$e99->id}/eventos/{$id99}/revisar", ['nota' => 'x'])->assertNotFound();

    $linha = DB::table('fisio_study_adverse_events')->where('id', $id)->first();
    $tela = F::props($this, $this->pesq, '/fisio/pesquisa', 'Fisio/Pesquisa/Index', 'eventos');
    expect($linha->review_note)->toBe('Comunicado ao CEP em 04/11')
        ->and((int) $linha->reviewed_by)->toBe($this->pesq->id)
        ->and(DB::table('fisio_study_adverse_events')->where('id', $id99)->value('reviewed_at'))->toBeNull()
        ->and(collect($tela)->pluck('id')->all())->toBe([$id]);
});
