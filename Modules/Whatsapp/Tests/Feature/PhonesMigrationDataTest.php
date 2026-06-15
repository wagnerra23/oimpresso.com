<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * ADR 0117 §Q6 — data migration 1→N copia legacy `whatsapp_business_configs`
 * pra `whatsapp_business_phones` com `label='Comercial'` (default seguro)
 * + UPDATE conversations/messages apontando pro phone novo.
 *
 * Este teste valida o COMPORTAMENTO esperado da migration:
 *
 * 1. Cada config legacy vira 1 phone novo
 * 2. Phone seed nasce com `label='Comercial'` + todas flags `handles_*=true`
 *    (legacy 1-número fazia tudo — admin desmarca conscientemente depois)
 * 3. Conversations/messages do business apontam pro phone correto
 * 4. Multi-tenant: nenhum phone_id de business=A vinculado a conversation
 *    de business=B (Tier 0 IRREVOGÁVEL)
 * 5. Idempotência: rodar 2× não duplica phone "Comercial"
 *
 * Setup espelha schema das 4 migrations PR 1. Lógica de seed replicada
 * inline pra desacoplar do arquivo de migration anônima (válido pro
 * comportamento, não pra implementação exata).
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
        $table->string('baileys_instance_id', 64)->nullable();
        $table->string('baileys_phone_e164', 20)->nullable();
        $table->string('baileys_verified_name', 100)->nullable();
        $table->string('baileys_profile_pic_url', 255)->nullable();
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
        $table->string('baileys_instance_id', 64)->nullable();
        $table->string('baileys_phone_e164', 20)->nullable();
        $table->string('baileys_verified_name', 100)->nullable();
        $table->string('baileys_profile_pic_url', 255)->nullable();
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
        $table->unique(['business_id', 'baileys_phone_e164'], 'wbp_biz_phone_unq');
    });

    Schema::create('whatsapp_conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_phone', 20);
        $table->string('status', 20)->default('open');
        $table->timestamp('last_message_at')->nullable();
        $table->timestamps();
    });

    Schema::create('whatsapp_messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable();
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 20);
        $table->string('provider_message_id', 128)->nullable();
        $table->string('status', 20);
        $table->text('body')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
});

/**
 * Replica a lógica essencial da migration de dados (espelho fiel pro
 * comportamento documentado em ADR 0117 §Q6 + runbook).
 */
function runDataMigration(): void
{
    DB::transaction(function () {
        $configs = DB::table('whatsapp_business_configs')->get();

        foreach ($configs as $config) {
            $existing = DB::table('whatsapp_business_phones')
                ->where('business_id', $config->business_id)
                ->where('label', 'Comercial')
                ->first();

            if ($existing) {
                $phoneId = $existing->id;
            } else {
                $phoneId = DB::table('whatsapp_business_phones')->insertGetId([
                    'business_id' => $config->business_id,
                    'phone_uuid' => (string) Str::uuid(),
                    'label' => 'Comercial',
                    'driver' => $config->driver ?? 'zapi',
                    'fallback_driver' => $config->fallback_driver ?? 'meta_cloud',
                    'meta_access_token' => $config->meta_access_token ?? null,
                    'zapi_instance_token' => $config->zapi_instance_token ?? null,
                    'baileys_phone_e164' => $config->baileys_phone_e164 ?? null,
                    'handles_repair_status' => true,
                    'handles_billing' => true,
                    'handles_jana_bot' => true,
                    'handles_outbound_default' => true,
                    'bot_enabled' => $config->bot_enabled ?? false,
                    'driver_health' => $config->driver_health ?? 'never_checked',
                    'created_at' => $config->created_at ?? now(),
                    'updated_at' => $config->updated_at ?? now(),
                ]);
            }

            DB::table('whatsapp_conversations')
                ->where('business_id', $config->business_id)
                ->whereNull('whatsapp_business_phone_id')
                ->update(['whatsapp_business_phone_id' => $phoneId]);

            DB::table('whatsapp_messages')
                ->where('business_id', $config->business_id)
                ->whereNull('whatsapp_business_phone_id')
                ->update(['whatsapp_business_phone_id' => $phoneId]);
        }
    });
}

