<?php
// @covers-us US-WA-320

declare(strict_types=1);

use App\Utils\NotificationUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;
use Tests\Support\WithSeededTenant;

/**
 * Aviso de venda do núcleo enviado pela API do WhatsApp em vez de link (US-WA-320).
 *
 * Âncora: SPEC US-WA-320 (critérios 1-4). Caminho real: `NotificationUtil::autoSendNotification`
 * → `NotificadorClienteWhatsapp` → `DespacharMensagemDoSistemaJob` (só o job é falso).
 *
 * Tenant 98 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasColumn('contacts', 'whatsapp_consent')
        || ! Schema::hasColumn('notification_templates', 'auto_send_wa_notif')
        || ! Schema::hasColumn('channels', 'handles_outbound_default')) {
        $this->markTestSkipped('Requer MySQL com notification_templates, consentimento em contacts e channels.handles_*.');
    }
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Bus::fake([DespacharMensagemDoSistemaJob::class]);
    $criador = \App\User::factory()->create(['business_id' => $this->biz->id]);

    DB::table('notification_templates')->where('business_id', $this->biz->id)->where('template_for', 'new_sale')->delete();
    DB::table('notification_templates')->insert([
        'business_id' => $this->biz->id, 'template_for' => 'new_sale', 'whatsapp_text' => 'Obrigado pela compra!',
        'auto_send' => 0, 'auto_send_sms' => 0, 'auto_send_wa_notif' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->canal = (int) Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Aviso venda teste',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW, 'status' => 'active', 'channel_health' => 'healthy',
        'handles_outbound_default' => true, 'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ])->id;
    $this->enviadas = fn () => DB::table('messages')->where('business_id', $this->biz->id)
        ->where('direction', 'outbound')->where('sender_kind', 'system')->get();
    $this->contato = function (?bool $consente) use ($criador) {
        $id = (int) DB::table('contacts')->insertGetId([
            'business_id' => $this->biz->id, 'type' => 'customer', 'name' => 'Cliente Aviso', 'mobile' => '48988887777',
            'whatsapp_consent' => $consente, 'contact_status' => 'active', 'created_by' => $criador->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return \App\Contact::query()->where('business_id', $this->biz->id)->findOrFail($id);
    };
    $this->avisar = function (\App\Contact $c): string {
        $tx = new \App\Transaction(['business_id' => $this->biz->id, 'type' => 'sell']);
        $tx->setRelation('contact', $c);

        return (string) app(NotificationUtil::class)->autoSendNotification($this->biz->id, 'new_sale', $tx, $c);
    };
});

it('US-WA-320 · flag desligada: comportamento de sempre, link wa.me e nada pela API', function () {
    config(['whatsapp.aviso_automatico.enabled' => false]);

    $link = ($this->avisar)(($this->contato)(null));

    expect($link)->toContain('48988887777')->toContain(urlencode('Obrigado pela compra!'));
    expect(($this->enviadas)())->toHaveCount(0);
    Bus::assertNotDispatched(DespacharMensagemDoSistemaJob::class);
});

it('US-WA-320 · flag ligada: envia pela API pelo número da empresa e não devolve link', function () {
    config(['whatsapp.aviso_automatico.enabled' => true]);

    $link = ($this->avisar)(($this->contato)(true));

    expect($link)->toBe('');
    $msgs = ($this->enviadas)();
    expect($msgs)->toHaveCount(1)
        ->and($msgs[0]->body)->toBe('Obrigado pela compra!')
        ->and((int) DB::table('conversations')->where('id', $msgs[0]->conversation_id)->value('channel_id'))->toBe($this->canal);
    Bus::assertDispatched(DespacharMensagemDoSistemaJob::class, fn ($j) => $j->businessId === $this->biz->id && $j->messageId === (int) $msgs[0]->id);
});

it('US-WA-320 · flag ligada, cliente recusou WhatsApp: nem API nem link', function () {
    config(['whatsapp.aviso_automatico.enabled' => true]);

    expect(($this->avisar)(($this->contato)(false)))->toBe('');
    expect(($this->enviadas)())->toHaveCount(0);
});

it('US-WA-320 · flag ligada, empresa sem número de saída: volta ao link manual', function () {
    config(['whatsapp.aviso_automatico.enabled' => true]);
    DB::table('channels')->where('id', $this->canal)->update(['handles_outbound_default' => false]);

    expect(($this->avisar)(($this->contato)(null)))->toContain('48988887777');
    expect(($this->enviadas)())->toHaveCount(0);
});
