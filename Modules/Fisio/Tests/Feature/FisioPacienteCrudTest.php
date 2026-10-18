<?php

declare(strict_types=1);
// @covers-us US-FISIO-005
// Cobre UC-FPI-01, UC-FPI-03 (Fisio/Pacientes/Index.casos.md) e UC-FPS-02, UC-FPS-07 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Paciente É um contact (ADR 0437 §4): o Fisio guarda só o perfil clínico 1:1, nunca nome
 * ou telefone duplicados. "Excluir" é bloquear (Lei 13.787/2018, guarda de 20 anos).
 *
 * Tenant 98. ⚠️ SKIP em SQLite (LC-13).
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
});

function fpcPayload(int $contactId, array $mudar = []): array
{
    return array_merge([
        'contact_id' => $contactId,
        'main_complaint' => 'Dor no ombro direito ao elevar o braço',
        'goals' => ['pentear o cabelo sem dor', 'voltar à natação'],
        'sessions_per_week' => 3,
        'condition_flags' => ['pos_op_manguito'],
    ], $mudar);
}

it('UC-FPI-01 · cria o perfil clínico a partir de um contact, sem duplicar nome nem telefone', function () {
    $contato = F::contato($this->b98, $this->u98->id, 'Marta Souza');

    $this->post('/fisio/pacientes', fpcPayload($contato))->assertSessionHasNoErrors()->assertRedirect();

    $p = Patient::query()->where('contact_id', $contato)->firstOrFail();
    expect((int) $p->business_id)->toBe($this->b98)
        ->and($p->goals)->toBe(['pentear o cabelo sem dor', 'voltar à natação'])
        ->and((int) $p->sessions_per_week)->toBe(3)
        ->and($p->condition_flags)->toBe(['pos_op_manguito'])
        ->and($p->blocked_at)->toBeNull();

    // O nome mora no contact: a tabela clínica não tem coluna de nome/telefone.
    expect(Schema::hasColumn('fisio_patients', 'name'))->toBeFalse()
        ->and(Schema::hasColumn('fisio_patients', 'mobile'))->toBeFalse();

    // A ficha lê o nome do contact — renomear o contact reflete na ficha.
    DB::table('contacts')->where('id', $contato)->update(['name' => 'Marta Souza Lima']);
    $this->get("/fisio/pacientes/{$p->id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $pg) => $pg->component('Fisio/Pacientes/Show', false)
            ->where('paciente.id', $p->id)
            ->where('paciente.nome', 'Marta Souza Lima'));
});

it('UC-FPI-01 · business_id vem da sessão, nunca do corpo', function () {
    $contato = F::contato($this->b98, $this->u98->id);

    $this->post('/fisio/pacientes', fpcPayload($contato, ['business_id' => 99]))->assertSessionHasNoErrors();

    expect((int) Patient::query()->where('contact_id', $contato)->value('business_id'))->toBe($this->b98);
});

it('UC-FPI-03 · o mesmo contact não vira dois pacientes no mesmo business', function () {
    $contato = F::contato($this->b98, $this->u98->id);
    $this->post('/fisio/pacientes', fpcPayload($contato))->assertSessionHasNoErrors();

    $this->post('/fisio/pacientes', fpcPayload($contato))->assertSessionHasErrors('contact_id');

    expect(Patient::query()->where('contact_id', $contato)->count())->toBe(1);
});

dataset('sessoes invalidas', [
    'zero (adesão dividiria por zero)' => [0],
    'negativo' => [-1],
    'oito (semana tem 7 dias)' => [8],
    'texto' => ['tres'],
    'vazio' => [null],
]);

it('UC-FPI-03 · sessions_per_week fora de 1–7 é recusado na criação e na edição', function ($valor) {
    $contato = F::contato($this->b98, $this->u98->id);
    $this->post('/fisio/pacientes', fpcPayload($contato, ['sessions_per_week' => $valor]))->assertSessionHasErrors('sessions_per_week');
    expect(Patient::query()->where('contact_id', $contato)->exists())->toBeFalse();

    $p = F::paciente($this->b98, $this->u98->id);
    $this->put("/fisio/pacientes/{$p->id}", ['main_complaint' => 'x', 'sessions_per_week' => $valor])->assertSessionHasErrors('sessions_per_week');
    expect((int) $p->fresh()->sessions_per_week)->toBe(3);
})->with('sessoes invalidas');

