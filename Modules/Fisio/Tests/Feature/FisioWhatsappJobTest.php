<?php

declare(strict_types=1);
// Cobre UC-FPS-11, UC-FPS-12, UC-FPS-36 (Fisio/Pacientes/Show.casos.md) e UC-FPN-07, UC-FPN-08 (Fisio/Painel.casos.md).
// @covers-us US-FISIO-009

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Contracts\LinkDoPortal;
use Modules\Fisio\Jobs\EnviarLembreteDiarioJob;
use Modules\Fisio\Jobs\EnviarProgramaWhatsappJob;
use Modules\Fisio\Tests\Support\FisioFixtures as F;
use Modules\Fisio\Tests\Support\LinkDoPortalFalso;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * WhatsApp do Fisio (T-09 / US-FISIO-009) — REUSO do módulo `Whatsapp`, precedente
 * `EnviarLinkAprovacaoWhatsappJob` da OficinaAuto: número de saída padrão da empresa,
 * `businessId` no constructor (job não tem sessão), guarda cross-tenant, consentimento antes de
 * enviar, idempotência. O Fisio não tem canal, driver nem tabela de envio próprios ([W]
 * 2026-10-19: "o WhatsApp já está pronto no sistema"). Contrato: ADR 0437 §13.1 + SPEC US-FISIO-009.
 *
 * Duas mensagens no envio do programa: link primeiro, PIN depois (atrasado) — PIN nunca na mesma
 * mensagem do link.
 *
 * Idempotência por chave PERSISTIDA (não só cache): no máximo 1 lembrete e 1 envio de programa por
 * paciente por dia. O teste mede o efeito (nº de SendWhatsappMessageJob), não onde a chave mora.
 *
 * O link do portal vem do contrato `LinkDoPortal` (ponto de integração com o US-FISIO-007a):
 * aqui um falso; o último caso usa o portal real (PortalTokenService, já no main).
 *
 * Tenant 98 (com pacote) contra 99 (sem). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['whatsapp_business_phones', 'activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config([
        'fisio.whatsapp.teto_por_hora' => 30,
        'fisio.whatsapp.intervalo_min_segundos' => 20,
        'fisio.whatsapp.intervalo_max_segundos' => 90,
        'fisio.whatsapp.silencio' => ['inicio' => '21:00', 'fim' => '07:00'],
        'fisio.whatsapp.pin_delay_segundos' => 60,
    ]);
    Carbon::setTestNow('2026-10-14 08:00:00');
    $this->portal = new LinkDoPortalFalso();
    app()->instance(LinkDoPortal::class, $this->portal);

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98]);
    $this->fone98 = F::telefoneWhatsapp($this->b98);
    F::telefoneWhatsapp($this->b99);

    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->prog = F::programa($this->p, [F::exercicio(null)], $this->u98->id);
    F::consentir($this->p, 'whatsapp');
});

afterEach(fn () => Carbon::setTestNow());

/** @return list<SendWhatsappMessageJob> */
function fisioWaEnviados(): array
{
    $enviados = [];
    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) use (&$enviados) {
        $enviados[] = $job;

        return true;
    });

    return $enviados;
}

it('UC-FPS-11 · "Enviar pelo WhatsApp" enfileira o job com business e programa — não envia no request', function () {
    Bus::fake([EnviarProgramaWhatsappJob::class, SendWhatsappMessageJob::class]);
    F::como($this, $this->u98);

    $this->postJson("/fisio/programas/{$this->prog->id}/enviar")->assertStatus(202)->assertJson(['modo' => 'fila']);

    Bus::assertDispatched(EnviarProgramaWhatsappJob::class,
        fn ($job) => $job->businessId === $this->b98 && $job->programId === $this->prog->id);
    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
    expect($this->portal->emissoes)->toBe([]); // o link só é emitido no job
});

it('UC-FPS-11 · o job manda link e PIN em duas mensagens pelo número do módulo Whatsapp, PIN depois do link', function () {
    Bus::fake([SendWhatsappMessageJob::class]);

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();

    $enviados = fisioWaEnviados();
    expect($enviados)->toHaveCount(2);
    foreach ($enviados as $job) {
        expect($job->businessId)->toBe($this->b98)
            ->and($job->whatsappBusinessPhoneId)->toBe($this->fone98)
            ->and($job->kind)->toBe('freeform');
    }

    $link = collect($enviados)->first(fn ($j) => str_contains($j->payload['body'], '/fisio/p/'));
    $pin = collect($enviados)->first(fn ($j) => ! str_contains($j->payload['body'], '/fisio/p/'));
    expect($link)->not->toBeNull()->and($pin)->not->toBeNull()
        ->and($link->payload['body'])->not->toContain(LinkDoPortalFalso::PIN)
        ->and($pin->payload['body'])->toContain(LinkDoPortalFalso::PIN)
        ->and($pin->delay)->not->toBeNull()
        ->and(Carbon::parse($pin->delay)->greaterThan(Carbon::parse($link->delay ?? now())))->toBeTrue();
    expect($this->portal->emissoes)->toBe([$this->p->id => 1]);
});

