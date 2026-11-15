<?php

declare(strict_types=1);
// @covers-us US-FISIO-011
// Cobre UC-FPS-13, UC-FPS-14, UC-FPS-15, UC-FPS-16, UC-FPS-39, UC-FPS-40, UC-FPS-41 (Fisio/Pacientes/Show.casos.md).

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

/*
 * Etapa 07 (P-03) — questionários de fibromialgia atrás de `fisio.instrumentos_liberados`
 * (ADR 0437 §8: sem licença confirmada, não entra). F::configurar() deixa a lista vazia, como
 * em produção; os casos abaixo ligam o que precisam.
 */
function fqFiqr(int $valor): array
{
    $itens = [];
    for ($n = 1; $n <= 21; $n++) {
        $itens[(string) $n] = $valor;
    }

    return ['instrumento' => 'fiqr', 'respostas' => ['itens' => $itens]];
}

it('UC-FPS-39 · desligado: o instrumento de fibromialgia não aparece na ficha e não aceita resposta', function () {
    $url = "/fisio/pacientes/{$this->p->id}/questionarios";
    foreach (['fiqr' => fqFiqr(5), 'pgic' => ['instrumento' => 'pgic', 'respostas' => ['valor' => 3]], 'sentar_levantar_30s' => ['instrumento' => 'sentar_levantar_30s', 'respostas' => ['valor' => 12]]] as $corpo) {
        $this->postJson($url, $corpo)->assertStatus(422)->assertJsonValidationErrors('instrumento');
    }

    $codigos = collect(F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'instrumentos'))->pluck('codigo')->all();
    expect($codigos)->toBe(['eva', 'rmdq', 'psfs', 'lefs', 'spadi'])
        ->and(Questionnaire::query()->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FPS-39 UC-FPS-40 · ligado só o FIQR: ele aparece e grava com a versão; os outros seguem recusados', function () {
    config(['fisio.instrumentos_liberados' => ['fiqr']]);
    $url = "/fisio/pacientes/{$this->p->id}/questionarios";

    $id = $this->postJson($url, fqFiqr(10) + ['versao' => 'inventada'])->assertCreated()->json('id');
    $q = Questionnaire::query()->findOrFail($id);
    expect($q->instrument)->toBe('fiqr')
        ->and((float) $q->score)->toBe(100.0)
        ->and($q->instrument_version)->toBe('fiqr-br-lupi-2017')               // do catálogo, não do corpo
        ->and($q->answers['versao_instrumento'])->toBe('fiqr-br-lupi-2017')
        ->and($q->answers['detalhe'])->toEqual(['funcao' => 30, 'impacto' => 20, 'sintomas' => 50]);

    $this->postJson($url, ['instrumento' => 'pgic', 'respostas' => ['valor' => 3]])->assertStatus(422)->assertJsonValidationErrors('instrumento');

    $codigos = collect(F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'instrumentos'))->pluck('codigo')->all();
    expect($codigos)->toBe(['eva', 'rmdq', 'psfs', 'lefs', 'spadi', 'fiqr']);

    $hist = collect(F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'questionarios'));
    expect($hist->first()['versao'])->toBe('fiqr-br-lupi-2017');
});

it('UC-FPS-41 · ACR 2016 ligado: grava WPI, SSS e o resultado dos critérios como informação', function () {
    config(['fisio.instrumentos_liberados' => ['acr2016']]);
    $itens = [];
    for ($n = 1; $n <= 26; $n++) {
        $itens[(string) $n] = 0;
    }
    foreach ([2, 6, 10, 13, 15, 16, 17] as $n) {   // 7 áreas, 5 regiões
        $itens[(string) $n] = 1;
    }
    $itens['20'] = 2;
    $itens['21'] = 2;
    $itens['22'] = 1;                               // SSS 5
    $itens['26'] = 1;                               // há 3 meses ou mais

    $id = $this->postJson("/fisio/pacientes/{$this->p->id}/questionarios", ['instrumento' => 'acr2016', 'respostas' => ['itens' => $itens]])
        ->assertCreated()->json('id');
    $q = Questionnaire::query()->findOrFail($id);
    expect((float) $q->score)->toBe(12.0)
        ->and($q->instrument_version)->toBe('acr2016-fsq-brasil-2020')
        ->and((int) $q->answers['detalhe']['wpi'])->toBe(7)
        ->and((int) $q->answers['detalhe']['sss'])->toBe(5)
        ->and((int) $q->answers['detalhe']['criterios_acr2016_atendidos'])->toBe(1);
});
