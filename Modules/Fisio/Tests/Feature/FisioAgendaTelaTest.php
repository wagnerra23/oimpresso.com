<?php

declare(strict_types=1);
// Cobre US-FISIO-015 (rotas da agenda da clínica) e UC-FAG-01..04 (Fisio/Agenda/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Agenda da clínica pelas rotas (tenant 98 × 99, ADR 0358). O pacote é simulado só para
 * `fisio_module`; o resto roda de verdade. ⚠️ SKIP em SQLite (LC-13): leia assertions.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_appointments', 'fisio_booking_settings', 'fisio_waitlist'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p98 = F::paciente($this->b98, $this->u98->id);
    $this->ini = now()->addDays(2)->startOfWeek()->addWeek()->setTime(9, 0);
});

/** Marca pela rota como a 98 e devolve o atendimento gravado. */
function marcarPelaRota($t, ?int $tipo = null): Appointment
{
    F::como($t, $t->u98);
    $tipo ??= (int) collect(F::props($t, $t->u98, '/fisio/agenda', 'Fisio/Agenda/Index', 'tipos'))->firstWhere('nome', 'Sessão')['id'];
    $t->post('/fisio/agenda/atendimentos', ['patient_id' => $t->p98->id, 'type_id' => $tipo,
        'professional_id' => $t->u98->id, 'starts_at' => $t->ini->format('Y-m-d H:i')])->assertSessionHasNoErrors();

    // SUPERADMIN: leitura do teste.
    return Appointment::withoutGlobalScopes()->where('business_id', $t->b98)->latest('id')->firstOrFail();
}

it('UC-FAG-01 · primeira abertura cria os 4 tipos padrão da empresa, e só dela', function () {
    $nomes = collect(F::props($this, $this->u98, '/fisio/agenda', 'Fisio/Agenda/Index', 'tipos'))->pluck('nome')->all();

    expect($nomes)->toBe(['Avaliação', 'Sessão', 'Reavaliação', 'Teleconsulta'])
        // SUPERADMIN: a 99 não ganhou tipo por a 98 ter aberto a agenda.
        ->and(AppointmentType::withoutGlobalScopes()->where('business_id', $this->b99)->count())->toBe(0);
});

it('UC-FAG-02 · nova sessão aparece na semana com o nome curto; a semana da 99 não a vê', function () {
    $a = marcarPelaRota($this);
    $semana = '/fisio/agenda?semana='.$this->ini->toDateString();

    $da98 = collect(F::props($this, $this->u98, $semana, 'Fisio/Agenda/Index', 'atendimentos'));
    expect($da98->pluck('id')->all())->toContain($a->id)
        ->and($da98->firstWhere('id', $a->id)['status'])->toBe('agendado')
        ->and($da98->firstWhere('id', $a->id))->not->toHaveKey('patient_note');

    $da99 = collect(F::props($this, $this->u99, $semana.'&profissional='.$this->u98->id, 'Fisio/Agenda/Index', 'atendimentos'));
    expect($da99->pluck('id')->all())->not->toContain($a->id);
});

it('UC-FAG-03 · a 99 não muda, não desmarca nem marca com paciente da 98 (404) e nada muda', function () {
    $a = marcarPelaRota($this);
    F::como($this, $this->u99);

    $this->post("/fisio/agenda/atendimentos/{$a->id}/status", ['status' => 'faltou'])->assertNotFound();
    $this->post("/fisio/agenda/atendimentos/{$a->id}/cancelar", ['por' => 'clinica'])->assertNotFound();
    $this->post('/fisio/agenda/atendimentos', ['patient_id' => $this->p98->id, 'type_id' => $a->type_id,
        'professional_id' => $this->u99->id, 'starts_at' => $this->ini->format('Y-m-d H:i')])->assertNotFound();
    $this->post('/fisio/agenda/espera', ['patient_id' => $this->p98->id])->assertNotFound();

    expect($a->fresh()->status)->toBe('agendado')
        // SUPERADMIN: nada gravado em nome da 99.
        ->and(WaitlistEntry::withoutGlobalScopes()->where('business_id', $this->b99)->count())->toBe(0);
});

it('UC-FAG-04 · sem fisio.editar a agenda abre, mas não marca nem desmarca (403)', function () {
    $a = marcarPelaRota($this);
    F::como($this, $this->leitor98);

    $this->post('/fisio/agenda/atendimentos', ['patient_id' => $this->p98->id, 'type_id' => $a->type_id,
        'professional_id' => $this->u98->id, 'starts_at' => $this->ini->copy()->addHour()->format('Y-m-d H:i')])->assertForbidden();
    $this->post("/fisio/agenda/atendimentos/{$a->id}/cancelar", ['por' => 'clinica'])->assertForbidden();

    expect(F::props($this, $this->leitor98, '/fisio/agenda', 'Fisio/Agenda/Index', 'tipos'))->not->toBeEmpty()
        ->and($a->fresh()->status)->toBe('agendado');
});

it('UC-FAG-02 · desmarcar com a lista de espera devolve quantos servem na vaga', function () {
    $a = marcarPelaRota($this);
    F::como($this, $this->u98);
    $outro = F::paciente($this->b98, $this->u98->id);
    $this->post('/fisio/agenda/espera', ['patient_id' => $outro->id, 'turnos' => ['manha']])->assertSessionHasNoErrors();

    $this->post("/fisio/agenda/atendimentos/{$a->id}/cancelar", ['por' => 'paciente'])
        ->assertSessionHas('status', 'Sessão desmarcada. 1 paciente da lista de espera serve neste horário.');
});
