<?php

declare(strict_types=1);
// Cobre US-FISIO-015 e UC-FCA-01..04 (Fisio/Configuracoes/Agendamento.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Configurações › Agendamento pelo app (tenant 98 × 99, ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_booking_settings', 'fisio_appointment_types'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->corpo = fn (array $extra = []) => array_merge([
        'online_enabled' => true, 'who_can_book' => 'todos', 'open_slots' => ['1' => ['08:30', '08:00'], '4' => ['14:00']],
        'min_notice_hours' => 2, 'cancel_min_hours' => 24, 'max_days_ahead' => 30, 'no_show_block_after' => 2,
        'waitlist_enabled' => true, 'checkin_enabled' => true, 'tipos_online' => [],
    ], $extra);
});

it('UC-FCA-01 · abre com o app desligado e os tipos da empresa, nenhum liberado', function () {
    $r = F::props($this, $this->u98, '/fisio/configuracoes/agendamento', 'Fisio/Configuracoes/Agendamento', 'regras');
    $tipos = F::props($this, $this->u98, '/fisio/configuracoes/agendamento', 'Fisio/Configuracoes/Agendamento', 'tipos');

    expect($r['online_enabled'])->toBeFalse()
        ->and(collect($tipos)->pluck('nome')->all())->toBe(['Avaliação', 'Sessão', 'Reavaliação', 'Teleconsulta'])
        ->and(collect($tipos)->pluck('online')->unique()->all())->toBe([false]);
});

it('UC-FCA-02 · salvar liga o app, ordena os horários e libera só os tipos marcados', function () {
    $tipos = collect(F::props($this, $this->u98, '/fisio/configuracoes/agendamento', 'Fisio/Configuracoes/Agendamento', 'tipos'));
    $sessao = (int) $tipos->firstWhere('nome', 'Sessão')['id'];

    $this->put('/fisio/configuracoes/agendamento', ($this->corpo)(['tipos_online' => [$sessao]]))->assertSessionHasNoErrors();

    $r = BookingSettings::daEmpresa($this->b98);
    expect($r->online_enabled)->toBeTrue()
        ->and($r->open_slots)->toBe(['1' => ['08:00', '08:30'], '4' => ['14:00']])
        // SUPERADMIN: leitura do teste.
        ->and(AppointmentType::withoutGlobalScopes()->where('business_id', $this->b98)->where('online_bookable', true)->pluck('id')->all())->toBe([$sessao]);
});

it('UC-FCA-03 · mudar prazo de cancelamento ou faltas sobe a versão da política; o resto não', function () {
    F::como($this, $this->u98);
    $v = (int) BookingSettings::daEmpresa($this->b98)->policy_version;

    $this->put('/fisio/configuracoes/agendamento', ($this->corpo)())->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Agendamento pelo app salvo.');
    expect((int) BookingSettings::daEmpresa($this->b98)->policy_version)->toBe($v);

    $this->put('/fisio/configuracoes/agendamento', ($this->corpo)(['cancel_min_hours' => 12]))->assertSessionHasNoErrors();
    expect((int) BookingSettings::daEmpresa($this->b98)->policy_version)->toBe($v + 1);
});

it('UC-FCA-04 · sem fisio.editar não salva (403); a 99 salvando não toca a 98', function () {
    F::como($this, $this->leitor98);
    $this->put('/fisio/configuracoes/agendamento', ($this->corpo)())->assertForbidden();
    expect(BookingSettings::daEmpresa($this->b98)->online_enabled)->toBeFalse();

    // A 99 tenta liberar um tipo da 98 pelo id: não muda o da 98.
    $tipo98 = (int) collect(F::props($this, $this->u98, '/fisio/configuracoes/agendamento', 'Fisio/Configuracoes/Agendamento', 'tipos'))->first()['id'];
    F::como($this, $this->u99);
    $this->put('/fisio/configuracoes/agendamento', ($this->corpo)(['tipos_online' => [$tipo98]]))->assertSessionHasNoErrors();

    // SUPERADMIN: leitura do teste.
    expect((bool) AppointmentType::withoutGlobalScopes()->whereKey($tipo98)->value('online_bookable'))->toBeFalse()
        ->and(BookingSettings::daEmpresa($this->b98)->online_enabled)->toBeFalse()
        ->and(BookingSettings::daEmpresa($this->b99)->online_enabled)->toBeTrue();
});
