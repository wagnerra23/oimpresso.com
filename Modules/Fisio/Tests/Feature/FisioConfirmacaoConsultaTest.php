<?php

declare(strict_types=1);
// @covers-us US-FISIO-017
// Cobre UC-FCF-01..06 (Fisio/Public/Confirmacao.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Jobs\EnviarConfirmacaoConsultaJob;
use Modules\Fisio\Services\Agenda\AgendaService;
use Modules\Fisio\Services\Agenda\ConfirmacaoDaConsulta;
use Modules\Fisio\Tests\Support\FisioFixtures as F;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Confirmação da consulta pelo WhatsApp (etapa 04 A6, US-FISIO-017): a mensagem leva um link
 * assinado que abre a página de um toque. Tenant 98 contra 99 (ADR 0358). O job mede o efeito
 * (quantos SendWhatsappMessageJob e para quem); a página é exercida pelas rotas públicas reais.
 * ⚠️ SKIP em SQLite (LC-13): leia assertions.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_appointments', 'fisio_booking_settings', 'whatsapp_business_phones', 'activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.confirmacao.horas_antes' => 24, 'fisio.confirmacao.minimo_horas' => 2]);
    Carbon::setTestNow('2026-10-14 08:00:00'); // quarta
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->fone98 = F::telefoneWhatsapp($this->b98);
    F::telefoneWhatsapp($this->b99);

    $this->p = F::paciente($this->b98, $this->u98->id);
    F::consentir($this->p, 'whatsapp');
    // SUPERADMIN: fixtures das empresas 98 e 99.
    $this->tipo = AppointmentType::withoutGlobalScopes()->create(['business_id' => $this->b98, 'name' => 'Sessão', 'duration_min' => 50]);
    $this->tipo99 = AppointmentType::withoutGlobalScopes()->create(['business_id' => $this->b99, 'name' => 'Sessão', 'duration_min' => 50]);
    $this->marcar = fn (Patient $p, string $inicio, ?int $biz = null, ?int $prof = null, ?int $tipo = null): Appointment => app(AgendaService::class)->marcar($biz ?? $this->b98, [
        'patient_id' => $p->id, 'professional_id' => $prof ?? $this->u98->id, 'type_id' => $tipo ?? $this->tipo->id, 'starts_at' => Carbon::parse($inicio),
    ]);
    $this->pagina = function (string $url): array {
        $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $r = $this->get($url, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);
        $r->assertOk();
        expect($r->json('component'))->toBe('Fisio/Public/Confirmacao');

        return (array) $r->json('props');
    };
});

afterEach(fn () => Carbon::setTestNow());

/** @return list<SendWhatsappMessageJob> */
function fisioConfirmacoesEnviadas(): array
{
    $out = [];
    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) use (&$out) {
        $out[] = $job;

        return true;
    });

    return $out;
}

it('UC-FCF-01 · uma mensagem por atendimento na janela, com link assinado; rodar de novo não repete', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    $a = ($this->marcar)($this->p, '2026-10-14 18:00');
    $b = ($this->marcar)($this->p, '2026-10-15 07:30'); // mesmo paciente, outra sessão dentro das 24 h

    (new EnviarConfirmacaoConsultaJob($this->b98))->handle();
    (new EnviarConfirmacaoConsultaJob($this->b98))->handle();

    $enviados = fisioConfirmacoesEnviadas();
    expect($enviados)->toHaveCount(2);
    $corpos = collect($enviados)->map(fn ($j) => (string) $j->payload['body'])->all();
    foreach ($enviados as $job) {
        expect($job->businessId)->toBe($this->b98)->and($job->whatsappBusinessPhoneId)->toBe($this->fone98)->and($job->kind)->toBe('freeform');
    }
    expect(collect($corpos)->contains(fn ($c) => str_contains($c, "/fisio/consulta/{$a->id}?") && str_contains($c, 'signature=')))->toBeTrue()
        ->and(collect($corpos)->contains(fn ($c) => str_contains($c, "/fisio/consulta/{$b->id}?") && str_contains($c, 'signature=')))->toBeTrue()
        ->and(collect($corpos)->contains(fn ($c) => str_contains($c, 'às 18:00')))->toBeTrue();
});

