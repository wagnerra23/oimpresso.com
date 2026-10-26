<?php
// @covers-us US-WA-319

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;
use Modules\Whatsapp\Services\Notify\ResultadoNotificacao;
use Tests\Support\WithSeededTenant;

/**
 * Notificador reutilizável de aviso automático por WhatsApp (US-WA-319).
 *
 * Âncora: SPEC US-WA-319 (critérios 1-5) e a regra LGPD do sistema —
 * `Contact::canReceiveWhatsappNotification()`: opt-out explícito bloqueia, null permite.
 *
 * Tenant 98 contra 99 (ADR 0358). Só o job é falso (Bus::fake). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasColumn('contacts', 'whatsapp_consent')
        || ! Schema::hasTable('whatsapp_business_phones')) {
        $this->markTestSkipped('Requer MySQL com consentimento em contacts e whatsapp_business_phones.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Bus::fake([SendWhatsappMessageJob::class]);
    $this->criador = \App\User::factory()->create(['business_id' => $this->biz->id]);
    $this->notificador = app(NotificadorClienteWhatsapp::class);
});

function notifFone(int $biz, array $flags): int
{
    return (int) DB::table('whatsapp_business_phones')->insertGetId(array_merge([
        'business_id' => $biz, 'phone_uuid' => (string) Str::uuid(), 'label' => 'Notif teste',
        'driver' => 'meta_cloud', 'fallback_driver' => 'meta_cloud',
        'handles_outbound_default' => false, 'handles_billing' => false, 'handles_repair_status' => false,
        'created_at' => now(), 'updated_at' => now(),
    ], $flags));
}

/** "Sem celular" é string vazia: `contacts.mobile` é NOT NULL no schema. */
function notifContato(int $biz, int $criadoPor, string $mobile, ?bool $consente): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente Notif', 'mobile' => $mobile,
        'whatsapp_consent' => $consente, 'contact_status' => 'active', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return list<SendWhatsappMessageJob> */
function notifJobs(): array
{
    return Bus::dispatched(SendWhatsappMessageJob::class)->values()->all();
}

it('US-WA-319 · envia texto e um job por anexo, pelo número de saída da própria empresa', function () {
    $fone = notifFone($this->biz->id, ['handles_outbound_default' => true]);
    notifFone($this->outra->id, ['handles_outbound_default' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '48988887777', null);

    $r = $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'Sua compra foi registrada.', [
        ['url' => 'https://exemplo.test/fatura.pdf', 'type' => 'document', 'caption' => 'Fatura'],
    ]);

    expect($r)->toBe(ResultadoNotificacao::Enviado);
    $jobs = notifJobs();
    expect($jobs)->toHaveCount(2);
    expect($jobs[0]->businessId)->toBe($this->biz->id)
        ->and($jobs[0]->whatsappBusinessPhoneId)->toBe($fone)
        ->and($jobs[0]->to)->toBe('48988887777')
        ->and($jobs[0]->kind)->toBe('freeform')
        ->and($jobs[0]->payload['body'])->toBe('Sua compra foi registrada.');
    expect($jobs[1]->kind)->toBe('media')
        ->and($jobs[1]->whatsappBusinessPhoneId)->toBe($fone)
        ->and($jobs[1]->payload)->toBe(['url' => 'https://exemplo.test/fatura.pdf', 'type' => 'document', 'caption' => 'Fatura']);
});

it('US-WA-319 · cobrança usa o número marcado para cobrança, não o de saída padrão', function () {
    notifFone($this->biz->id, ['handles_outbound_default' => true]);
    $financeiro = notifFone($this->biz->id, ['handles_billing' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '48988887777', true);

    $r = $this->notificador->notificar($this->biz->id, $cliente, 'billing', 'Seu boleto vence amanhã.');

    expect($r)->toBe(ResultadoNotificacao::Enviado);
    expect(notifJobs()[0]->whatsappBusinessPhoneId)->toBe($financeiro);
});

it('US-WA-319 · contato de outra empresa, recusa LGPD, sem celular ou número caído: não envia e diz o motivo', function () {
    $fone = notifFone($this->biz->id, ['handles_outbound_default' => true]);
    $daOutra = notifContato($this->outra->id, $this->criador->id, '48911110000', true);
    $recusou = notifContato($this->biz->id, $this->criador->id, '48911112222', false);
    $semCelular = notifContato($this->biz->id, $this->criador->id, '', true);
    $ok = notifContato($this->biz->id, $this->criador->id, '48933334444', null);

    expect($this->notificador->notificar($this->biz->id, $daOutra, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::ContatoNaoEncontrado)
        ->and($this->notificador->notificar($this->biz->id, $recusou, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::RecusouWhatsapp)
        ->and($this->notificador->notificar($this->biz->id, $semCelular, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::SemCelular);

    DB::table('whatsapp_business_phones')->where('id', $fone)->update(['driver_health' => 'banned']);
    expect($this->notificador->notificar($this->biz->id, $ok, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::SemNumeroConfigurado);
    expect(notifJobs())->toHaveCount(0);

    // Controle: o mesmo cliente, com o número de volta, recebe.
    DB::table('whatsapp_business_phones')->where('id', $fone)->update(['driver_health' => 'healthy']);
    expect($this->notificador->notificar($this->biz->id, $ok, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::Enviado);
    expect(notifJobs())->toHaveCount(1);
});

it('US-WA-319 · número de outra empresa não serve: sem número próprio, não envia', function () {
    notifFone($this->outra->id, ['handles_outbound_default' => true, 'handles_billing' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '48988887777', true);

    expect($this->notificador->notificar($this->biz->id, $cliente, 'billing', 'x'))->toBe(ResultadoNotificacao::SemNumeroConfigurado);
    expect(notifJobs())->toHaveCount(0);
});

it('US-WA-319 · anexo sem URL absoluta ou de tipo desconhecido é erro de quem chamou', function () {
    notifFone($this->biz->id, ['handles_outbound_default' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '48988887777', true);

    expect(fn () => $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'x', [['url' => '/storage/a.pdf']]))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'x', [['url' => 'https://a.test/a.mp3', 'type' => 'audio']]))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => $this->notificador->notificar($this->biz->id, $cliente, 'evento_inventado', 'x'))
        ->toThrow(\InvalidArgumentException::class);
    expect(notifJobs())->toHaveCount(0);
});
