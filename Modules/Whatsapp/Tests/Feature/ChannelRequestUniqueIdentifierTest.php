<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Http\Requests\ChannelRequest;

uses(Tests\TestCase::class);

/**
 * Cross-business uniqueness do display_identifier (incident 2026-05-13 Gap B).
 *
 * Channels id=2 (biz=1) e id=4 (biz=164) tinham mesmo `554888782087` em produção,
 * causando stream:error conflict type="replaced" em loop por 99min no daemon
 * Baileys. Esta validação no FormRequest bloqueia o pareamento duplicado ANTES
 * que o daemon dispute a sessão.
 *
 * Cobertura BACKEND (não HTTP — requer auth real, validada em smoke biz=1):
 *   1. Tabela schema + Channel entity OK
 *   2. Query `withoutGlobalScopes` detecta colisão cross-business
 *   3. Normalização '+' funciona (E.164 com ou sem prefixo)
 *   4. ID atual ignorado em update
 *   5. Type não-whatsapp passa direto (não aplica regra)
 *
 * @see Modules/Whatsapp/Http/Requests/ChannelRequest.php::withValidator()
 * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Schema::dropIfExists('channels');
    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->string('display_identifier', 100)->nullable();
        $table->text('config_json')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });
});

function makeBaileysChannel(int $businessId, string $displayIdentifier, string $label = 'Suporte'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => 'crit-' . $businessId . '-' . uniqid(),
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
        'display_identifier' => $displayIdentifier,
    ]);
}

function runChannelRequestValidator(array $data, ?int $editingChannelId = null): \Illuminate\Validation\Validator
{
    $request = new ChannelRequest();
    $request->merge($data);
    $request->setContainer(app());
    if ($editingChannelId !== null) {
        $request->setRouteResolver(function () use ($editingChannelId) {
            $route = new \Illuminate\Routing\Route(['POST'], '/test', []);
            $route->parameters = ['channel' => $editingChannelId];
            return $route;
        });
    }

    $factory = app(\Illuminate\Contracts\Validation\Factory::class);
    $validator = $factory->make($data, $request->rules());
    $request->withValidator($validator);

    return $validator;
}

it('R-WA-CHAN-UNIQ-001 — bloqueia 2 channels Baileys cross-business com mesmo telefone (sem +)', function () {
    makeBaileysChannel(1, '554888782087', 'Suporte biz1');

    $validator = runChannelRequestValidator([
        'label' => 'Vendas biz99',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'config' => ['baileys_phone_e164' => '+554888782087'],
        'lgpd_acknowledged' => true,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('config.baileys_phone_e164'))->toBeTrue();
    expect($validator->errors()->first('config.baileys_phone_e164'))
        ->toContain('já está cadastrado em outro canal');
});

it('R-WA-CHAN-UNIQ-002 — bloqueia também quando display_identifier em DB tem o + prefix', function () {
    makeBaileysChannel(1, '+554888782087', 'Suporte biz1 com +');

    $validator = runChannelRequestValidator([
        'label' => 'Vendas biz99',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'config' => ['baileys_phone_e164' => '+554888782087'],
        'lgpd_acknowledged' => true,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('config.baileys_phone_e164'))->toBeTrue();
});

it('R-WA-CHAN-UNIQ-003 — permite telefones diferentes entre businesses', function () {
    makeBaileysChannel(1, '554888782087', 'biz1');

    $validator = runChannelRequestValidator([
        'label' => 'Vendas biz99 outro número',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'config' => ['baileys_phone_e164' => '+554896486699'], // diferente
        'lgpd_acknowledged' => true,
    ]);

    // Pode falhar em outras regras (label/etc), mas NÃO em config.baileys_phone_e164
    expect($validator->errors()->has('config.baileys_phone_e164'))->toBeFalse();
});

it('R-WA-CHAN-UNIQ-004 — Z-API instance_id também é unique cross-business', function () {
    Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_uuid' => 'zapi-biz1-' . uniqid(),
        'label' => 'Z-API biz1',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'status' => 'active',
        'display_identifier' => '3D4F5A6B7C8E',
    ]);

    $validator = runChannelRequestValidator([
        'label' => 'Z-API biz99',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'config' => [
            'zapi_instance_id' => '3D4F5A6B7C8E',
            'zapi_instance_token' => 'tok-fake',
        ],
    ]);

    expect($validator->errors()->has('config.zapi_instance_id'))->toBeTrue();
});

it('R-WA-CHAN-UNIQ-005 — type não-whatsapp não dispara a regra de unicidade', function () {
    makeBaileysChannel(1, 'someone@example.com', 'biz1 email-ish display_id');

    $validator = runChannelRequestValidator([
        'label' => 'Email canal',
        'type' => Channel::TYPE_EMAIL_IMAP,
        'config' => [],
    ]);

    expect($validator->errors()->has('config.baileys_phone_e164'))->toBeFalse();
    expect($validator->errors()->has('config.meta_phone_number_id'))->toBeFalse();
    expect($validator->errors()->has('config.zapi_instance_id'))->toBeFalse();
});

it('R-WA-CHAN-UNIQ-006 — update do MESMO channel não dispara contra si mesmo', function () {
    $ch = makeBaileysChannel(1, '554888782087', 'Original');

    $validator = runChannelRequestValidator([
        'label' => 'Original renomeada',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'config' => ['baileys_phone_e164' => '+554888782087'],
        'lgpd_acknowledged' => true,
    ], editingChannelId: $ch->id);

    expect($validator->errors()->has('config.baileys_phone_e164'))->toBeFalse();
});