it('UC-FPS-11 · envio idempotente: rodar o job duas vezes não manda (nem emite link) duas vezes no mesmo dia', function () {
    Bus::fake([SendWhatsappMessageJob::class]);

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();

    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 2); // 1 link + 1 PIN, não 4
    expect($this->portal->emissoes)->toBe([$this->p->id => 1]);    // não revogou o link já enviado
});
it('UC-FPS-12 · sem disparo para business sem o módulo, nem para programa de outro business', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    $p99 = F::paciente($this->b99, $this->u99->id);
    $prog99 = F::programa($p99, [F::exercicio(null)], $this->u99->id);
    F::consentir($p99, 'whatsapp');

    (new EnviarProgramaWhatsappJob($this->b99, $prog99->id))->handle();   // 99 sem pacote
    (new EnviarProgramaWhatsappJob($this->b98, $prog99->id))->handle();   // cross-tenant
    (new EnviarLembreteDiarioJob($this->b99))->handle();

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
    expect($this->portal->emissoes)->toBe([]);

    // Controle positivo: o mesmo job, com o programa da própria empresa, envia.
    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 2);
});

it('UC-FPS-12 · consentimento de WhatsApp revogado, nunca dado ou de versão antiga impede envio e lembrete', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'whatsapp')->update(['revoked_at' => now()]);
    $semConsentimento = F::paciente($this->b98, $this->u98->id);
    F::programa($semConsentimento, [F::exercicio(null)], $this->u98->id);
    $versaoVelha = F::paciente($this->b98, $this->u98->id);
    $progVelha = F::programa($versaoVelha, [F::exercicio(null)], $this->u98->id);
    F::consentir($versaoVelha, 'whatsapp', 'wa-v0');

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    (new EnviarProgramaWhatsappJob($this->b98, $progVelha->id))->handle();
    (new EnviarLembreteDiarioJob($this->b98))->handle();

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
    expect($this->portal->emissoes)->toBe([]);
});

it('UC-FPS-12 · paciente bloqueado, sem telefone ou com opt-out de WhatsApp no contato não recebe', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    DB::table('fisio_patients')->where('id', $this->p->id)->update(['blocked_at' => now()]);
    $semFone = F::paciente($this->b98, $this->u98->id, ['contact_id' => F::contato($this->b98, $this->u98->id, 'Sem fone', '')]);
    $progSemFone = F::programa($semFone, [F::exercicio(null)], $this->u98->id);
    F::consentir($semFone, 'whatsapp');
    $optOut = F::paciente($this->b98, $this->u98->id);
    $progOptOut = F::programa($optOut, [F::exercicio(null)], $this->u98->id);
    F::consentir($optOut, 'whatsapp');
    DB::table('contacts')->where('id', $optOut->contact_id)->update(['whatsapp_consent' => false]);

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    (new EnviarProgramaWhatsappJob($this->b98, $progSemFone->id))->handle();
    (new EnviarProgramaWhatsappJob($this->b98, $progOptOut->id))->handle();
    (new EnviarLembreteDiarioJob($this->b98))->handle();

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
});

it('UC-FPS-36 · sem canal ativo no módulo Whatsapp: nada sai sozinho e a ficha devolve os links wa.me para envio manual', function () {
    Bus::fake([EnviarProgramaWhatsappJob::class, SendWhatsappMessageJob::class]);
    DB::table('whatsapp_business_phones')->where('id', $this->fone98)->update(['handles_outbound_default' => false]);
    F::como($this, $this->u98);

    $r = $this->postJson("/fisio/programas/{$this->prog->id}/enviar")->assertOk()->assertJson(['modo' => 'manual']);

    expect($r->json('wa_me.link'))->toStartWith('https://wa.me/5548999990000?text=')
        ->and(rawurldecode((string) $r->json('wa_me.link')))->toContain('/fisio/p/')->not->toContain(LinkDoPortalFalso::PIN)
        ->and(rawurldecode((string) $r->json('wa_me.codigo')))->toContain(LinkDoPortalFalso::PIN);
    Bus::assertNotDispatched(EnviarProgramaWhatsappJob::class);

    // O job (retry de fila, por exemplo) também não envia sem canal; nem o lembrete.
    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    (new EnviarLembreteDiarioJob($this->b98))->handle();
    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
});

