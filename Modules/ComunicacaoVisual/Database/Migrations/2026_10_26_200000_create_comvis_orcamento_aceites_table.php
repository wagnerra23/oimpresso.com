<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-COMVIS-023 — link para o cliente aceitar o orçamento da gráfica.
 *
 * Uma linha por link gerado. Só o sha256 do token fica no banco; o token sai uma vez, para o
 * operador mandar ao cliente. `decisao` nula = link ainda aberto; `aceito` ou `ajuste` = usado
 * (uso único). Gerar outro link fecha o anterior adiantando `expira_em`.
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK; toda leitura filtra por ele.
 * Idempotente: só cria se a tabela não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comvis_orcamento_aceites')) {
            return;
        }

        Schema::create('comvis_orcamento_aceites', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index('comvis_orc_aceites_biz_idx');
            $t->unsignedBigInteger('orcamento_id');
            $t->char('token_hash', 64)->unique('comvis_orc_aceites_token_uq');
            $t->dateTime('expira_em');
            $t->enum('decisao', ['aceito', 'ajuste'])->nullable();
            $t->text('comentario')->nullable();
            $t->dateTime('decidido_em')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'orcamento_id'], 'comvis_orc_aceites_biz_orc_idx');
            $t->foreign('business_id', 'comvis_orc_aceites_biz_fk')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('orcamento_id', 'comvis_orc_aceites_orc_fk')->references('id')->on('comvis_orcamentos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comvis_orcamento_aceites');
    }
};
