<?php

declare(strict_types=1);
// Cobre US-FISIO-013 (memory/requisitos/Fisio/SPEC.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Entities\ScheduleBlock;
use Modules\Fisio\Entities\WaitlistEntry;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da camada de dados da agenda (US-FISIO-013). Tenant 98 contra o 99 (ADR 0358),
 * nunca biz=4. A fixture grava só o estado inicial; o escopo é medido pelos models.
 * ⚠️ SKIP em SQLite (LC-13): leia assertions.
 */
const AGENDA_TABELAS = ['fisio_appointment_types', 'fisio_appointments', 'fisio_schedule_blocks', 'fisio_waitlist', 'fisio_booking_settings'];

beforeEach(function () {
    if ($motivo = F::motivoDeSkip(AGENDA_TABELAS)) {
        $this->markTestSkipped($motivo);
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededSupportClientTenant());

    foreach ([[$this->b98, $this->u98], [$this->b99, $this->u99]] as [$biz, $u]) {
        $p = F::paciente($biz, $u->id);
        // SUPERADMIN: fixture grava nas duas empresas; o escopo é provado nos asserts.
        $tipo = AppointmentType::withoutGlobalScopes()->create(['business_id' => $biz, 'name' => 'Sessão', 'duration_min' => 50]);
        $ini = now()->addDay()->setTime(9, 0);
        Appointment::withoutGlobalScopes()->create(['business_id' => $biz, 'patient_id' => $p->id, 'professional_id' => $u->id,
            'type_id' => $tipo->id, 'starts_at' => $ini, 'ends_at' => $ini->copy()->addMinutes(50)]);
        ScheduleBlock::withoutGlobalScopes()->create(['business_id' => $biz, 'professional_id' => $u->id,
            'starts_at' => $ini->copy()->setTime(12, 0), 'ends_at' => $ini->copy()->setTime(13, 0), 'reason' => 'almoço']);
        WaitlistEntry::withoutGlobalScopes()->create(['business_id' => $biz, 'patient_id' => $p->id, 'preferences' => ['turnos' => ['manha']]]);
        BookingSettings::daEmpresa($biz);
    }
});

it('as 5 tabelas da agenda têm business_id NOT NULL e indexado', function () {
    foreach (AGENDA_TABELAS as $t) {
        $col = collect(Schema::getColumns($t))->firstWhere('name', 'business_id');
        $idx = collect(Schema::getIndexes($t))->filter(fn ($i) => ($i['columns'][0] ?? null) === 'business_id');

        expect($col)->not->toBeNull()
            ->and($col['nullable'])->toBeFalse()
            ->and($idx)->not->toBeEmpty();
    }
});

it('sob a sessão da 98 cada model da agenda enxerga só a 98; a 99 só a 99', function () {
    $models = [AppointmentType::class, Appointment::class, ScheduleBlock::class, WaitlistEntry::class, BookingSettings::class];

    foreach ([[$this->u98, $this->b98], [$this->u99, $this->b99]] as [$u, $biz]) {
        F::como($this, $u);
        foreach ($models as $m) {
            $bizes = $m::query()->pluck('business_id')->map('intval')->unique()->values()->all();
            expect($bizes)->toBe([$biz], "{$m} sob a sessão da {$biz}");
        }
    }

    // Controle negativo: sem escopo as duas empresas existem — o filtro acima é real.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(Appointment::withoutGlobalScopes()->whereIn('business_id', [$this->b98, $this->b99])->distinct()->count('business_id'))->toBe(2);
});

it('atendimento não é apagado pelo Eloquent: desmarcar é status', function () {
    F::como($this, $this->u98);
    $a = Appointment::query()->firstOrFail();

    expect(fn () => $a->delete())->toThrow(DadoClinicoImutavelException::class)
        ->and(DB::table('fisio_appointments')->where('id', $a->id)->exists())->toBeTrue();
});

it('regras da agenda: uma linha por empresa, nasce com o app desligado e sem sinal', function () {
    $r = BookingSettings::daEmpresa($this->b98);

    expect(BookingSettings::daEmpresa($this->b98)->id)->toBe($r->id)
        // SUPERADMIN: contagem direta da linha da empresa.
        ->and(BookingSettings::withoutGlobalScopes()->where('business_id', $this->b98)->count())->toBe(1)
        ->and($r->online_enabled)->toBeFalse()
        ->and((int) $r->deposit_percent)->toBe(0)
        ->and((int) $r->cancel_min_hours)->toBe(24)
        ->and((int) $r->no_show_block_after)->toBe(2);
});
