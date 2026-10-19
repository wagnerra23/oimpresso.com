<?php

declare(strict_types=1);
// @covers-us US-FISIO-009
// Cobre UC-FPN-09 (Fisio/Painel.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Services\Whatsapp\MensagensFisio;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fallback humano do WhatsApp (etapa 02 T-09e). O Fisio não tem tabela de envio ([W] 2026-10-19):
 * o painel cruza a reserva do dia (`activity_log`, gravada por EnvioWhatsappFisio::reservar) com o
 * que o módulo Whatsapp gravou em `whatsapp_messages`. Aqui a reserva é a real; as mensagens são
 * inseridas como o SendWhatsappMessageJob as grava (uma linha por tentativa da fila).
 *
 * Hoje = 2026-10-14 12:00 (depois do horário do lembrete, 08:30). Tenant 98 contra 99.
 * ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['whatsapp_business_phones', 'whatsapp_messages', 'activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.whatsapp.lembrete_horario' => '08:30', 'fisio.whatsapp.silencio' => ['inicio' => '21:00', 'fim' => '07:00']]);
    Carbon::setTestNow('2026-10-14 12:00:00');

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->fone98 = F::telefoneWhatsapp($this->b98);
    $this->fone99 = F::telefoneWhatsapp($this->b99);
});

afterEach(fn () => Carbon::setTestNow());

/** Mensagem como o SendWhatsappMessageJob grava: conversa pelo telefone normalizado + 1 linha por tentativa. */
function fpnMensagem(int $biz, int $phoneId, Patient $p, string $body, string $status, ?string $motivo = null): void
{
    $fone = (string) DB::table('contacts')->where('business_id', $biz)->where('id', $p->contact_id)->value('mobile');
    $normalizado = '+55'.preg_replace('/\D/', '', $fone);
    $conversa = DB::table('whatsapp_conversations')->where('business_id', $biz)->where('customer_phone', $normalizado)->value('id')
        ?? DB::table('whatsapp_conversations')->insertGetId([
            'business_id' => $biz, 'whatsapp_business_phone_id' => $phoneId, 'customer_phone' => $normalizado,
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
    DB::table('whatsapp_messages')->insert([
        'business_id' => $biz, 'whatsapp_business_phone_id' => $phoneId, 'conversation_id' => $conversa,
        'direction' => 'outbound', 'provider' => 'meta_cloud', 'type' => 'text', 'body' => $body,
        'status' => $status, 'failed_reason' => $motivo, 'sender_kind' => 'system', 'created_at' => now(),
    ]);
}

/** Lembrete exato que o job manda para este paciente (o painel casa pelo texto com o nome). */
function fpnLembrete(Patient $p): string
{
    $envio = app(EnvioWhatsappFisio::class);

    return MensagensFisio::lembrete($envio->primeiroNome($p), $envio->nomeDaClinica((int) $p->business_id));
}

function fpnPacienteComLembrete(int $biz, int $userId, bool $consentir = true, string $mobile = '48999990000', string $nome = 'Paciente Fixture'): Patient
{
    $p = F::paciente($biz, $userId, ['contact_id' => F::contato($biz, $userId, $nome, $mobile)]);
    F::programa($p, [F::exercicio(null)], $userId);
    if ($consentir) {
        F::consentir($p, 'whatsapp');
    }
    app(EnvioWhatsappFisio::class)->reservar($p, EnvioWhatsappFisio::LEMBRETE, fn () => true);

    return $p;
}

function fpnFalhos($t, $user): \Illuminate\Support\Collection
{
    return collect(F::props($t, $user, '/fisio', 'Fisio/Painel', 'envios_falhos'))->keyBy(fn ($i) => $i['tipo'].':'.$i['patient_id']);
}

it('UC-FPN-09 · lembrete cujas tentativas falharam aparece com o link wa.me do mesmo texto', function () {
    $p = fpnPacienteComLembrete($this->b98, $this->u98->id);
    $texto = fpnLembrete($p);
    fpnMensagem($this->b98, $this->fone98, $p, $texto, 'failed', 'timeout');
    fpnMensagem($this->b98, $this->fone98, $p, $texto, 'failed', 'session_lost');

    $item = fpnFalhos($this, $this->u98)->get('lembrete:'.$p->id);

    expect($item)->not->toBeNull()
        ->and($item['motivo'])->toBe('falhou')
        ->and($item['sem_link'])->toBeNull()
        ->and($item['wa_me'])->toBe([MensagensFisio::waMe('48999990000', $texto)]);
});

it('UC-FPN-09 · programa: só a mensagem que falhou vira link; a que chegou não', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    $prog = F::programa($p, [F::exercicio(null)], $this->u98->id);
    F::consentir($p, 'whatsapp');
    app(EnvioWhatsappFisio::class)->reservar($p, EnvioWhatsappFisio::PROGRAMA, fn () => true, ['program_id' => $prog->id]);
    $envio = app(EnvioWhatsappFisio::class);
    $link = MensagensFisio::link($envio->primeiroNome($p), $envio->nomeDaClinica($this->b98), 'https://exemplo/fisio/p/tok');
    fpnMensagem($this->b98, $this->fone98, $p, $link, 'failed', 'erro');
    fpnMensagem($this->b98, $this->fone98, $p, MensagensFisio::pin('1234'), 'sent');

    $item = fpnFalhos($this, $this->u98)->get('programa:'.$p->id);

    expect($item['motivo'])->toBe('falhou')
        ->and($item['wa_me'])->toBe([MensagensFisio::waMe('48999990000', $link)]);
});

it('UC-FPN-09 · envio que chegou em alguma tentativa não aparece', function () {
    $p = fpnPacienteComLembrete($this->b98, $this->u98->id);
    fpnMensagem($this->b98, $this->fone98, $p, fpnLembrete($p), 'failed', 'timeout');
    fpnMensagem($this->b98, $this->fone98, $p, fpnLembrete($p), 'sent');

    // Controle positivo: outro paciente com falha aparece — a lista não está vazia por acaso.
    $outro = fpnPacienteComLembrete($this->b98, $this->u98->id, mobile: '48988880000');
    fpnMensagem($this->b98, $this->fone98, $outro, fpnLembrete($outro), 'failed', 'timeout');

    $lista = fpnFalhos($this, $this->u98);
    expect($lista->has('lembrete:'.$p->id))->toBeFalse()
        ->and($lista->has('lembrete:'.$outro->id))->toBeTrue();
});

it('UC-FPN-09 · falha de outra clínica não aparece no painel da 98', function () {
    $p99 = fpnPacienteComLembrete($this->b99, $this->u99->id);
    fpnMensagem($this->b99, $this->fone99, $p99, fpnLembrete($p99), 'failed', 'timeout');
    $p98 = fpnPacienteComLembrete($this->b98, $this->u98->id);
    fpnMensagem($this->b98, $this->fone98, $p98, fpnLembrete($p98), 'failed', 'timeout');

    $ids = fpnFalhos($this, $this->u98)->pluck('patient_id')->all();

    expect($ids)->toContain($p98->id)->not->toContain($p99->id);
});

it('UC-FPN-09 · paciente sem consentimento de WhatsApp aparece sem link (LGPD)', function () {
    $p = fpnPacienteComLembrete($this->b98, $this->u98->id, consentir: false);
    fpnMensagem($this->b98, $this->fone98, $p, fpnLembrete($p), 'failed', 'timeout');

    $item = fpnFalhos($this, $this->u98)->get('lembrete:'.$p->id);

    expect($item)->not->toBeNull()
        ->and($item['wa_me'])->toBe([])
        ->and($item['sem_link'])->toBe('sem_consentimento');
});

it('UC-FPN-09 · sem fisio.editar a lista vem sem link (o programa leva o código do portal)', function () {
    $p = fpnPacienteComLembrete($this->b98, $this->u98->id);
    fpnMensagem($this->b98, $this->fone98, $p, fpnLembrete($p), 'failed', 'timeout');
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());

    $item = fpnFalhos($this, $leitor)->get('lembrete:'.$p->id);

    expect($item['wa_me'])->toBe([])->and($item['sem_link'])->toBe('sem_permissao');
});

