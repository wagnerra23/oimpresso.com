<?php

declare(strict_types=1);

// @covers-us US-NOTIF-002 — F2, sem ativação da Page em produção.
use App\NotificationTemplate;
use App\Services\FeatureFlagService;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('notification_templates')) {
        $this->markTestSkipped('Schema UltimatePOS ausente; executar na lane MySQL.');
    }
    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['send_notification'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'business.id' => $this->business->id,
        'business.enabled_modules' => []]);
    $this->inertiaHeaders = ['X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request())];
});

function notifDualFlag(bool $enabled): void
{
    test()->mock(FeatureFlagService::class, fn ($mock) => $mock->shouldReceive('isOn')
        ->with('useV2NotificationTemplates', ['business_id' => 98])->andReturn($enabled));
}

test('UC-NOT-02 F2 flag OFF preserva Blade e os três grupos', function () {
    notifDualFlag(false);
    $this->get('/notification-templates')->assertOk()->assertViewIs('notification_template.index')
        ->assertViewHasAll(['general_notifications', 'customer_notifications', 'supplier_notifications']);
});

test('UC-NOT-02 F2 flag ON responde Inertia com três grupos adiados', function () {
    notifDualFlag(true);
    $r = $this->withHeaders($this->inertiaHeaders)->get('/notification-templates')->assertOk();
    expect($r->json('component'))->toBe('NotificationTemplate/Index');
    expect($r->json('deferredProps.default'))->toEqualCanonicalizing([
        'general_notifications', 'customer_notifications', 'supplier_notifications',
    ]);
    expect($r->json('props'))->not->toHaveKey('customer_notifications');
});

test('UC-NOT-26 F2 partial reload traz os campos apenas do próprio tenant', function () {
    notifDualFlag(true);
    $outro = $this->seededSupportClientTenant();
    NotificationTemplate::updateOrCreate(['business_id' => $this->business->id, 'template_for' => 'new_sale'],
        ['subject' => 'Meu modelo F2', 'email_body' => '<p>Meu</p>']);
    NotificationTemplate::updateOrCreate(['business_id' => $outro->id, 'template_for' => 'new_sale'],
        ['subject' => 'Alheio F2', 'email_body' => '<p>Alheio</p>']);
    $r = $this->withHeaders($this->inertiaHeaders + [
        'X-Inertia-Partial-Component' => 'NotificationTemplate/Index',
        'X-Inertia-Partial-Data' => 'customer_notifications',
    ])->get('/notification-templates')->assertOk();
    $modelo = $r->json('props.customer_notifications.new_sale');
    expect($modelo['subject'])->toBe('Meu modelo F2');
    expect($modelo)->toHaveKeys(['subject', 'email_body', 'sms_body', 'whatsapp_text',
        'auto_send', 'auto_send_sms', 'auto_send_wa_notif', 'cc', 'bcc']);
    expect($r->getContent())->not->toContain('Alheio F2');
    expect($r->json('props.customer_notifications'))->not->toHaveKey('new_booking');
});

test('UC-NOT-23 F2 conserva os modelos injetados pelos módulos', function () {
    notifDualFlag(true);
    $this->partialMock(ModuleUtil::class, function ($mock) {
        $mock->shouldReceive('getModuleData')->with('notification_list', ['notification_for' => 'customer'])
            ->andReturn([['custom_module' => ['name' => 'Modelo do módulo', 'extra_tags' => []]]]);
        $mock->shouldReceive('getModuleData')->with('notification_list', ['notification_for' => 'supplier'])
            ->andReturn([]);
    });
    $r = $this->withHeaders($this->inertiaHeaders + [
        'X-Inertia-Partial-Component' => 'NotificationTemplate/Index',
        'X-Inertia-Partial-Data' => 'customer_notifications',
    ])->get('/notification-templates')->assertOk();
    expect($r->json('props.customer_notifications.custom_module.name'))->toBe('Modelo do módulo');
});

test('UC-NOT-01 F2 nega Inertia sem send_notification antes de avaliar flag', function () {
    $this->mock(FeatureFlagService::class, fn ($mock) => $mock->shouldNotReceive('isOn'));
    $this->actingAs($this->usuarioComPermissoes([], $this->business))
        ->withHeaders($this->inertiaHeaders)->get('/notification-templates')->assertForbidden();
});


test('F2 flag ausente fica OFF no serviço real para qualquer tenant', function () {
    Cache::forget('growthbook.features');
    Http::fake(['*' => Http::response(['features' => []])]);
    config(['feature-flags.forced_on' => '']);
    expect(app(FeatureFlagService::class)->isOn('useV2NotificationTemplates', ['business_id' => 98]))->toBeFalse();
    expect(app(FeatureFlagService::class)->isOn('useV2NotificationTemplates', ['business_id' => 99]))->toBeFalse();
    Cache::forget('growthbook.features');
});

test('F3 opção legacy preserva o editor Blade mesmo com a flag ON', function () {
    $this->mock(FeatureFlagService::class, fn ($mock) => $mock->shouldNotReceive('isOn'));
    $this->get('/notification-templates?legacy=1')->assertOk()->assertViewIs('notification_template.index');
});


test('F3 flag ON permite abrir a Page por navegação HTML', function () {
    notifDualFlag(true);
    $this->get('/notification-templates')->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('NotificationTemplate/Index'));
});
