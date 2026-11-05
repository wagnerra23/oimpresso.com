<?php

declare(strict_types=1);
// Cobre US-FISIO-016 e UC-FPA-01..07 (memory/requisitos/Fisio/_telas/agenda-paciente.casos.md).

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Services\Agenda\AgendaService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Agenda no portal do paciente pelas rotas públicas reais (tenant 98 × 99, ADR 0358). O portal
 * entra pelo PIN e pelo consentimento de verdade (F::portalPronto). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_appointments', 'fisio_booking_settings', 'fisio_waitlist'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->travelTo(Carbon::parse('next monday 07:00'));
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p98 = F::paciente($this->b98, $this->u98->id, ['responsible_user_id' => $this->u98->id]);
    // SUPERADMIN: fixture da empresa 98.
    $this->tipo = AppointmentType::withoutGlobalScopes()->create(['business_id' => $this->b98, 'name' => 'Sessão', 'duration_min' => 50, 'online_bookable' => true]);
    $r = BookingSettings::daEmpresa($this->b98);
    $r->forceFill(['online_enabled' => true, 'who_can_book' => 'todos', 'open_slots' => ['3' => ['08:30', '10:00', '14:00']]])->save();
    $this->quarta = now()->addDays(2)->toDateString();
    $this->token = F::portalPronto($this, $this->p98);
    $this->url = '/fisio/p/'.$this->token.'/agenda';
    $this->marcar = fn (string $h = '10:00', array $extra = []) => $this->postJson($this->url, array_merge([
        'tipo' => $this->tipo->id, 'profissional' => $this->u98->id, 'inicio' => "{$this->quarta} {$h}",
    ], $extra));
});

it('UC-FPA-01 · horários livres saem só dos abertos para o app, sem os ocupados', function () {
    // SUPERADMIN: atendimento de outro paciente às 08:30 (marcado pela clínica).
    app(AgendaService::class)->marcar($this->b98, ['patient_id' => F::paciente($this->b98, $this->u98->id)->id, 'professional_id' => $this->u98->id,
        'type_id' => $this->tipo->id, 'starts_at' => Carbon::parse("{$this->quarta} 08:30")]);

    $this->getJson("{$this->url}/horarios?tipo={$this->tipo->id}&profissional={$this->u98->id}&dia={$this->quarta}")
        ->assertOk()->assertJsonPath('horarios', ['10:00', '14:00']);
    // Controle: quinta não tem horário aberto para o app.
    $this->getJson("{$this->url}/horarios?tipo={$this->tipo->id}&profissional={$this->u98->id}&dia=".now()->addDays(3)->toDateString())
        ->assertOk()->assertJsonPath('horarios', []);
});

it('UC-FPA-02 · marcar grava pelo app com o recado; o mesmo horário não marca duas vezes', function () {
    ($this->marcar)('10:00', ['recado' => 'o joelho piorou'])->assertCreated();

    // SUPERADMIN: leitura do teste.
    $a = Appointment::withoutGlobalScopes()->where('patient_id', $this->p98->id)->firstOrFail();
    expect($a->source)->toBe('online')->and($a->patient_note)->toBe('o joelho piorou')->and((int) $a->business_id)->toBe($this->b98);
    ($this->marcar)('10:00')->assertStatus(422);
});

it('UC-FPA-03 · app desligado, só pela clínica ou bloqueado por faltas: não marca', function () {
    BookingSettings::daEmpresa($this->b98)->forceFill(['online_enabled' => false])->save();
    ($this->marcar)()->assertStatus(422)->assertJsonPath('errors.agenda.0', 'desligado');

    BookingSettings::daEmpresa($this->b98)->forceFill(['online_enabled' => true, 'who_can_book' => 'ninguem'])->save();
    ($this->marcar)()->assertStatus(422)->assertJsonPath('errors.agenda.0', 'so_pela_clinica');

    BookingSettings::daEmpresa($this->b98)->forceFill(['who_can_book' => 'todos'])->save();
    Patient::withoutGlobalScopes()->whereKey($this->p98->id)->update(['booking_blocked_at' => now()]); // SUPERADMIN: fixture.
    ($this->marcar)()->assertStatus(422)->assertJsonPath('errors.agenda.0', 'bloqueado_faltas');
    $this->getJson($this->url)->assertOk()->assertJsonPath('impedimento', 'bloqueado_faltas');
});

it('UC-FPA-04 · cancelar e remarcar só antes do prazo de 24 h', function () {
    $id = ($this->marcar)('14:00')->json('id');
    $this->postJson("{$this->url}/{$id}/remarcar", ['inicio' => "{$this->quarta} 10:00"])->assertOk();
    // SUPERADMIN: leituras do teste.
    $novo = Appointment::withoutGlobalScopes()->where('patient_id', $this->p98->id)->where('status', 'agendado')->firstOrFail();
    expect((int) $novo->rescheduled_from_id)->toBe($id)->and($novo->starts_at->format('H:i'))->toBe('10:00');

    $this->travelTo(Carbon::parse("{$this->quarta} 08:00"));
    $this->postJson("{$this->url}/{$novo->id}/cancelar")->assertStatus(422)->assertJsonPath('errors.agenda.0', 'fora_do_prazo');
    expect($novo->fresh()->status)->toBe('agendado');
});

it('UC-FPA-05 · confirmar e "Cheguei" a partir de 1 h antes', function () {
    $id = ($this->marcar)('10:00')->json('id');
    $this->postJson("{$this->url}/{$id}/confirmar")->assertOk()->assertJsonPath('status', 'confirmado');

    $this->travelTo(Carbon::parse("{$this->quarta} 08:30"));
    $this->postJson("{$this->url}/{$id}/cheguei")->assertStatus(422);
    $this->travelTo(Carbon::parse("{$this->quarta} 09:15"));
    $this->postJson("{$this->url}/{$id}/cheguei")->assertOk()->assertJsonPath('status', 'chegou');
});

it('UC-FPA-06 · "me avise se abrir vaga" põe na lista de espera; sair tira', function () {
    ($this->marcar)('14:00', ['avisar_se_abrir' => true])->assertCreated();
    expect($this->getJson($this->url)->json('espera'))->not->toBeNull();

    $this->deleteJson("{$this->url}/espera")->assertOk();
    // SUPERADMIN: leitura do teste.
    expect(WaitlistEntry::withoutGlobalScopes()->where('patient_id', $this->p98->id)->where('status', 'ativo')->count())->toBe(0);
});

it('UC-FPA-07 · atendimento de outro paciente ou de outra clínica é 404; sem PIN é 403', function () {
    $outro = F::paciente($this->b98, $this->u98->id);
    $alheio = app(AgendaService::class)->marcar($this->b98, ['patient_id' => $outro->id, 'professional_id' => $this->u98->id,
        'type_id' => $this->tipo->id, 'starts_at' => Carbon::parse("{$this->quarta} 08:30")]);

    $this->postJson("{$this->url}/{$alheio->id}/cancelar")->assertNotFound();
    expect($alheio->fresh()->status)->toBe('agendado')
        ->and(collect($this->getJson($this->url)->json('proximas'))->pluck('id')->all())->not->toContain($alheio->id);

    $this->flushSession();
    $this->getJson($this->url)->assertForbidden()->assertJsonPath('erro', 'pin_pendente');
});
