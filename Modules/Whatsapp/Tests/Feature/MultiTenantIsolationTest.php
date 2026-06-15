<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Entities\WhatsappPhoneUserAccess;

uses(Tests\TestCase::class);

/**
 * R-WA-005 · Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093 + ADR 0096).
 *
 * Cenário Gherkin:
 *   Dado WhatsappBusinessConfig + WhatsappConversation + WhatsappMessage
 *        usam BusinessIdScope global via trait HasBusinessScope
 *   Quando user do business A consulta esses Models
 *   Então só vê rows com business_id = A; rows do business B NUNCA aparecem
 *
 * Também cobre:
 *   - R-WA-006 PII redacted: tokens cifrados em DB (encrypted cast Laravel)
 *   - Trigger de fallback: effectiveDriver() troca pra fallback quando degraded
 *
 * Padrão: cria tabelas manualmente em beforeEach (migrations UltimatePOS
 * quebram SQLite — mesmo padrão de RecurringBilling/DomainModelsTest).
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    foreach ([
        'whatsapp_phone_user_access',
        'whatsapp_messages',
        'whatsapp_conversations',
        'whatsapp_templates',
        'whatsapp_business_phones',
        'whatsapp_business_configs',
    ] as $t) {
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
        // ADR 0202 (2026-05-27): colunas baileys_* DROPADAS via migration.
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('driver_health', 20)->default('never_checked');
        $table->unsignedInteger('driver_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamps();
    });

    Schema::create('whatsapp_business_phones', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('phone_uuid')->unique();
        $table->string('label', 80);
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
        // ADR 0202 (2026-05-27): colunas baileys_* DROPADAS via migration.
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('driver_health', 20)->default('never_checked');
        $table->unsignedInteger('driver_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamps();
        // ADR 0202 (2026-05-27): UNIQUE wbp_biz_phone_unq REMOVIDO junto com a
        // coluna baileys_phone_e164. Uniqueness Channel-based agora via ChannelRequest::withValidator.
    });

    Schema::create('whatsapp_phone_user_access', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('whatsapp_business_phone_id');
        $table->unsignedInteger('user_id');
        $table->timestamps();
        $table->unique(['whatsapp_business_phone_id', 'user_id'], 'wpua_phone_user_unq');
    });

    Schema::create('whatsapp_conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable();
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

    Schema::create('whatsapp_messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable();
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 20);
        $table->string('provider_message_id', 128)->nullable()->unique();
        $table->string('type', 20)->default('text');
        $table->string('template_name', 64)->nullable();
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->string('failed_reason', 255)->nullable();
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->string('sender_kind', 20)->nullable();
        $table->unsignedInteger('cost_centavos')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });
});

it('isola WhatsappBusinessConfig cross-business via global scope', function () {
    // Cria 2 configs em businesses diferentes (escapando scope pra setup)
    $configA = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
    ]);

    $configB = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
    ]);

    // User do business 1 (Wagner): só deve ver config A
    auth()->logout();
    session(['user.business_id' => 1]);

    $found = WhatsappBusinessConfig::all();
    expect($found)->toHaveCount(1)
        ->and($found->first()->id)->toBe($configA->id)
        ->and($found->first()->business_id)->toBe(1);

    // Switch pra business 99 (adversário cross-tenant): só deve ver config B
    session(['user.business_id' => 99]);
    $foundOther = WhatsappBusinessConfig::all();
    expect($foundOther)->toHaveCount(1)
        ->and($foundOther->first()->id)->toBe($configB->id)
        ->and($foundOther->first()->business_id)->toBe(99);
});

it('isola WhatsappConversation cross-business via global scope', function () {
    WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
    ]);
    WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'customer_phone' => '+5511987654321',
    ]);

    auth()->logout();
    session(['user.business_id' => 1]);
    expect(WhatsappConversation::count())->toBe(1);

    session(['user.business_id' => 99]);
    expect(WhatsappConversation::count())->toBe(1);

    // Sanity: as 2 conversations realmente existem em DB (escape do scope confirma)
    $total = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->count();
    expect($total)->toBe(2);
});

it('isola WhatsappMessage cross-business via global scope', function () {
    $convA = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
    ]);
    $convB = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'customer_phone' => '+5511987654321',
    ]);

    WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $convA->id,
        'direction' => 'outbound',
        'provider' => 'zapi',
        'provider_message_id' => 'wamid.A',
        'body' => 'Mensagem business 1 (CONFIDENCIAL — não pode vazar)',
        'status' => 'sent',
    ]);

    WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'conversation_id' => $convB->id,
        'direction' => 'outbound',
        'provider' => 'zapi',
        'provider_message_id' => 'wamid.B',
        'body' => 'Mensagem business 99 (CONFIDENCIAL — não pode vazar)',
        'status' => 'sent',
    ]);

    auth()->logout();
    session(['user.business_id' => 1]);
    $msgs = WhatsappMessage::all();
    expect($msgs)->toHaveCount(1);
    expect($msgs->first()->body)->toContain('business 1');
    expect($msgs->first()->body)->not->toContain('business 99');
});

it('cifra access_token e tokens sensíveis em DB (encrypted cast)', function () {
    $tokenPlano = 'EAAB-meta-bearer-secret-12345-abc';
    $zapiToken = 'zapi-instance-secret-XYZ-789';

    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'meta_access_token' => $tokenPlano,
        'zapi_instance_token' => $zapiToken,
    ]);

    // Via Eloquent: lemos texto plano (cast decifra)
    $reloaded = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->find($config->id);
    expect($reloaded->meta_access_token)->toBe($tokenPlano);
    expect($reloaded->zapi_instance_token)->toBe($zapiToken);

    // Via raw SQL: vem cifrado (NÃO contém texto plano)
    $raw = DB::table('whatsapp_business_configs')->where('id', $config->id)->first();
    expect($raw->meta_access_token)->not->toBe($tokenPlano);
    expect($raw->zapi_instance_token)->not->toBe($zapiToken);
    expect($raw->meta_access_token)->not->toContain('EAAB');
    expect($raw->zapi_instance_token)->not->toContain('zapi-instance-secret');

    // Sanity: o texto cifrado decifra de volta no plano original
    expect(Crypt::decryptString($raw->meta_access_token))->toBe($tokenPlano);
});

it('effectiveDriver retorna fallback quando driver_health degraded', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    expect($config->effectiveDriver())->toBe('zapi');

    $config->driver_health = 'degraded';
    expect($config->effectiveDriver())->toBe('meta_cloud');

    $config->driver_health = 'banned';
    expect($config->effectiveDriver())->toBe('meta_cloud');

    $config->driver_health = 'never_checked';
    expect($config->effectiveDriver())->toBe('zapi'); // never_checked usa primário
});

it('requiresFallback identifica zapi como único driver obrigando Meta cadastrado (pós ADR 0202)', function () {
    // ADR 0202 (2026-05-27): baileys removido de mandatory_for_drivers junto
    // com a saída integral do BaileysDriver. Só zapi exige fallback Meta agora.
    config()->set('whatsapp.fallback.mandatory_for_drivers', ['zapi']);

    $configZapi = new WhatsappBusinessConfig(['driver' => 'zapi']);
    expect($configZapi->requiresFallback())->toBeTrue();

    $configMeta = new WhatsappBusinessConfig(['driver' => 'meta_cloud']);
    expect($configMeta->requiresFallback())->toBeFalse();

    $configNull = new WhatsappBusinessConfig(['driver' => 'null']);
    expect($configNull->requiresFallback())->toBeFalse();
});

it('hasMetaCloudConfigured detecta Meta cadastrado pra gating fallback', function () {
    $configSemMeta = new WhatsappBusinessConfig([
        'driver' => 'zapi',
        'meta_phone_number_id' => null,
    ]);
    expect($configSemMeta->hasMetaCloudConfigured())->toBeFalse();

    $configComMeta = new WhatsappBusinessConfig([
        'driver' => 'zapi',
        'meta_phone_number_id' => '123456789',
        'meta_access_token' => 'EAAB-secret',
        'meta_app_secret' => 'app-secret-xyz',
    ]);
    expect($configComMeta->hasMetaCloudConfigured())->toBeTrue();
});

// ============================================================
// ADR 0117 — N números/business via WhatsappBusinessPhone
// ============================================================

it('isola WhatsappBusinessPhone cross-business via global scope', function () {
    $phoneA = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
    ]);

    $phoneB = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Financeiro',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
    ]);

    auth()->logout();
    session(['user.business_id' => 1]);
    $found = WhatsappBusinessPhone::all();
    expect($found)->toHaveCount(1)
        ->and($found->first()->id)->toBe($phoneA->id)
        ->and($found->first()->label)->toBe('Comercial');

    session(['user.business_id' => 99]);
    $foundOther = WhatsappBusinessPhone::all();
    expect($foundOther)->toHaveCount(1)
        ->and($foundOther->first()->id)->toBe($phoneB->id)
        ->and($foundOther->first()->label)->toBe('Financeiro');
});

it('cifra access_token e tokens sensíveis em WhatsappBusinessPhone (encrypted cast)', function () {
    $tokenPlano = 'EAAB-meta-bearer-secret-PHONE-12345';
    $zapiToken = 'zapi-instance-secret-PHONE-XYZ-789';

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'meta_access_token' => $tokenPlano,
        'zapi_instance_token' => $zapiToken,
    ]);

    $reloaded = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->find($phone->id);
    expect($reloaded->meta_access_token)->toBe($tokenPlano);
    expect($reloaded->zapi_instance_token)->toBe($zapiToken);

    $raw = DB::table('whatsapp_business_phones')->where('id', $phone->id)->first();
    expect($raw->meta_access_token)->not->toBe($tokenPlano);
    expect($raw->zapi_instance_token)->not->toBe($zapiToken);
    expect($raw->meta_access_token)->not->toContain('EAAB');

    expect(Crypt::decryptString($raw->meta_access_token))->toBe($tokenPlano);
});

// ADR 0202 (2026-05-27): testes "UNIQUE baileys_phone_e164" e "mesmo número
// Baileys cross-business" REMOVIDOS — colunas baileys_* serão DROPADAS pela
// migration 2026_05_28_000001_drop_baileys_columns_from_whatsapp_business_configs.

it('resolveForEvent retorna phone com handle específico ligado', function () {
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => true,
        'handles_billing' => false,
    ]);

    $financeiro = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Financeiro',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => false,
        'handles_billing' => true,
    ]);

    $resolvedRepair = WhatsappBusinessPhone::resolveForEvent(1, 'repair_status');
    expect($resolvedRepair)->not->toBeNull()
        ->and($resolvedRepair->label)->toBe('Comercial');

    $resolvedBilling = WhatsappBusinessPhone::resolveForEvent(1, 'billing');
    expect($resolvedBilling)->not->toBeNull()
        ->and($resolvedBilling->id)->toBe($financeiro->id);
});

it('resolveForEvent cai no handles_outbound_default quando nenhum específico bate', function () {
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Único',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => false,
        'handles_billing' => false,
        'handles_jana_bot' => false,
        'handles_outbound_default' => true,
    ]);

    $resolved = WhatsappBusinessPhone::resolveForEvent(1, 'repair_status');
    expect($resolved)->not->toBeNull()
        ->and($resolved->label)->toBe('Único');
});

it('resolveForEvent retorna null quando nenhum phone tem flag nem default', function () {
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Sem rotear nada',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => false,
        'handles_billing' => false,
        'handles_jana_bot' => false,
        'handles_outbound_default' => false,
    ]);

    $resolved = WhatsappBusinessPhone::resolveForEvent(1, 'billing');
    expect($resolved)->toBeNull();
});

it('isola WhatsappPhoneUserAccess cross-business via global scope', function () {
    $phoneA = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial A',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
    ]);
    $phoneB = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial B',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
    ]);

    WhatsappPhoneUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'whatsapp_business_phone_id' => $phoneA->id,
        'user_id' => 10,
    ]);
    WhatsappPhoneUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'whatsapp_business_phone_id' => $phoneB->id,
        'user_id' => 20,
    ]);

    auth()->logout();
    session(['user.business_id' => 1]);
    $found = WhatsappPhoneUserAccess::all();
    expect($found)->toHaveCount(1)
        ->and($found->first()->user_id)->toBe(10);

    session(['user.business_id' => 99]);
    $foundOther = WhatsappPhoneUserAccess::all();
    expect($foundOther)->toHaveCount(1)
        ->and($foundOther->first()->user_id)->toBe(20);
});

it('scope accessibleBy filtra phones que user tem ACL', function () {
    $phoneVisivel = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Visível',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
    ]);

    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Sem ACL',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
    ]);

    WhatsappPhoneUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'whatsapp_business_phone_id' => $phoneVisivel->id,
        'user_id' => 42,
    ]);

    session(['user.business_id' => 1]);
    $accessible = WhatsappBusinessPhone::query()->accessibleBy(42)->get();
    expect($accessible)->toHaveCount(1)
        ->and($accessible->first()->label)->toBe('Visível');
});

it('effectiveDriver em WhatsappBusinessPhone tem mesma semântica do legacy config', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    expect($phone->effectiveDriver())->toBe('zapi');

    $phone->driver_health = 'degraded';
    expect($phone->effectiveDriver())->toBe('meta_cloud');

    $phone->driver_health = 'banned';
    expect($phone->effectiveDriver())->toBe('meta_cloud');

    $phone->driver_health = 'never_checked';
    expect($phone->effectiveDriver())->toBe('zapi');
});