it('UC-FCF-02 · fora da janela, já confirmado ou sem consentimento de WhatsApp: não envia', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    ($this->marcar)($this->p, '2026-10-14 09:30'); // menos de 2 h
    ($this->marcar)($this->p, '2026-10-16 10:00'); // mais de 24 h
    $confirmado = ($this->marcar)($this->p, '2026-10-14 15:00');
    app(AgendaService::class)->mudarStatus($confirmado, 'confirmado');
    $semConsentimento = F::paciente($this->b98, $this->u98->id);
    ($this->marcar)($semConsentimento, '2026-10-14 16:00');
    // Âncora positiva: um atendimento elegível sai, para provar que o job rodou.
    $ok = ($this->marcar)($this->p, '2026-10-14 17:00');

    (new EnviarConfirmacaoConsultaJob($this->b98))->handle();

    $enviados = fisioConfirmacoesEnviadas();
    expect($enviados)->toHaveCount(1)
        ->and((string) $enviados[0]->payload['body'])->toContain("/fisio/consulta/{$ok->id}?");
});

it('UC-FCF-03 · 98 contra 99: o job de uma empresa só manda os atendimentos dela, pelo número dela', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    $p99 = F::paciente($this->b99, $this->u99->id);
    F::consentir($p99, 'whatsapp');
    $a99 = ($this->marcar)($p99, '2026-10-14 18:00', $this->b99, $this->u99->id, $this->tipo99->id);
    $a98 = ($this->marcar)($this->p, '2026-10-14 18:00');

    (new EnviarConfirmacaoConsultaJob($this->b98))->handle();

    $enviados = fisioConfirmacoesEnviadas();
    expect($enviados)->toHaveCount(1)
        ->and($enviados[0]->whatsappBusinessPhoneId)->toBe($this->fone98)
        ->and((string) $enviados[0]->payload['body'])->toContain("/fisio/consulta/{$a98->id}?")
        ->and((string) $enviados[0]->payload['body'])->not->toContain("/fisio/consulta/{$a99->id}?");
});

it('UC-FCF-04 · abrir o link mostra dia e hora e não muda nada; sem assinatura, adulterado ou empresa sem o pacote não abre', function () {
    $a = ($this->marcar)($this->p, '2026-10-14 18:00');
    $link = app(ConfirmacaoDaConsulta::class)->link($a);

    $props = ($this->pagina)($link);
    expect($props['quando'])->toContain('às 18:00')
        ->and($props['aberta'])->toBeTrue()
        ->and($props['acoes'])->toHaveKeys(['confirmar', 'nao_vou'])
        ->and($a->fresh()->status)->toBe('agendado'); // GET não muda o atendimento

    $this->get("/fisio/consulta/{$a->id}")->assertForbidden();
    $outro = ($this->marcar)($this->p, '2026-10-14 19:00');
    $this->get(str_replace("/fisio/consulta/{$a->id}?", "/fisio/consulta/{$outro->id}?", $link))->assertForbidden();

    F::trocarPacote($this->b98, false);
    $this->get($link)->assertNotFound();
});

it('UC-FCF-05 · "Confirmo" confirma pelo WhatsApp; confirmar de novo não muda nada', function () {
    $a = ($this->marcar)($this->p, '2026-10-14 18:00');
    $props = ($this->pagina)(app(ConfirmacaoDaConsulta::class)->link($a));

    $this->post($props['acoes']['confirmar'])->assertRedirect();
    $a->refresh();
    expect($a->status)->toBe('confirmado')->and($a->confirmation_channel)->toBe('whatsapp');
    $confirmadoEm = (string) $a->confirmed_at;

    $this->post($props['acoes']['confirmar'])->assertRedirect();
    expect($a->fresh()->status)->toBe('confirmado')->and((string) $a->fresh()->confirmed_at)->toBe($confirmadoEm);
    expect(($this->pagina)(app(ConfirmacaoDaConsulta::class)->link($a))['resultado'])->toBe('confirmado');
});

it('UC-FCF-06 · "Não vou poder ir" desmarca dentro do prazo; fora do prazo não desmarca e manda falar com a clínica', function () {
    BookingSettings::daEmpresa($this->b98)->forceFill(['cancel_min_hours' => 24])->save();
    $longe = ($this->marcar)($this->p, '2026-10-16 10:00'); // 50 h antes
    $perto = ($this->marcar)($this->p, '2026-10-14 18:00'); // 10 h antes
    $svc = app(ConfirmacaoDaConsulta::class);

    $this->post($svc->linkDaResposta($longe, 'nao_vou'))->assertRedirect();
    $longe->refresh();
    expect($longe->status)->toBe('cancelado')->and($longe->cancelled_by)->toBe('paciente');

    $this->post($svc->linkDaResposta($perto, 'nao_vou'))->assertRedirect();
    expect($perto->fresh()->status)->toBe('agendado');
    expect(($this->pagina)($svc->link($perto))['resultado'])->toBe('fora_do_prazo');
});
