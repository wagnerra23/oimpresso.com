<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency-Key das escritas do app das lojas (oimpresso-app). Primeiro uso: POST /api/app/vendas
 * (tela 11, API-CONTRATO-v1 §2.2). A linha é reservada na MESMA transação de banco da escrita: se a
 * venda falha, a chave some junto; repetir a chave depois do sucesso devolve a mesma resposta.
 *
 * Tier 0 (ADR 0093): business_id indexado com FK; a chave é única por business + usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_idempotencia')) {
            return;
        }

        Schema::create('app_idempotencia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            $table->string('rota', 60);
            $table->string('chave', 100);
            $table->char('hash_corpo', 64);
            $table->unsignedInteger('transaction_id')->nullable();
            $table->json('resposta')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id', 'rota', 'chave'], 'app_idem_biz_user_rota_chave_unique');
            $table->index('business_id', 'app_idem_business_id_index');
            $table->foreign('business_id', 'app_idem_business_id_foreign')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_idempotencia');
    }
};
