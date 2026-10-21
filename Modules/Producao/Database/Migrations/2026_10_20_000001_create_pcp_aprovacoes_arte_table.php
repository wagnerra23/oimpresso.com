<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cliente aprova a arte por link (UC-FLX-02 · US-COMVIS-NEW-004).
 *
 * Um pedido por envio da prova de uma OS por item (`pcp_ordens`). O token vai só no link; aqui fica
 * o sha256 dele. `decisao` nula = aguardando; preenchida uma vez (uso único). `arte_programacao_id`
 * é a etapa de arte que volta como etapa nova se o cliente recusar. Escopo por empresa (ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_aprovacoes_arte')) {
            return;
        }
        Schema::create('pcp_aprovacoes_arte', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->foreignId('ordem_id')->constrained('pcp_ordens')->cascadeOnDelete();
            $t->foreignId('programacao_id')->constrained('pcp_programacoes')->comment('etapa Aprovação do cliente');
            $t->foreignId('arte_programacao_id')->constrained('pcp_programacoes')->comment('etapa de arte que volta se recusar');
            $t->char('token_hash', 64)->unique();
            $t->string('arquivo', 500)->comment('referência do arquivo da prova (URL ou caminho)');
            $t->dateTime('expira_em');
            $t->enum('decisao', ['aprovada', 'recusada'])->nullable();
            $t->text('comentario')->nullable();
            $t->dateTime('decidido_em')->nullable();
            $t->unsignedInteger('created_by');
            $t->timestamps();
            $t->index(['business_id', 'ordem_id'], 'pcp_aprov_arte_biz_ordem_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcp_aprovacoes_arte');
    }
};