it('UC-FPI-03 · condition_flags aceita só marcadores da lista fechada', function () {
    $contato = F::contato($this->b98, $this->u98->id);

    $this->post('/fisio/pacientes', fpcPayload($contato, ['condition_flags' => ['inventado']]))->assertSessionHasErrors('condition_flags.0');
    $this->post('/fisio/pacientes', fpcPayload($contato, ['condition_flags' => ['protese_quadril_posterior', 'gestante']]))->assertSessionHasNoErrors();

    expect(Patient::query()->where('contact_id', $contato)->value('condition_flags'))->toBe(['protese_quadril_posterior', 'gestante']);
});

it('UC-FPS-02 · edita queixa, objetivos, sessões e marcadores', function () {
    $p = F::paciente($this->b98, $this->u98->id);

    $this->put("/fisio/pacientes/{$p->id}", [
        'main_complaint' => 'Lombalgia com irradiação',
        'goals' => ['carregar o filho'],
        'sessions_per_week' => 5,
        'condition_flags' => ['osteoporose'],
    ])->assertSessionHasNoErrors();

    $f = $p->fresh();
    expect($f->main_complaint)->toBe('Lombalgia com irradiação')
        ->and($f->goals)->toBe(['carregar o filho'])
        ->and((int) $f->sessions_per_week)->toBe(5)
        ->and($f->condition_flags)->toBe(['osteoporose']);
});

it('UC-FPS-07 · excluir paciente = bloquear: a linha, o prontuário e os check-ins ficam', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    $prog = F::programa($p, [F::exercicio(null)], $this->u98->id);
    F::checkin($p, $prog, now()->subDay()->toDateString(), F::itens($prog), 4);
    $this->postJson("/fisio/pacientes/{$p->id}/registros", [
        'kind' => 'evolucao', 'crefito' => F::CREFITO,
        'body' => ['conduta' => 'Cinesioterapia ativa', 'resposta_paciente' => 'Tolerou bem'],
    ])->assertCreated();

    $this->delete("/fisio/pacientes/{$p->id}")->assertRedirect();

    // SUPERADMIN: verificação de guarda — lê fora do escopo para provar que nada sumiu.
    $linha = DB::table('fisio_patients')->where('id', $p->id)->first();
    expect($linha)->not->toBeNull()
        ->and($linha->blocked_at)->not->toBeNull()
        ->and(DB::table('fisio_records')->where('patient_id', $p->id)->count())->toBe(1)
        ->and(DB::table('fisio_checkins')->where('patient_id', $p->id)->count())->toBe(1)
        ->and(DB::table('fisio_programs')->where('patient_id', $p->id)->count())->toBe(1);

    // Bloqueado sai da lista padrão de ativos, mas a ficha continua legível (guarda de 20 anos).
    $ids = collect(F::props($this, $this->u98, '/fisio/pacientes', 'Fisio/Pacientes/Index', 'pacientes'))->pluck('id');
    expect($ids)->not->toContain($p->id);
    $this->get("/fisio/pacientes/{$p->id}")->assertOk();
});

it('UC-FPS-07 · excluir de novo é idempotente e não apaga nada', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    $this->delete("/fisio/pacientes/{$p->id}");
    $primeiro = DB::table('fisio_patients')->where('id', $p->id)->value('blocked_at');

    $this->delete("/fisio/pacientes/{$p->id}");

    expect(DB::table('fisio_patients')->where('id', $p->id)->value('blocked_at'))->toBe($primeiro);
});

it('UC-FPI-01 · busca por nome do contact na lista', function () {
    $alvo = F::paciente($this->b98, $this->u98->id, ['contact_id' => F::contato($this->b98, $this->u98->id, 'Zuleica Prado')]);
    $outro = F::paciente($this->b98, $this->u98->id, ['contact_id' => F::contato($this->b98, $this->u98->id, 'Bruno Alves')]);

    $ids = collect(F::props($this, $this->u98, '/fisio/pacientes?q=Zuleica', 'Fisio/Pacientes/Index', 'pacientes'))->pluck('id');

    expect($ids)->toContain($alvo->id)->not->toContain($outro->id);
});