it('UC-FPN-09 · celular compartilhado: a falha de um paciente não vira falha do outro', function () {
    $irmao1 = fpnPacienteComLembrete($this->b98, $this->u98->id, mobile: '48977770000', nome: 'Ana Fixture');
    $irmao2 = fpnPacienteComLembrete($this->b98, $this->u98->id, mobile: '48977770000', nome: 'Bia Fixture');
    fpnMensagem($this->b98, $this->fone98, $irmao1, fpnLembrete($irmao1), 'failed', 'timeout');
    fpnMensagem($this->b98, $this->fone98, $irmao2, fpnLembrete($irmao2), 'sent');

    $lista = fpnFalhos($this, $this->u98);

    expect($lista->has('lembrete:'.$irmao1->id))->toBeTrue()
        ->and($lista->has('lembrete:'.$irmao2->id))->toBeFalse();
});

it('UC-FPN-09 · canal da clínica caído: quem ficou sem lembrete aparece; sem consentimento, não', function () {
    DB::table('whatsapp_business_phones')->where('id', $this->fone98)->update(['driver_health' => 'disconnected']);
    $p = F::paciente($this->b98, $this->u98->id);
    F::programa($p, [F::exercicio(null)], $this->u98->id);
    F::consentir($p, 'whatsapp');
    $semConsentimento = F::paciente($this->b98, $this->u98->id);
    F::programa($semConsentimento, [F::exercicio(null)], $this->u98->id);

    $lista = fpnFalhos($this, $this->u98);
    $item = $lista->get('lembrete:'.$p->id);

    expect($item)->not->toBeNull()
        ->and($item['motivo'])->toBe('sem_canal')
        ->and($item['wa_me'][0])->toStartWith('https://wa.me/5548999990000?text=')
        ->and($lista->has('lembrete:'.$semConsentimento->id))->toBeFalse();
});