it('UC-FPS-36 · envio manual também exige consentimento, e programa de outra clínica é 404', function () {
    Bus::fake([EnviarProgramaWhatsappJob::class, SendWhatsappMessageJob::class]);
    DB::table('whatsapp_business_phones')->where('id', $this->fone98)->update(['handles_outbound_default' => false]);
    DB::table('fisio_consents')->where('patient_id', $this->p->id)->update(['revoked_at' => now()]);
    $p99 = F::paciente($this->b99, $this->u99->id);
    $prog99 = F::programa($p99, [F::exercicio(null)], $this->u99->id);
    F::como($this, $this->u98);

    $this->postJson("/fisio/programas/{$this->prog->id}/enviar")->assertStatus(422)->assertJson(['erro' => 'sem_consentimento_whatsapp']);
    $this->postJson("/fisio/programas/{$prog99->id}/enviar")->assertNotFound();
    expect($this->portal->emissoes)->toBe([]);
});
it('UC-FPN-07 · lembrete diário: no máximo 1 por paciente por dia, mesmo rodando o job 3 vezes', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    $outro = F::paciente($this->b98, $this->u98->id);
    F::programa($outro, [F::exercicio(null)], $this->u98->id);
    F::consentir($outro, 'whatsapp');

    foreach ([1, 2, 3] as $_) {
        (new EnviarLembreteDiarioJob($this->b98))->handle();
    }
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 2); // 1 por paciente

    // Controle: no dia seguinte, novo lembrete.
    Carbon::setTestNow('2026-10-15 08:00:00');
    (new EnviarLembreteDiarioJob($this->b98))->handle();
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 4);
});

it('UC-FPN-07 · quem já fez o check-in do dia, ou não tem programa ativo, não recebe lembrete', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    F::checkin($this->p, $this->prog, '2026-10-14', F::itens($this->prog), 2);
    $semPrograma = F::paciente($this->b98, $this->u98->id);
    F::consentir($semPrograma, 'whatsapp');

    (new EnviarLembreteDiarioJob($this->b98))->handle();

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
});

it('UC-FPN-07 · o agendador enfileira um lembrete por business com o módulo, e nenhum para quem não tem', function () {
    Bus::fake([EnviarLembreteDiarioJob::class]);
    F::paciente($this->b99, $this->u99->id); // 99 tem paciente, mas não tem o pacote

    $this->artisan('fisio:lembretes')->assertSuccessful();

    Bus::assertDispatched(EnviarLembreteDiarioJob::class, fn ($j) => $j->businessId === $this->b98);
    Bus::assertNotDispatched(EnviarLembreteDiarioJob::class, fn ($j) => $j->businessId === $this->b99);
});

it('UC-FPN-08 · lembrete no horário de silêncio é agendado para o fim do silêncio, não enviado na hora', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    Carbon::setTestNow(Carbon::parse('2026-10-14 22:30:00', 'America/Sao_Paulo'));

    (new EnviarLembreteDiarioJob($this->b98))->handle();

    $saida = Carbon::parse(fisioWaEnviados()[0]->delay)->setTimezone('America/Sao_Paulo');
    expect($saida->format('Y-m-d H:i'))->toBe('2026-10-15 07:00');
});

it('UC-FPN-08 · ritmo: teto por hora no número e intervalo mínimo entre mensagens', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    config(['fisio.whatsapp.teto_por_hora' => 2, 'fisio.whatsapp.intervalo_min_segundos' => 30, 'fisio.whatsapp.intervalo_max_segundos' => 30]);
    foreach ([1, 2] as $_) {
        $q = F::paciente($this->b98, $this->u98->id);
        F::programa($q, [F::exercicio(null)], $this->u98->id);
        F::consentir($q, 'whatsapp');
    }

    (new EnviarLembreteDiarioJob($this->b98))->handle();

    $saidas = collect(fisioWaEnviados())->map(fn ($j) => Carbon::parse($j->delay))->sort()->values();
    expect($saidas)->toHaveCount(3)
        ->and($saidas[0]->diffInSeconds($saidas[1]))->toBeGreaterThanOrEqual(30)
        ->and($saidas[2]->greaterThan($saidas[0]->copy()->addHour()))->toBeTrue(); // 3º fora da janela de 1 h
});

it('UC-FPN-08 · canal desconectado ou banido no módulo Whatsapp: nenhum lembrete nem envio automático', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    DB::table('whatsapp_business_phones')->where('id', $this->fone98)->update(['driver_health' => 'disconnected']);

    (new EnviarLembreteDiarioJob($this->b98))->handle();
    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
});

it('UC-FPS-11 · integração com o portal real (US-FISIO-007a): o link enviado abre o portal', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    app()->forgetInstance(LinkDoPortal::class);
    app()->bind(LinkDoPortal::class, \Modules\Fisio\Services\Whatsapp\LinkDoPortalPorToken::class);

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();

    $link = collect(fisioWaEnviados())->first(fn ($j) => str_contains($j->payload['body'], '/fisio/p/'));
    preg_match('#/fisio/p/([^\s]+)#', $link->payload['body'], $m);
    expect(F::portalProps($this, $m[1])['erro'])->toBeNull();
});
