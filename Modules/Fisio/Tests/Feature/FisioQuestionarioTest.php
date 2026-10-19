<?php

declare(strict_types=1);
// @covers-us US-FISIO-011
// Cobre UC-FPS-13, UC-FPS-14, UC-FPS-15, UC-FPS-16 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Modules\Fisio\Entities\Questionnaire;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Questionários de uso livre na ficha (US-FISIO-011; ADR 0437 §8): o profissional transcreve as
 * respostas, o SERVIDOR calcula a pontuação, e o histórico aparece na ficha como prop deferida.
 * Bloqueados por licença (ODI, NDI, KOOS, QuickDASH, WOMAC) não entram.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite: schema MySQL do UltimatePOS (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_questionnaires'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-18 10:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    F::como($this, $this->u98);
    $this->p = F::paciente($this->b98, $this->u98->id);
});

afterEach(fn () => Carbon::setTestNow());

function fqRmdq(array $sim): array
{
    $itens = [];
    for ($n = 1; $n <= 24; $n++) {
        $itens[(string) $n] = in_array($n, $sim, true) ? 1 : 0;
    }

    return ['instrumento' => 'rmdq', 'respostas' => ['itens' => $itens]];
}

it('UC-FPS-13 · grava a resposta com a pontuação calculada no servidor (score do corpo é ignorado)', function () {
    $r = $this->postJson("/fisio/pacientes/{$this->p->id}/questionarios", fqRmdq([2, 4, 6, 8, 10]) + ['score' => 24, 'business_id' => 99999])
        ->assertCreated();

    $q = Questionnaire::query()->findOrFail($r->json('id'));
    expect($q->instrument)->toBe('rmdq')
        ->and((float) $q->score)->toBe(5.0)                    // 5 "sim", não os 24 do corpo
        ->and((int) $q->business_id)->toBe($this->b98)          // da sessão, não do corpo
        ->and((int) $q->patient_id)->toBe((int) $this->p->id)
        ->and($q->answered_at->toDateTimeString())->toBe('2026-10-18 10:00:00')
        ->and($q->answers['respostas']['itens']['2'])->toBe(1)
        ->and((int) $q->answers['registrado_por'])->toBe((int) $this->u98->id);
});

it('UC-FPS-13 · SPADI grava total e subescalas', function () {
    $itens = [];
    for ($n = 1; $n <= 13; $n++) {
        $itens[(string) $n] = $n <= 5 ? 10 : 0;
    }
    $id = $this->postJson("/fisio/pacientes/{$this->p->id}/questionarios", ['instrumento' => 'spadi', 'respostas' => ['itens' => $itens]])
        ->assertCreated()->json('id');

    $q = Questionnaire::query()->findOrFail($id);
    // dor 50/50 = 100%; incapacidade 0%; total 50/130 = 38,46%
    expect((float) $q->score)->toBe(38.46)
        ->and($q->answers['detalhe'])->toEqual(['dor' => 100, 'incapacidade' => 0]); // JSON devolve 100.0 como int
});

