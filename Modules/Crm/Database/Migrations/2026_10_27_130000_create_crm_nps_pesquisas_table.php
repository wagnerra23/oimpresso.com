<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-097 · NPS pós-venda e pós-OS.
 *
 * Uma linha por pesquisa: venda (`transactions`) ou OS do Repair (`repair_job_sheets`) entregue.
 * O cliente responde de 0 a 10 por um link público com `token` — o token é o único dado da
 * URL; a empresa vem da linha, nunca de quem responde (Tier 0, ADR 0093).
 *
 * Não substitui a pesquisa de satisfação da entrega (CSAT 1-5, US-SELL-071,
 * `transaction_satisfaction_surveys`): NPS pergunta "recomendaria?" sobre o relacionamento,
 * cobre também a OS e vai no máximo uma vez a cada 90 dias por cliente.
 *
 * Idempotente (hasTable) + reversível.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_nps_pesquisas')) {
            return;
        }

        Schema::create('crm_nps_pesquisas', function (Blueprint $t) {
            $t->bigIncrements('id');
            // Mesmo tipo e FK de crm_deals.business_id.
            $t->unsignedBigInteger('business_id');
            $t->foreign('business_id', 'crm_nps_biz_fk')->references('id')->on('business')->onDelete('cascade');
            $t->unsignedBigInteger('contact_id');
            $t->string('origem_tipo', 10)->comment('venda|os');
            $t->unsignedBigInteger('origem_id')->comment('transactions.id ou repair_job_sheets.id');
            $t->char('token', 40)->unique('crm_nps_token_uq');
            $t->string('status', 16)->default('nao_enviado')->comment('enviado|nao_enviado|respondido');
            $t->string('motivo', 40)->nullable()->comment('Por que não saiu (sem_canal, sem_celular, ...)');
            $t->unsignedTinyInteger('nota')->nullable()->comment('0-10');
            $t->string('comentario', 1000)->nullable();
            $t->timestamp('enviada_em')->nullable();
            $t->timestamp('respondida_em')->nullable();
            $t->timestamps();

            $t->unique(['business_id', 'origem_tipo', 'origem_id'], 'crm_nps_biz_origem_uq');
            $t->index(['business_id', 'contact_id', 'created_at'], 'crm_nps_biz_contato_idx');
            $t->index(['business_id', 'respondida_em'], 'crm_nps_biz_resp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_nps_pesquisas');
    }
};
