<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesquisa de satisfação depois da entrega — US-SELL-071 · UC-FLX-25.
 *
 * Uma linha por venda entregue (UNIQUE business_id + transaction_id = uma pesquisa por venda,
 * mesmo rodando a rotina duas vezes). Tabela-ponte: não altera `transactions`. Guarda o canal,
 * o motivo quando não sai (opt-out LGPD, sem canal), a nota e a tarefa de atendimento aberta
 * quando a nota é baixa. Sem telefone nem e-mail do cliente aqui (ficam no `contacts`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_satisfaction_surveys')) {
            return;
        }

        Schema::create('transaction_satisfaction_surveys', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('contact_id')->nullable();
            $t->timestamp('entregue_em')->nullable();
            $t->string('canal', 20)->default('nenhum'); // whatsapp | email | nenhum
            $t->string('status', 20)->default('nao_enviado'); // enviado | nao_enviado | respondido
            $t->string('motivo', 40)->nullable();
            $t->unsignedBigInteger('csat_response_id')->nullable();
            $t->unsignedTinyInteger('nota')->nullable();
            $t->timestamp('respondido_em')->nullable();
            $t->unsignedBigInteger('atendimento_schedule_id')->nullable();
            $t->timestamps();

            $t->unique(['business_id', 'transaction_id'], 'tx_satisf_biz_tx_uq');
            $t->index(['business_id', 'status'], 'tx_satisf_biz_status_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_satisfaction_surveys');
    }
};
