<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FIN-081 — link para o cliente pagar um título a receber.
 *
 * Uma linha por link gerado. Só o sha256 do token fica no banco; o token sai uma vez, para o
 * operador mandar ao cliente. Gerar outro link do mesmo título fecha o anterior adiantando
 * `expira_em`. A baixa não mora aqui: vem do webhook da cobrança (`origem_type = fin_titulo`).
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK; toda leitura filtra por ele.
 * Idempotente: só cria se a tabela não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_titulo_links_pagamento')) {
            return;
        }

        Schema::create('fin_titulo_links_pagamento', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index('fin_tit_link_biz_idx');
            $t->unsignedInteger('titulo_id');
            $t->char('token_hash', 64)->unique('fin_tit_link_token_uq');
            $t->dateTime('expira_em');
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'titulo_id'], 'fin_tit_link_biz_tit_idx');
            $t->foreign('business_id', 'fin_tit_link_biz_fk')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('titulo_id', 'fin_tit_link_tit_fk')->references('id')->on('fin_titulos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_titulo_links_pagamento');
    }
};
