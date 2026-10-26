<?php
// @covers-us US-WA-320

declare(strict_types=1);

use App\Utils\NotificationUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Tests\Support\WithSeededTenant;

/**
 * Aviso de venda do núcleo enviado pela API do WhatsApp em vez de link (US-WA-320).
 *
 * Âncora: SPEC US-WA-320 (critérios 1-4). Caminho real: `NotificationUtil::autoSendNotification`
 * → `NotificadorClienteWhatsapp` → `SendWhatsappMessageJob` (só o job é falso).
 *
 * Tenant 98 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasColumn('contacts', 'whatsapp_consent')
        || ! Schema::hasColumn('notification_templates', 'auto_send_wa_notif')
        || ! Schema::hasTable('whatsapp_business_phones')) {
        $this->markTestSkipped('Requer MySQL com notification_templates, consentimento em contacts e whatsapp_business_phones.');
    }
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Bus::fake([SendWhatsappMessageJob::class]);
    $criador = \App\User::factory()->create(['business_id' => $this->biz->id]);

    DB::table('notification_templates')->where('business_id', $this->biz->id)->where('template_for', 'new_sale')->delete();
    DB::table('notification_templates')->insert([
        'business_id' => $this->biz->id, 'template_for' => 'new_sale', 'whatsapp_text' => 'Obrigado pela compra!',
        'auto_send' => 0, 'auto_send_sms' => 0, 'auto_send_wa_notif' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->fone = (int) DB::table('whatsapp_business_phones')->insertGetId([
        'business_id' => $this->biz->id, 'phone_uuid' => (string) Str::uuid(), 'label' => 'Aviso venda teste',
        'driver' => 'meta_cloud', 'fallback_driver' => 'meta_cloud', 'handles_outbound_default' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
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
    expect(Bus::dispatched(SendWhatsappMessageJob::class)->count())->toBe(0);
});

it('US-WA-320 · flag ligada: envia pela API pelo número da empresa e não devolve link', function () {
    config(['whatsapp.aviso_automatico.enabled' => true]);

    $link = ($this->avisar)(($this->contato)(true));

    expect($link)->toBe('');
    $jobs = Bus::dispatched(SendWhatsappMessageJob::class)->values()->all();
    expect($jobs)->toHaveCount(1);
    expect($jobs[0]->businessId)->toBe($this->biz->id)
        ->and($jobs[0]->whatsappBusinessPhoneId)->toBe($this->fone)
        ->and($jobs[0]->kind)->toBe('freeform')
        ->and($jobs[0]->payload['body'])->toBe('Obrigado pela compra!');
});

it('US-WA-320 · flag ligada, cliente recusou WhatsApp: nem API nem link', function () {
    config(['whatsapp.aviso_automatico.enabled' => true]);

    expect(($this->avisar)(($this->contato)(false)))->toBe('');
    expect(Bus::dispatched(SendWhatsappMessageJob::class)->count())->toBe(0);
});

it('US-WA-320 · flag ligada, empresa sem número de saída: volta ao link manual', function () {
    config(['whatsapp.aviso_automatico.enabled' => true]);
    DB::table('whatsapp_business_phones')->where('id', $this->fone)->update(['handles_outbound_default' => false]);

    expect(($this->avisar)(($this->contato)(null)))->toContain('48988887777');
    expect(Bus::dispatched(SendWhatsappMessageJob::class)->count())->toBe(0);
});
