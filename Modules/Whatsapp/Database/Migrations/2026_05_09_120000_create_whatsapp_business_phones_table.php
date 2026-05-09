<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whatsapp business phones — N rows por business (1 por número Whatsapp).
 *
 * Substitui `whatsapp_business_configs` (1:1 business→config) — ver ADR 0117.
 * Schema espelha SPEC.md US-WA-040 + ADR 0117 §Schema mãe.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id` no
 * Model via trait `HasBusinessScope`.
 *
 * Tokens (meta_*, zapi_*, baileys_*) cifrados em DB via cast `encrypted`
 * Laravel no Model `WhatsappBusinessPhone`.
 *
 * Roteamento de eventos automáticos via flags `handles_*` (decisão Q2 do
 * Wagner em ADR 0117 — cada número escolhe quais eventos dispara).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_business_phones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->uuid('phone_uuid')->unique()
                ->comment('usado em webhook URL e Centrifugo channel granular');
            $table->string('label', 80)
                ->comment('apelido livre Comercial/Financeiro/etc — Q4 ADR 0117');

            // Driver per-phone (era per-business em whatsapp_business_configs)
            $table->string('driver', 20)->default('zapi')
                ->comment('zapi|meta_cloud|baileys|null — Evolution PROIBIDO Tier 0');
            $table->string('fallback_driver', 20)->default('meta_cloud');
            $table->string('display_phone', 20)->nullable()
                ->comment('preenchido após primeiro ping bem-sucedido');

            // Meta Cloud per-phone (sempre cadastrado quando driver=zapi/baileys = fallback obrigatório)
            $table->string('meta_phone_number_id', 64)->nullable();
            $table->text('meta_access_token')->nullable()->comment('encrypted cast Laravel');
            $table->text('meta_app_secret')->nullable()->comment('encrypted — usado pra HMAC webhook');
            $table->string('meta_webhook_verify_token', 64)->nullable();

            // Z-API per-phone
            $table->string('zapi_instance_id', 64)->nullable();
            $table->text('zapi_instance_token')->nullable()->comment('encrypted');
            $table->text('zapi_client_token')->nullable()->comment('encrypted — header Client-Token + valida webhook');

            // Baileys per-phone (US-WA-022 — só telefone E.164 do tenant; daemon URL/api key vão em config global)
            $table->string('baileys_instance_id', 64)->nullable()
                ->comment('auto-gerado pelo backend prefixo biz{business_id}-');
            $table->string('baileys_phone_e164', 20)->nullable()
                ->comment('telefone E.164 (+5511987654321) cadastrado pelo tenant');
            $table->string('baileys_verified_name', 100)->nullable()
                ->comment('Business Profile name sincronizado após pareamento');
            $table->string('baileys_profile_pic_url', 255)->nullable()
                ->comment('URL da foto de perfil sincronizada após pareamento');

            // LGPD per-phone (cada aceite é por número — driver não-oficial exige)
            $table->timestamp('lgpd_acknowledged_at')->nullable();
            $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();

            // Roteamento de eventos automáticos (Q2 ADR 0117 — decisão B)
            $table->boolean('handles_repair_status')->default(false)
                ->comment('listener NotifyRepairCustomer dispara por este número?');
            $table->boolean('handles_billing')->default(false)
                ->comment('listeners RecurringBilling (InvoicePaid/Due) disparam por este número?');
            $table->boolean('handles_jana_bot')->default(true)
                ->comment('mensagens entrantes processadas pelo Jana bot saem por este número?');
            $table->boolean('handles_outbound_default')->default(false)
                ->comment('fallback se nenhum outro flag bate evento');

            // Bot e templates per-phone (cross-driver)
            $table->boolean('bot_enabled')->default(false);
            $table->string('template_repair_ready_name', 64)->nullable();
            $table->string('template_repair_waiting_parts_name', 64)->nullable();
            $table->string('template_billing_due_name', 64)->nullable();
            $table->string('template_billing_paid_name', 64)->nullable();

            // Driver health (atualizado por WhatsappDriverHealthCheckJob)
            $table->enum('driver_health', ['healthy', 'degraded', 'disconnected', 'banned', 'never_checked'])
                ->default('never_checked');
            $table->unsignedInteger('driver_health_consecutive_failures')->default(0);
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('last_health_message')->nullable();

            $table->timestamps();

            // UNIQUE composto — anti-duplicate (mesmo número Baileys 1x por business)
            $table->unique(['business_id', 'baileys_phone_e164'], 'wbp_biz_phone_unq');
            $table->index('business_id', 'wbp_biz_idx');
            $table->index(['driver', 'driver_health'], 'wbp_drv_health_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_business_phones');
    }
};