it('migra cada config legacy pra 1 phone com label Comercial', function () {
    DB::table('whatsapp_business_configs')->insert([
        [
            'business_id' => 1,
            'business_uuid' => (string) Str::uuid(),
            'driver' => 'baileys',
            'fallback_driver' => 'meta_cloud',
            'baileys_phone_e164' => '+5511999990001',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'business_id' => 99,
            'business_uuid' => (string) Str::uuid(),
            'driver' => 'zapi',
            'fallback_driver' => 'meta_cloud',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    runDataMigration();

    $phones = DB::table('whatsapp_business_phones')->orderBy('business_id')->get();
    expect($phones)->toHaveCount(2);

    expect($phones[0]->business_id)->toBe(1);
    expect($phones[0]->label)->toBe('Comercial');
    expect($phones[0]->driver)->toBe('baileys');
    expect($phones[0]->baileys_phone_e164)->toBe('+5511999990001');

    expect($phones[1]->business_id)->toBe(99);
    expect($phones[1]->driver)->toBe('zapi');
});

it('phone seed nasce com todas flags handles_* true (legacy 1-número fazia tudo)', function () {
    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runDataMigration();

    $phone = DB::table('whatsapp_business_phones')->first();
    expect((bool) $phone->handles_repair_status)->toBeTrue();
    expect((bool) $phone->handles_billing)->toBeTrue();
    expect((bool) $phone->handles_jana_bot)->toBeTrue();
    expect((bool) $phone->handles_outbound_default)->toBeTrue();
});

it('vincula conversations e messages do business pro phone correto', function () {
    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $convId = DB::table('whatsapp_conversations')->insertGetId([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('whatsapp_messages')->insert([
        'business_id' => 1,
        'conversation_id' => $convId,
        'direction' => 'outbound',
        'provider' => 'baileys',
        'provider_message_id' => 'msg-1',
        'status' => 'sent',
    ]);

    runDataMigration();

    $phone = DB::table('whatsapp_business_phones')->first();
    $conv = DB::table('whatsapp_conversations')->where('id', $convId)->first();
    $msg = DB::table('whatsapp_messages')->where('provider_message_id', 'msg-1')->first();

    expect($conv->whatsapp_business_phone_id)->toBe($phone->id);
    expect($msg->whatsapp_business_phone_id)->toBe($phone->id);
});

it('multi-tenant: phone de business A nunca vincula a conversation de business B', function () {
    DB::table('whatsapp_business_configs')->insert([
        ['business_id' => 1, 'business_uuid' => (string) Str::uuid(), 'driver' => 'baileys', 'fallback_driver' => 'meta_cloud', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 99, 'business_uuid' => (string) Str::uuid(), 'driver' => 'zapi', 'fallback_driver' => 'meta_cloud', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $convA = DB::table('whatsapp_conversations')->insertGetId([
        'business_id' => 1, 'customer_phone' => '+5511990000001', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $convB = DB::table('whatsapp_conversations')->insertGetId([
        'business_id' => 99, 'customer_phone' => '+5511990000004', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);

    runDataMigration();

    $phoneA = DB::table('whatsapp_business_phones')->where('business_id', 1)->first();
    $phoneB = DB::table('whatsapp_business_phones')->where('business_id', 99)->first();

    $cA = DB::table('whatsapp_conversations')->where('id', $convA)->first();
    $cB = DB::table('whatsapp_conversations')->where('id', $convB)->first();

    expect($cA->whatsapp_business_phone_id)->toBe($phoneA->id);
    expect($cB->whatsapp_business_phone_id)->toBe($phoneB->id);

    // R-WA-005 R-WA-005 — Tier 0 IRREVOGÁVEL: garantir que phone A nunca aponta pra business B
    $cross = DB::table('whatsapp_conversations as c')
        ->join('whatsapp_business_phones as p', 'p.id', '=', 'c.whatsapp_business_phone_id')
        ->whereColumn('c.business_id', '!=', 'p.business_id')
        ->count();

    expect($cross)->toBe(0);
});

it('idempotência: rodar 2× não duplica phone Comercial', function () {
    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runDataMigration();
    expect(DB::table('whatsapp_business_phones')->where('business_id', 1)->count())->toBe(1);

    runDataMigration();
    expect(DB::table('whatsapp_business_phones')->where('business_id', 1)->count())->toBe(1);
});

it('preserva ciphertext raw dos secrets (mesma APP_KEY decifra)', function () {
    // Simula ciphertext já cadastrado em legacy (em prod viria do encrypted cast)
    $cipher = \Illuminate\Support\Facades\Crypt::encryptString('EAAB-bearer-secret');

    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'meta_access_token' => $cipher,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runDataMigration();

    $phone = DB::table('whatsapp_business_phones')->first();
    expect($phone->meta_access_token)->toBe($cipher);
    expect(\Illuminate\Support\Facades\Crypt::decryptString($phone->meta_access_token))->toBe('EAAB-bearer-secret');
});
