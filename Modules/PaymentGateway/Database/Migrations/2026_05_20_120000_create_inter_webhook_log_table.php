<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FIN-032 (Onda 26) — Inter API webhook PIX recebido → titulo auto-pago.
 *
 * Tabela dedicada de log/idempotência para webhooks PIX do Inter, separada
 * de `gateway_webhook_events` (que é audit cross-gateway append-only Onda 2).
 *
 * **Por que tabela própria** (e não reusar `gateway_webhook_events`):
 *   - Idempotência cirúrgica por (txid, credential_id) — fluxo PIX Inter
 *     identifica cobrança por `txid` único.
 *   - Audit trail focado em pagamento (signature_valid, processed_at,
 *     cobranca_id, valor_centavos, payer_cpf_cnpj redacted).
 *   - Permite worker rodar isolado sem competir com outros gateways.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NOT NULL + index + FK.
 *
 * Idempotência: UNIQUE (payment_gateway_credential_id, txid) — webhook
 * duplicado é detectado pelo banco antes do worker processar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inter_webhook_log')) {
            return;
        }

        Schema::create('inter_webhook_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('payment_gateway_credential_id');
            $table->string('txid', 64);                   // Inter PIX txid (chave idempotência por credencial)
            $table->string('endToEndId', 64)->nullable(); // E2E PIX (BCB)
            $table->unsignedBigInteger('cobranca_id')->nullable(); // FK resolvida no worker
            $table->unsignedBigInteger('titulo_id')->nullable();   // FK resolvida no worker
            $table->unsignedBigInteger('gateway_webhook_event_id')->nullable(); // ponteiro pro audit cross-gateway
            $table->integer('valor_centavos')->nullable();
            $table->string('payer_cpf_cnpj_redacted', 32)->nullable(); // LGPD: redacted antes de gravar
            $table->dateTime('data_pagamento')->nullable();
            $table->boolean('signature_valid')->default(false)->index();
            $table->string('status', 24)->default('received')->index();
            // status valores: received | processed | titulo_nao_encontrado | erro_fsm | erro_outro
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();

            $table->index('business_id', 'iwl_biz_idx');
            $table->index('payment_gateway_credential_id', 'iwl_cred_idx');
            $table->index('cobranca_id', 'iwl_cob_idx');
            $table->index('titulo_id', 'iwl_titulo_idx');

            $table->unique(
                ['payment_gateway_credential_id', 'txid'],
                'iwl_cred_txid_unique'
            );

            // FK pro business_id (CASCADE — se business apagado, audit some)
            $table->foreign('business_id', 'iwl_biz_fk')
                ->references('id')->on('business')
                ->onDelete('cascade');

            // FK pra credencial — RESTRICT pra preservar audit mesmo se credencial trocar
            $table->foreign('payment_gateway_credential_id', 'iwl_cred_fk')
                ->references('id')->on('payment_gateway_credentials')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('inter_webhook_log')) {
            Schema::dropIfExists('inter_webhook_log');
        }
    }
};
