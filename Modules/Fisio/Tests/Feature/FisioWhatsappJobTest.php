<?php

declare(strict_types=1);
// Cobre UC-FPS-11, UC-FPS-12 (Fisio/Pacientes/Show.casos.md) e UC-FPN-07 (Fisio/Painel.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Jobs\EnviarLembreteDiarioJob;
use Modules\Fisio\Jobs\EnviarProgramaWhatsappJob;
use Modules\Fisio\Tests\Support\FisioFixtures as F;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * WhatsApp do Fisio (T-09) — reuso do módulo Whatsapp, precedente
 * `EnviarLinkAprovacaoWhatsappJob` da OficinaAuto: `businessId` no constructor (job não tem
 * sessão), guarda cross-tenant, consentimento antes de enviar, idempotência.
 *
 * Duas mensagens no envio do programa, como na aprovação de OS: link primeiro, PIN depois
 * (atrasado) — PIN nunca na mesma mensagem do link.
 *
 * Idempotência por chave PERSISTIDA (não só cache): o lembrete é no máximo 1 por paciente
 * por dia. ⚠️ SUPOSIÇÃO de nome: tabela/coluna de controle à escolha do implementador; o teste
 * mede o efeito (número de SendWhatsappMessageJob), não a tabela.
 *
 * Tenant 98 (com pacote) contra 99 (sem). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['whatsapp_business_phones'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 08:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98]);
    F::telefoneWhatsapp($this->b98);
    F::telefoneWhatsapp($this->b99);

    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->prog = F::programa($this->p, [F::exercicio(null)], $this->u98->id);
    F::consentir($this->p, 'whatsapp');
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FPS-11 · "Enviar pelo WhatsApp" enfileira o job com business e programa — não envia no request', function () {
    Bus::fake([EnviarProgramaWhatsappJob::class]);
    F::como($this, $this->u98);

    $this->postJson("/fisio/programas/{$this->prog->id}/enviar")->assertStatus(202);

    Bus::assertDispatched(EnviarProgramaWhatsappJob::class,
        fn ($job) => $job->businessId === $this->b98 && $job->programId === $this->prog->id);
});

it('UC-FPS-11 · o job manda link e PIN em duas mensagens, PIN atrasado; o link abre o portal', function () {
    Bus::fake([SendWhatsappMessageJob::class]);

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();

    $enviados = [];
    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) use (&$enviados) {
        $enviados[] = $job;

        return $job->businessId === $this->b98 && $job->kind === 'freeform';
    });
    expect($enviados)->toHaveCount(2);

    $link = collect($enviados)->first(fn ($j) => str_contains($j->payload['body'], '/fisio/p/'));
    $pin = collect($enviados)->first(fn ($j) => ! str_contains($j->payload['body'], '/fisio/p/'));
    expect($link)->not->toBeNull()->and($pin)->not->toBeNull()
        ->and($pin->delay)->not->toBeNull()
        ->and($link->payload['body'])->not->toMatch('/\bPIN\b.*\d{4}/');

    preg_match('#/fisio/p/([^\s]+)#', $link->payload['body'], $m);
    expect(F::portalProps($this, $m[1])['erro'])->toBeNull();
});

it('UC-FPS-11 · envio idempotente: rodar o job duas vezes não manda duas vezes no mesmo dia', function () {
    Bus::fake([SendWhatsappMessageJob::class]);

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();

    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 2); // 1 link + 1 PIN, não 4
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
});

it('UC-FPS-12 · consentimento de WhatsApp revogado (ou nunca dado) impede envio e lembrete', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'whatsapp')->update(['revoked_at' => now()]);
    $semConsentimento = F::paciente($this->b98, $this->u98->id);
    F::programa($semConsentimento, [F::exercicio(null)], $this->u98->id);

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    (new EnviarLembreteDiarioJob($this->b98))->handle();

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
});

it('UC-FPS-12 · paciente bloqueado ou sem telefone não recebe', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    DB::table('fisio_patients')->where('id', $this->p->id)->update(['blocked_at' => now()]);
    $semFone = F::paciente($this->b98, $this->u98->id, ['contact_id' => F::contato($this->b98, $this->u98->id, 'Sem fone', null)]);
    $progSemFone = F::programa($semFone, [F::exercicio(null)], $this->u98->id);
    F::consentir($semFone, 'whatsapp');

    (new EnviarProgramaWhatsappJob($this->b98, $this->prog->id))->handle();
    (new EnviarProgramaWhatsappJob($this->b98, $progSemFone->id))->handle();

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
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

    $this->artisan('fisio:lembretes')->assertSuccessful();

    Bus::assertDispatched(EnviarLembreteDiarioJob::class, fn ($j) => $j->businessId === $this->b98);
    Bus::assertNotDispatched(EnviarLembreteDiarioJob::class, fn ($j) => $j->businessId === $this->b99);
});
