<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baixas (pagamentos parciais ou totais) de fin_titulos.
 * Idempotência via UNIQUE (business_id, idempotency_key) — TECH-0001.
 * Estorno via row negativa com estorno_de_id (NÃO hard delete) — TECH-0002.
 * transaction_payment_id linka retro com core (criado quando origem=venda/compra).
 */
class CreateFinTituloBaixasTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_titulo_baixas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('titulo_id')->unsigned();
            $table->integer('conta_bancaria_id')->unsigned();

            $table->decimal('valor_baixa', 22, 4);
            $table->decimal('juros', 22, 4)->default(0);
            $table->decimal('multa', 22, 4)->default(0);
            $table->decimal('desconto', 22, 4)->default(0);

            $table->date('data_baixa');
            $table->enum('meio_pagamento', [
                'dinheiro',
                'pix',
                'boleto',
                'cartao_credito',
                'cartao_debito',
                'transferencia',
                'cheque',
                'compensacao',
                'outro',
            ]);

            $table->char('idempotency_key', 36)->comment('UUID gerado pelo frontend; protege contra dupla');
            $table->integer('transaction_payment_id')->unsigned()->nullable()->comment('FK soft -> transaction_payments.id');
            $table->integer('estorno_de_id')->unsigned()->nullable()->comment('Self-FK para estornos (ledger style)');

            $table->text('observacoes')->nullable();
            $table->integer('created_by')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at — baixa é imutável (estorno cria nova row)

            $table->unique(['business_id', 'idempotency_key'], 'uk_baixa_idempotency');
            $table->index(['titulo_id'], 'idx_titulo');
            $table->index(['business_id', 'data_baixa'], 'idx_business_data');
            $table->index(['conta_bancaria_id', 'data_baixa'], 'idx_conta_data');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('titulo_id')->references('id')->on('fin_titulos')->onDelete('cascade');
            $table->foreign('conta_bancaria_id')->references('id')->on('fin_contas_bancarias');
            $table->foreign('estorno_de_id')->references('id')->on('fin_titulo_baixas')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_titulo_baixas');
    }
}
