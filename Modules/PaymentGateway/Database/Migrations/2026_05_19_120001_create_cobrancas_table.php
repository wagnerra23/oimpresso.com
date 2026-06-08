<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 2 — ADR 0170.
 *
 * Tabela centralizada de cobranças do PaymentGateway.
 *
 * Substitui (eventualmente) `rb_charge_attempts` como source-of-truth do
 * que foi cobrado. Eventos `CobrancaEmitida`/`CobrancaPaga` etc apontam
 * pra registros aqui via `cobranca_id`.
 *
 * Idempotência: `(business_id, idempotency_key)` é UNIQUE — tentativas
 * de reemitir com mesma key retornam o registro existente.
 *
 * Multi-tenant Tier 0: business_id NOT NULL + index.
 * PII (`payer_cpf_cnpj`/`payer_name`/`payer_email`/`descricao`) declarada
 * em module.json.lgpd_compliance + retention 5 anos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->nullable()->index();
            $table->string('gateway_external_id')->nullable()->index(); // ID no banco/gateway
            $table->enum('tipo', ['boleto', 'pix_cob', 'pix_cobv', 'pix_recv', 'card'])->index();
            $table->enum('status', ['pending', 'emitida', 'paga', 'vencida', 'cancelada', 'erro'])
                ->default('pending')
                ->index();

            // Valores em centavos pra evitar float arithmetic.
            $table->unsignedInteger('valor_centavos');
            $table->unsignedInteger('valor_pago_centavos')->nullable();
            $table->date('vencimento');
            $table->timestamp('paga_em')->nullable();

            // Pagador.
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('payer_cpf_cnpj', 14)->nullable(); // LGPD pii
            $table->string('payer_name')->nullable();         // LGPD pii
            $table->string('payer_email')->nullable();       // LGPD pii
            $table->text('descricao');                       // LGPD pii (pode mencionar serviço)

            // Idempotência.
            $table->string('idempotency_key', 191);

            // Origem do trigger da cobrança.
            $table->enum('origem_type', ['sale', 'invoice', 'subscription_license', 'avulsa'])->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();

            // Artefatos da cobrança emitida.
            $table->string('linha_digitavel', 60)->nullable();
            $table->string('codigo_barras', 60)->nullable();
            $table->text('pix_emv')->nullable();          // BR Code copia-e-cola
            $table->string('pix_qr_code_path')->nullable();
            $table->string('boleto_pdf_url')->nullable();
            $table->string('nosso_numero', 30)->nullable();
            $table->enum('forma_pagamento', ['boleto', 'pix', 'cartao'])->nullable();

            // Payload bruto (request/response) — audit.
            $table->json('payload_gateway')->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key'], 'cobrancas_biz_idem_unique');
            $table->index(['business_id', 'status', 'vencimento'], 'cobrancas_biz_status_venc');
            $table->index(['origem_type', 'origem_id'], 'cobrancas_origem_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
