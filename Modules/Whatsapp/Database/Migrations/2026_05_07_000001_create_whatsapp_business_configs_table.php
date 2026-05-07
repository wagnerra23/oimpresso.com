<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whatsapp business config — 1 row por business com Whatsapp ativo.
 *
 * Espelha memory/requisitos/Whatsapp/ARCHITECTURE.md §2.1.
 * Decisão mãe: ADR 0096.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope business_id
 * aplicado no Model via trait HasBusinessScope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_business_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->uuid('business_uuid')->unique()->comment('usado no webhook URL');

            // Driver primário e fallback obrigatório
            // Sprint 1: zapi | meta_cloud | null
            // Sprint 3: + baileys (autorizado ADR 0096 emenda 4)
            // Evolution PROIBIDO permanente (FormRequest rejeita 422)
            $table->string('driver', 20)->default('zapi');
            $table->string('fallback_driver', 20)->default('meta_cloud');

            $table->string('display_phone', 20)->nullable()
                ->comment('preenchido após primeiro ping bem-sucedido');

            // Meta Cloud API (sempre cadastrado quando driver=zapi/baileys = fallback obrigatório)
            $table->string('meta_phone_number_id', 64)->nullable();
            $table->text('meta_access_token')->nullable()->comment('encrypted cast Laravel');
            $table->text('meta_app_secret')->nullable()->comment('encrypted — usado pra HMAC webhook');
            $table->string('meta_webhook_verify_token', 64)->nullable();

            // Z-API (driver default Sprint 1)
            $table->string('zapi_instance_id', 64)->nullable();
            $table->text('zapi_instance_token')->nullable()->comment('encrypted');
            $table->text('zapi_client_token')->nullable()->comment('encrypted — header Client-Token + valida webhook');

            // Baileys custom Sprint 3 (ADR 0096 emenda 4 — daemon Node CT 100 próprio)
            $table->string('baileys_instance_id', 64)->nullable();
            $table->string('baileys_daemon_url', 255)->nullable();
            $table->text('baileys_api_key')->nullable()->comment('encrypted — Bearer pro daemon Node');

            // LGPD (obrigatório quando driver IN (zapi, baileys))
            $table->timestamp('lgpd_acknowledged_at')->nullable();
            $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();

            // Bot e templates (cross-driver)
            $table->boolean('bot_enabled')->default(false);
            $table->string('template_repair_ready_name', 64)->nullable();
            $table->string('template_repair_waiting_parts_name', 64)->nullable();
            $table->string('template_billing_due_name', 64)->nullable();
            $table->string('template_billing_paid_name', 64)->nullable();

            // Driver health (atualizado por WhatsappDriverHealthCheckJob — Lote 2c)
            $table->enum('driver_health', ['healthy', 'degraded', 'disconnected', 'banned', 'never_checked'])
                ->default('never_checked');
            $table->unsignedInteger('driver_health_consecutive_failures')->default(0);
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('last_health_message')->nullable();

            $table->timestamps();

            $table->index('business_id', 'wbc_biz_idx');
            $table->index(['driver', 'driver_health'], 'wbc_drv_health_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_business_configs');
    }
};
