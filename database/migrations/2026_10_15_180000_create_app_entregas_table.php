<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocolo de entrega do pedido registrado pelo app das lojas (P3 · tela 22).
 * Contrato: memory/requisitos/AppMobile/api/tela-22-entrega.md.
 *
 * Uma linha por entrega: o número `ENT-NNNN` é sequencial POR business (único em
 * business_id + numero), quem recebeu, a assinatura (id em `arquivos`) ou o motivo de não ter, e
 * a localização do celular quando houve. Gravada na MESMA transação de banco da ação `entregar`
 * da FSM: se a ação falha, o protocolo some junto.
 *
 * Tier 0 (ADR 0093): business_id indexado com FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_entregas')) {
            return;
        }

        Schema::create('app_entregas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('transaction_id');
            $table->unsignedInteger('numero');
            $table->string('protocolo', 20);
            $table->string('recebido_por', 120);
            $table->string('documento', 40)->nullable();
            $table->boolean('com_assinatura')->default(false);
            $table->unsignedBigInteger('assinatura_arquivo_id')->nullable();
            $table->string('sem_assinatura_motivo', 200)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('precisao_m', 10, 2)->nullable();
            $table->json('itens');
            $table->unsignedInteger('user_id');
            $table->timestamp('registrado_em');
            $table->timestamps();

            $table->unique(['business_id', 'numero'], 'app_entregas_biz_numero_unique');
            $table->index('business_id', 'app_entregas_business_id_index');
            $table->index(['business_id', 'transaction_id'], 'app_entregas_biz_transaction_index');
            $table->foreign('business_id', 'app_entregas_business_id_foreign')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_entregas');
    }
};
