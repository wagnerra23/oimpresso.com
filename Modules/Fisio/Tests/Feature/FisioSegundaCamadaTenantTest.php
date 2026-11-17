<?php

declare(strict_types=1);
// Cobre UC-FPS-01 (Fisio/Pacientes) e UC-FCA-04 (Fisio/Configuracoes/Agendamento.casos.md):
// paciente e tipo de outra empresa seguem fora de alcance mesmo se o escopo global falhar.

use App\Scopes\ScopeByBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Auditoria do Fisio d8 (2026-11-05): o helper de paciente dos controllers e o update em massa de
 * `online_bookable` dependiam só do escopo global. Aqui o escopo é tirado de propósito (como se
 * falhasse) e a empresa 99 tenta alcançar o dado da 98: a segunda camada (`where business_id`
 * explícito) tem de segurar. Controle positivo: sem o escopo, a busca crua acha o paciente da 98.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
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
    F::pacote([$this->b98, $this->b99]);
    $this->p98 = F::paciente($this->b98, $this->u98->id);
});

// Devolve os escopos globais ao estado normal para os outros testes.
afterEach(fn () => Model::clearBootedModels());

/** Tira o ScopeByBusiness do model, simulando o escopo global que não se aplicou. */
function fscSemEscopo(string $classe): void
{
    $classe::query(); // garante o boot do model antes de mexer nos escopos
    Closure::bind(function () use ($classe): void {
        unset(static::$globalScopes[$classe][ScopeByBusiness::class]);
    }, null, Model::class)();
}

it('UC-FPS-01 · sem o escopo global, a 99 ainda não alcança o paciente da 98 em nenhuma rota', function () {
    fscSemEscopo(Patient::class);
    F::como($this, $this->u99);

    // Controle positivo: o escopo saiu mesmo — a busca crua acha o paciente da 98.
    expect(Patient::query()->find($this->p98->id)?->id)->toBe($this->p98->id);

    $id = $this->p98->id;
    $this->get("/fisio/pacientes/{$id}")->assertNotFound();
    $this->putJson("/fisio/pacientes/{$id}", ['main_complaint' => 'invadido'])->assertNotFound();
    $this->deleteJson("/fisio/pacientes/{$id}")->assertNotFound();
    $this->postJson("/fisio/pacientes/{$id}/registros", ['kind' => 'evolucao', 'body' => ['conduta' => 'x', 'resposta_paciente' => 'y']])->assertNotFound();
    $this->postJson("/fisio/pacientes/{$id}/questionarios", ['tipo' => 'x'])->assertNotFound();
    $this->postJson("/fisio/pacientes/{$id}/responsavel", ['user_id' => $this->u99->id])->assertNotFound();
    $this->postJson("/fisio/pacientes/{$id}/portal")->assertNotFound();
    $this->deleteJson("/fisio/pacientes/{$id}/portal")->assertNotFound();
    $this->get("/fisio/pacientes/{$id}/prontuario.pdf")->assertNotFound();
    $this->get("/fisio/pacientes/{$id}/laudo.pdf")->assertNotFound();

    // Nada da 98 mudou.
    $depois = Patient::withoutGlobalScopes()->find($id); // SUPERADMIN: leitura do teste.
    expect($depois->main_complaint)->toBe('Lombalgia mecânica')
        ->and($depois->blocked_at)->toBeNull()
        ->and($depois->responsible_user_id)->not->toBe($this->u99->id);
});

it('UC-FCA-04 · sem o escopo global, salvar o agendamento da 99 não liga nem desliga tipo da 98', function () {
    // Os tipos de cada empresa nascem ao abrir a tela.
    $tipos98 = collect(F::props($this, $this->u98, '/fisio/configuracoes/agendamento', 'Fisio/Configuracoes/Agendamento', 'tipos'))->pluck('id')->map('intval');
    F::props($this, $this->u99, '/fisio/configuracoes/agendamento', 'Fisio/Configuracoes/Agendamento', 'tipos');
    // Um tipo da 98 liberado, outro não.
    AppointmentType::withoutGlobalScopes()->whereIn('id', $tipos98)->update(['online_bookable' => false]); // SUPERADMIN: preparo do teste.
    AppointmentType::withoutGlobalScopes()->whereKey($tipos98[0])->update(['online_bookable' => true]); // SUPERADMIN: preparo do teste.

    fscSemEscopo(AppointmentType::class);
    F::como($this, $this->u99);
    $this->put('/fisio/configuracoes/agendamento', [
        'online_enabled' => true, 'who_can_book' => 'todos', 'open_slots' => [],
        'min_notice_hours' => 2, 'cancel_min_hours' => 24, 'max_days_ahead' => 30, 'no_show_block_after' => 2,
        'waitlist_enabled' => true, 'checkin_enabled' => true, 'tipos_online' => [$tipos98[1]],
    ])->assertSessionHasNoErrors();

    // SUPERADMIN: leitura do teste.
    $liberados98 = AppointmentType::withoutGlobalScopes()->where('business_id', $this->b98)->where('online_bookable', true)->pluck('id')->map('intval')->all();
    expect($liberados98)->toBe([$tipos98[0]]);
});
