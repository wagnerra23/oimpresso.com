<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-074 — link para o cliente aprovar o orçamento da venda comum.
 *
 * Mesmo desenho do aceite da gráfica (`comvis_orcamento_aceites`, US-COMVIS-023): uma linha por
 * link, só o sha256 do token no banco, validade em `expira_em`, `decisao` nula = link aberto,
 * uso único. Gerar outro link fecha o anterior adiantando `expira_em`. `nome_cliente` e `ip`
 * registram quem aprovou (o SPEC pede nome, data e IP).
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK; toda leitura filtra por ele.
 * Idempotente: só cria se a tabela não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_orcamento_aceites')) {
            return;
        }

        Schema::create('transaction_orcamento_aceites', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index('tx_orc_aceites_biz_idx');
            $t->unsignedInteger('transaction_id');
            $t->char('token_hash', 64)->unique('tx_orc_aceites_token_uq');
            $t->dateTime('expira_em');
            $t->enum('decisao', ['aprovado', 'ajuste'])->nullable();
            $t->string('nome_cliente', 120)->nullable();
            $t->text('comentario')->nullable();
            $t->string('ip', 45)->nullable();
            $t->dateTime('decidido_em')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'transaction_id'], 'tx_orc_aceites_biz_tx_idx');
            $t->foreign('business_id', 'tx_orc_aceites_biz_fk')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('transaction_id', 'tx_orc_aceites_tx_fk')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_orcamento_aceites');
    }
};
