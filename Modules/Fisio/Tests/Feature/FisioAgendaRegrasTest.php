<?php

declare(strict_types=1);
// Cobre US-FISIO-014 (memory/requisitos/Fisio/SPEC.md · etapa 04 A1, A4, A5, A7, A8, A10, A21).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Entities\ScheduleBlock;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Services\Agenda\AgendaService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regras da agenda pelo serviço (tenant 98 × 99, ADR 0358). A fixture cria só o estado inicial;
 * conflito, status, prazo, lista de espera e bloqueio saem do AgendaService.
 * ⚠️ SKIP em SQLite (LC-13): leia assertions.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_appointments', 'fisio_booking_settings', 'fisio_waitlist'])) {
        $this->markTestSkipped($motivo);
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->p98 = F::paciente($this->b98, $this->u98->id);
    $this->p99 = F::paciente($this->b99, $this->u99->id);
    // SUPERADMIN: fixture por empresa.
    $this->t98 = AppointmentType::withoutGlobalScopes()->create(['business_id' => $this->b98, 'name' => 'Sessão', 'duration_min' => 50]);
    $this->agenda = app(AgendaService::class);
    $this->ini = now()->addDays(3)->setTime(9, 0);
    $this->marcar = fn (array $d = [], bool $encaixe = false) => $this->agenda->marcar($this->b98, array_merge([
        'patient_id' => $this->p98->id, 'professional_id' => $this->u98->id, 'type_id' => $this->t98->id, 'starts_at' => $this->ini,
    ], $d), $encaixe);
});

it('marca com o fim pela duração do tipo; mesmo horário só como encaixe confirmado (A1, A4)', function () {
    $a = ($this->marcar)();
    expect($a->ends_at->diffInMinutes($a->starts_at, true))->toBe(50.0)->and($a->is_overbook)->toBeFalse();

    expect(fn () => ($this->marcar)(['starts_at' => $this->ini->copy()->addMinutes(30)]))->toThrow(ValidationException::class);
    $enc = ($this->marcar)(['starts_at' => $this->ini->copy()->addMinutes(30)], true);
    expect($enc->is_overbook)->toBeTrue();

    // Controle: logo depois do fim não é conflito.
    expect(($this->marcar)(['starts_at' => $this->ini->copy()->addMinutes(50)])->is_overbook)->toBeFalse();
});

it('horário bloqueado recusa até o encaixe; atendimento cancelado libera o horário (A5)', function () {
    // SUPERADMIN: fixture.
    ScheduleBlock::withoutGlobalScopes()->create(['business_id' => $this->b98, 'professional_id' => $this->u98->id,
        'starts_at' => $this->ini->copy()->setTime(12, 0), 'ends_at' => $this->ini->copy()->setTime(13, 0)]);
    expect(fn () => ($this->marcar)(['starts_at' => $this->ini->copy()->setTime(12, 30)], true))->toThrow(ValidationException::class);

    $a = ($this->marcar)();
    $this->agenda->cancelar($a, 'clinica');
    expect(($this->marcar)()->is_overbook)->toBeFalse();
});

it('paciente, tipo ou profissional de outra empresa não marcam na 98', function () {
    // SUPERADMIN: tipo da 99.
    $t99 = AppointmentType::withoutGlobalScopes()->create(['business_id' => $this->b99, 'name' => 'Sessão', 'duration_min' => 50]);

    expect(fn () => ($this->marcar)(['patient_id' => $this->p99->id]))->toThrow(ValidationException::class)
        ->and(fn () => ($this->marcar)(['type_id' => $t99->id]))->toThrow(ValidationException::class)
        ->and(fn () => ($this->marcar)(['professional_id' => $this->u99->id]))->toThrow(ValidationException::class);
    // SUPERADMIN: nada foi gravado em nome da 98.
    expect(Appointment::withoutGlobalScopes()->where('business_id', $this->b98)->count())->toBe(0);
});

it('realizado exige evolução assinada do mesmo paciente e trava o status (A10)', function () {
    $a = ($this->marcar)();
    expect(fn () => $this->agenda->mudarStatus($a, 'realizado'))->toThrow(ValidationException::class);

    // SUPERADMIN: evolução da fixture, assinada.
    $reg = Record::withoutGlobalScopes()->create(['business_id' => $this->b98, 'patient_id' => $this->p98->id, 'kind' => 'evolucao',
        'body' => ['conduta' => 'x'], 'signed_by' => $this->u98->id, 'signed_crefito' => F::CREFITO, 'signed_at' => now()]);
    $this->agenda->mudarStatus($a, 'realizado', $reg->id);

    expect($a->fresh()->status)->toBe('realizado')->and((int) $a->fresh()->record_id)->toBe($reg->id)
        ->and(fn () => $this->agenda->mudarStatus($a->fresh(), 'faltou'))->toThrow(ValidationException::class)
        ->and(fn () => $this->agenda->cancelar($a->fresh(), 'clinica'))->toThrow(ValidationException::class);
});

it('cancelar dentro de 24 h marca tardio; antes disso não (A8)', function () {
    $longe = ($this->marcar)();
    $perto = ($this->marcar)(['starts_at' => now()->addHours(5)->seconds(0)]);

    $this->agenda->cancelar($longe, 'paciente');
    $this->agenda->cancelar($perto, 'paciente', 'imprevisto');

    expect($longe->fresh()->late_cancel)->toBeFalse()->and($perto->fresh()->late_cancel)->toBeTrue()
        ->and($perto->fresh()->cancelled_by)->toBe('paciente');
});

it('vaga cancelada aponta só quem da lista de espera serve no dia e turno, por ordem de entrada (A7)', function () {
    $a = ($this->marcar)(); // 9h → manhã
    $dia = $this->ini->dayOfWeekIso;
    $mk = fn (array $pref, ?int $biz = null) => WaitlistEntry::withoutGlobalScopes()->create([ // SUPERADMIN: fixture.
        'business_id' => $biz ?? $this->b98, 'patient_id' => ($biz ? $this->p99 : F::paciente($this->b98, $this->u98->id))->id, 'preferences' => $pref]);
    $manha = $mk(['turnos' => ['manha']]);
    $qualquer = $mk([]);
    $mk(['turnos' => ['tarde']]);
    $mk(['dias' => [($dia % 7) + 1]]);
    $mk([], $this->b99);

    $ids = $this->agenda->cancelar($a, 'paciente')->pluck('id')->all();

    expect($ids)->toBe([$manha->id, $qualquer->id]);
});

it('2 faltas bloqueiam o app; desbloquear zera a conta das faltas antigas (A21)', function () {
    BookingSettings::daEmpresa($this->b98);
    $faltar = function (int $d) {
        $this->agenda->mudarStatus(($this->marcar)(['starts_at' => $this->ini->copy()->addDays($d)]), 'faltou');
    };

    $faltar(1);
    expect(Patient::withoutGlobalScopes()->find($this->p98->id)->booking_blocked_at)->toBeNull(); // SUPERADMIN: leitura do teste.
    $faltar(2);
    $pac = Patient::withoutGlobalScopes()->find($this->p98->id); // SUPERADMIN: leitura do teste.
    expect($pac->booking_blocked_at)->not->toBeNull();

    $this->travelTo(now()->addMinute());
    $this->agenda->desbloquear($pac);
    $faltar(3);
    expect(Patient::withoutGlobalScopes()->find($this->p98->id)->booking_blocked_at)->toBeNull(); // SUPERADMIN: leitura do teste.
});
