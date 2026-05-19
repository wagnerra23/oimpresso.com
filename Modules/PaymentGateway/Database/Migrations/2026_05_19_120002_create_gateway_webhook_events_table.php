<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 2 — ADR 0170.
 *
 * Log de eventos de webhook recebidos do gateway.
 *
 * Função principal: IDEMPOTÊNCIA. Quando webhook chega 2x (rede flaky,
 * retry do gateway), checa `gateway_event_id` antes de processar.
 *
 * Multi-tenant Tier 0: business_id + index. Audit append-only por
 * convenção (sem UPDATE em campos exceto `processed_at`).
 *
 * Esta tabela substitui (eventualmente) `pg_webhook_events` da RB
 * legacy — backfill na Onda 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->nullable()->index();
            $table->string('gateway_key', 20)->index(); // inter | c6 | asaas | bcb_pix | pesapal
            $table->string('evento', 60)->index();      // cob.created | cob.paid | recv.confirmed | ...
            $table->string('gateway_event_id', 191);    // ID externo (idempotência)
            $table->unsignedBigInteger('cobranca_id')->nullable()->index();
            $table->json('payload');                    // body bruto recebido
            $table->boolean('signature_valid')->default(false)->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['business_id', 'gateway_key', 'gateway_event_id'],
                'gw_wh_biz_key_extid_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_webhook_events');
    }
};
