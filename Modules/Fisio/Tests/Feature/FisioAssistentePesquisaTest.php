<?php

declare(strict_types=1);
// Etapa 07 P-08 · assistente de IA da pesquisadora (UC-FES-16..19, Fisio/Pesquisa/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Ai\Agents\AssistentePesquisaAgent;
use Modules\Fisio\Services\Pesquisa\AssistentePesquisa;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Decisão [W] 2026-11-11: "seria um auxílio para pesquisador, ele confirma se quiser". O modelo é
 * falso (Agent::fake) — o que se mede é o portão (desligado por padrão), o que entra no modelo
 * (sem identificação), o registro de quem decide e que nada sai da área do estudo.
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_studies', 'fisio_study_participants', 'fisio_study_ai_suggestions'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-11-01 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->clinica = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR, 'fisio.pesquisa'], $this->seededTenant());
    $this->externo = $this->usuarioComPermissoes([F::PERM_ACESSO, 'fisio.pesquisa'], $this->seededTenant());
    $svc = app(EstudoService::class);
    $dados = ['title' => 'Fibro', 'cep_opinion_number' => '1', 'protocol_version' => 'p1', 'tcle_version' => 't1', 'starts_on' => '2026-11-01'];
    $this->estudo = $svc->criar($this->b98, $dados, [['label' => 'Entrada', 'day_offset' => 0, 'window_days' => 3, 'instruments' => ['eva']]], null);
    $this->e99 = $svc->criar($this->b99, $dados, [], null);
    $contato = F::contato($this->b98, $this->clinica->id, 'Joana Reservada Assistente', '48933334444');
    $this->p = F::paciente($this->b98, $this->clinica->id, ['contact_id' => $contato]);
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->clinica->id);
    $this->part = $svc->incluir($this->estudo, $this->p, null, null);
    F::checkin($this->p, $this->prog, '2026-11-01', [], 6);
    Carbon::setTestNow('2026-11-02 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

$falsas = [['sugestoes' => [
    ['tipo' => 'qualidade', 'texto' => 'P-? sem EVA na entrada.'],
    ['tipo' => 'resumo', 'texto' => '1 participante, 2 dias.'],
    ['tipo' => 'padrao', 'texto' => 'Hipótese: adesão cai antes da crise.'],
    ['tipo' => 'conduta', 'texto' => 'Aumente o exercício.'], // tipo fora da lista: não entra
]]];

it('UC-FES-16 · desligado por padrão; só quem atende liga; desligado, o modelo nem é chamado', function () {
    AssistentePesquisaAgent::fake([])->preventStrayPrompts();
    F::como($this, $this->clinica);
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/sugestoes")->assertStatus(422);
    expect(DB::table('fisio_study_ai_suggestions')->count())->toBe(0);

    F::como($this, $this->externo);
    $this->postJson('/fisio/pesquisa/assistente', ['ligado' => true])->assertForbidden();
    F::como($this, $this->clinica);
    $this->postJson('/fisio/pesquisa/assistente', ['ligado' => true])->assertOk();

    expect(app(AssistentePesquisa::class)->ligado($this->b98))->toBeTrue()
        ->and(app(AssistentePesquisa::class)->ligado($this->b99))->toBeFalse();
});

it('UC-FES-17 · o modelo recebe só o dado sem identificação', function () {
    $dados = app(AssistentePesquisa::class)->dadosParaIa($this->estudo);
    $prompt = (new AssistentePesquisaAgent($dados, 'Fibro'))->montarPrompt();

    expect($prompt)->toContain($this->part->code)
        ->not->toContain('Reservada')->not->toContain('48933334444')->not->toContain('2026-11')
        ->and($dados['diario'][1])->toBe([$this->part->code, '0', '6', '0', '0', '1']);
});

it('UC-FES-18 · sugestões nascem pendentes; aceitar grava quem e quando; tipo fora da lista não entra', function () use ($falsas) {
    AssistentePesquisaAgent::fake($falsas);
    app(AssistentePesquisa::class)->ligar($this->b98, true, $this->clinica->id);
    F::como($this, $this->externo);
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/sugestoes")->assertCreated()->assertJsonPath('criadas', 3);

    $ids = DB::table('fisio_study_ai_suggestions')->where('study_id', $this->estudo->id)->orderBy('id')->pluck('status', 'id');
    expect($ids->values()->all())->toBe(['pendente', 'pendente', 'pendente']);
    [$a, $d] = [$ids->keys()[0], $ids->keys()[1]];
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/sugestoes/{$a}/aceitar")->assertOk();
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/sugestoes/{$d}/descartar")->assertOk();
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/sugestoes/{$a}/descartar")->assertStatus(422);

    $aceita = DB::table('fisio_study_ai_suggestions')->where('id', $a)->first();
    expect($aceita->status)->toBe('aceita')
        ->and((int) $aceita->decided_by)->toBe($this->externo->id)
        ->and($aceita->decided_at)->not->toBeNull()
        ->and(DB::table('fisio_study_ai_suggestions')->where('text', 'Aumente o exercício.')->exists())->toBeFalse();
});

it('UC-FES-19 · nada sai da área do estudo: portal e ficha não trazem a sugestão; outra clínica 404 [T0]', function () use ($falsas) {
    AssistentePesquisaAgent::fake($falsas);
    app(AssistentePesquisa::class)->ligar($this->b98, true, $this->clinica->id);
    F::como($this, $this->clinica);
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/sugestoes")->assertCreated();
    $this->postJson("/fisio/pesquisa/estudos/{$this->e99->id}/sugestoes")->assertNotFound();

    $ficha = $this->get('/fisio/pacientes/'.$this->p->id)->assertOk()->getContent();
    $token = F::portalPronto($this, $this->p);
    $portal = json_encode(F::portalProps($this, $token));
    expect($ficha)->not->toContain('Hipótese: adesão cai')
        ->and($portal)->not->toContain('Hipótese: adesão cai');
});
