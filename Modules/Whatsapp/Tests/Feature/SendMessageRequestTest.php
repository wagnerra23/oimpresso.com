<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Http\Requests\SendMessageRequest;

uses(Tests\TestCase::class);

/**
 * US-WA-003 manual send · validação SendMessageRequest.
 *
 * Cobre:
 * - kind=freeform sem body → 422
 * - kind=template sem template_name → 422
 * - kind=media sem url/type → 422
 * - kind=freeform OK quando driver=zapi (sem janela 24h restritiva)
 * - kind=freeform com driver=meta_cloud + janela 24h fechada → 422
 * - kind=freeform com driver=meta_cloud + janela 24h aberta → válido
 */

function runSendRequest(array $input, ?WhatsappConversation $conv = null): \Illuminate\Validation\Validator
{
    $request = SendMessageRequest::create('/whatsapp/conversations/X/send', 'POST', $input);
    $request->setContainer(app());

    if ($conv !== null) {
        // Simula route binding pra withValidator detectar conversation
        $route = new \Illuminate\Routing\Route(['POST'], '/whatsapp/conversations/{id}/send', []);
        $route->bind($request);
        $route->setParameter('id', $conv->id);
        $request->setRouteResolver(fn () => $route);
    }

    $rules = (new SendMessageRequest())->rules();
    $messages = method_exists(new SendMessageRequest(), 'messages') ? (new SendMessageRequest())->messages() : [];

    $validator = Validator::make($request->all(), $rules, $messages);
    $req = new SendMessageRequest();
    $req->merge($input);
    $req->setContainer(app());
    if ($conv !== null) {
        $route = new \Illuminate\Routing\Route(['POST'], '/whatsapp/conversations/{id}/send', []);
        $route->bind($req);
        $route->setParameter('id', $conv->id);
        $req->setRouteResolver(fn () => $route);
    }
    $req->withValidator($validator);

    return $validator;
}

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    foreach (['whatsapp_conversations', 'whatsapp_business_configs'] as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('whatsapp_business_configs', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('business_uuid')->unique();
        $table->string('driver', 20)->default('zapi');
        $table->string('fallback_driver', 20)->default('meta_cloud');
        $table->string('display_phone', 20)->nullable();
        $table->string('meta_phone_number_id', 64)->nullable();
        $table->text('meta_access_token')->nullable();
        $table->text('meta_app_secret')->nullable();
        $table->string('meta_webhook_verify_token', 64)->nullable();
        $table->string('zapi_instance_id', 64)->nullable();
        $table->text('zapi_instance_token')->nullable();
        $table->text('zapi_client_token')->nullable();
        $table->string('baileys_instance_id', 64)->nullable();
        $table->string('baileys_daemon_url', 255)->nullable();
        $table->text('baileys_api_key')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('driver_health', 20)->default('healthy');
        $table->unsignedInteger('driver_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamps();
    });
    Schema::create('whatsapp_conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_phone', 20);
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->boolean('bot_handling')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamps();
        $table->unique(['business_id', 'customer_phone']);
    });
});

it('rejeita kind=freeform sem body', function () {
    $v = runSendRequest(['kind' => 'freeform']);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('body'))->toContain('body não-vazio');
});

it('rejeita kind=template sem template_name', function () {
    $v = runSendRequest(['kind' => 'template']);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('template_name'))->toContain('template_name');
});

it('rejeita kind=media sem media_url', function () {
    $v = runSendRequest(['kind' => 'media', 'media_type' => 'image']);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('media_url'))->toContain('media_url');
});

it('aceita kind=freeform com body válido (sem conversation = sem check janela 24h)', function () {
    $v = runSendRequest(['kind' => 'freeform', 'body' => 'Olá!']);
    expect($v->fails())->toBeFalse();
});

it('aceita kind=template com template_name + locale + params válidos (US-WA-013 plug composer)', function () {
    $v = runSendRequest([
        'kind' => 'template',
        'template_name' => 'repair_status_ready',
        'template_locale' => 'pt_BR',
        'template_params' => ['João Silva', '#OS-123'],
    ]);
    expect($v->fails())->toBeFalse();
});

it('aceita kind=template sem locale (default pt_BR no controller)', function () {
    $v = runSendRequest([
        'kind' => 'template',
        'template_name' => 'billing_due',
    ]);
    expect($v->fails())->toBeFalse();
});

it('rejeita kind=freeform com driver=meta_cloud E janela 24h fechada', function () {
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);
    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
        'last_inbound_at' => null, // janela 24h fechada (nunca houve inbound)
    ]);

    auth()->logout();
    session(['user.business_id' => 1]);

    $v = runSendRequest(['kind' => 'freeform', 'body' => 'mensagem'], $conv);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('body'))->toContain('Janela 24h Meta fechada');
});

it('aceita kind=freeform com driver=zapi mesmo com janela 24h fechada', function () {
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi', // ignora janela 24h
        'driver_health' => 'healthy',
    ]);
    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
        'last_inbound_at' => null,
    ]);

    auth()->logout();
    session(['user.business_id' => 1]);

    $v = runSendRequest(['kind' => 'freeform', 'body' => 'mensagem'], $conv);

    expect($v->fails())->toBeFalse();
});

it('aceita kind=freeform com driver=meta_cloud E janela 24h aberta (last_inbound_at recente)', function () {
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);
    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
        'last_inbound_at' => now()->subHours(2), // dentro da janela 24h
    ]);

    auth()->logout();
    session(['user.business_id' => 1]);

    $v = runSendRequest(['kind' => 'freeform', 'body' => 'mensagem'], $conv);

    expect($v->fails())->toBeFalse();
});