it('UC-FPS-14 · bloqueado por licença, desconhecido ou incompleto: 422 e nada grava', function () {
    $url = "/fisio/pacientes/{$this->p->id}/questionarios";
    $this->postJson($url, ['instrumento' => 'odi', 'respostas' => ['itens' => [1 => 1]]])->assertStatus(422)->assertJsonValidationErrors('instrumento');
    $this->postJson($url, ['instrumento' => 'womac', 'respostas' => ['valor' => 1]])->assertStatus(422)->assertJsonValidationErrors('instrumento');
    $this->postJson($url, ['instrumento' => 'xpto', 'respostas' => ['valor' => 1]])->assertStatus(422)->assertJsonValidationErrors('instrumento');

    $incompleto = fqRmdq([1]);
    unset($incompleto['respostas']['itens']['24']);
    $this->postJson($url, $incompleto)->assertStatus(422)->assertJsonValidationErrors('respostas.itens.24');
    $this->postJson($url, ['instrumento' => 'eva', 'respostas' => ['valor' => 12]])->assertStatus(422)->assertJsonValidationErrors('respostas.valor');

    expect(Questionnaire::query()->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FPS-15 · sem fisio.editar: 403; paciente bloqueado: 422; nada grava', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);
    $this->postJson("/fisio/pacientes/{$this->p->id}/questionarios", ['instrumento' => 'eva', 'respostas' => ['valor' => 3]])->assertForbidden();

    F::como($this, $this->u98);
    $bloqueado = F::paciente($this->b98, $this->u98->id, ['blocked_at' => now()]);
    $this->postJson("/fisio/pacientes/{$bloqueado->id}/questionarios", ['instrumento' => 'eva', 'respostas' => ['valor' => 3]])
        ->assertStatus(422)->assertJsonValidationErrors('instrumento');

    expect(Questionnaire::query()->whereIn('patient_id', [$this->p->id, $bloqueado->id])->count())->toBe(0);
});

it('UC-FPS-15 · paciente de outra empresa: 404 e nada grava na 99', function () {
    $u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::trocarPacote((int) $u99->business_id, true);
    $p99 = F::paciente((int) $u99->business_id, $u99->id);

    F::como($this, $this->u98);
    $this->postJson("/fisio/pacientes/{$p99->id}/questionarios", ['instrumento' => 'eva', 'respostas' => ['valor' => 3]])->assertNotFound();

    // SUPERADMIN: conferência cross-tenant — o escopo da sessão 98 esconderia a linha da 99.
    expect(Questionnaire::withoutGlobalScopes()->where('patient_id', $p99->id)->count())->toBe(0);
});

it('UC-FPS-16 · a ficha mostra o histórico (prop deferida), mais recente primeiro, só deste paciente e desta empresa', function () {
    $url = "/fisio/pacientes/{$this->p->id}/questionarios";
    $antigo = $this->postJson($url, ['instrumento' => 'eva', 'respostas' => ['valor' => 8]])->assertCreated()->json('id');
    Carbon::setTestNow('2026-10-18 11:00:00');
    $novo = $this->postJson($url, fqRmdq([1, 2]))->assertCreated()->json('id');

    $outro = F::paciente($this->b98, $this->u98->id);
    $this->postJson("/fisio/pacientes/{$outro->id}/questionarios", ['instrumento' => 'eva', 'respostas' => ['valor' => 1]])->assertCreated();

    $u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::trocarPacote((int) $u99->business_id, true);
    $p99 = F::paciente((int) $u99->business_id, $u99->id);
    F::como($this, $u99);
    $this->postJson("/fisio/pacientes/{$p99->id}/questionarios", ['instrumento' => 'eva', 'respostas' => ['valor' => 2]])->assertCreated();

    $hist = collect(F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'questionarios'));
    expect($hist->pluck('id')->map('intval')->all())->toBe([(int) $novo, (int) $antigo])
        ->and($hist->first()['instrumento'])->toBe('rmdq')
        ->and((float) $hist->first()['score'])->toBe(2.0)
        ->and((float) $hist->last()['score'])->toBe(8.0);
});

it('UC-FPS-16 · o histórico é deferido e o catálogo vai com referências e sem os bloqueados', function () {
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get("/fisio/pacientes/{$this->p->id}", ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao])
        ->assertOk();

    expect($r->json('props'))->not->toHaveKey('questionarios')                       // deferido
        ->and($r->json('deferredProps.default'))->toContain('questionarios');

    $codigos = collect($r->json('props.instrumentos'))->pluck('codigo')->all();
    expect($codigos)->toBe(['eva', 'rmdq', 'psfs', 'lefs', 'spadi'])
        ->and(collect($r->json('props.instrumentos'))->every(fn ($i) => count($i['referencias']) > 0))->toBeTrue();
});
