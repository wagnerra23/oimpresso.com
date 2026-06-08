<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger de movimentação financeira por conta bancária.
 * Toda baixa de título cria 1 movimento; saldo_apos é snapshot pra debug.
 * origem_tipo + origem_id permite rastrear de onde veio o lançamento.
 */
class CreateFinCaixaMovimentosTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_caixa_movimentos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('conta_bancaria_id')->unsigned();

            $table->enum('tipo', ['entrada', 'saida', 'ajuste', 'transferencia']);
            $table->decimal('valor', 22, 4)->comment('Sempre positivo; tipo define o sinal contábil');
            $table->date('data');
            $table->decimal('saldo_apos', 22, 4)->comment('Snapshot do saldo após este movimento');

            $table->string('origem_tipo', 50)->nullable()->comment('Ex: titulo_baixa, transferencia, manual');
            $table->integer('origem_id')->unsigned()->nullable();

            $table->string('descricao', 255);
            $table->json('metadata')->nullable();

            $table->integer('created_by')->unsigned();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'data'], 'idx_business_data');
            $table->index(['conta_bancaria_id', 'data'], 'idx_conta_data');
            $table->index(['origem_tipo', 'origem_id'], 'idx_origem');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('conta_bancaria_id')->references('id')->on('fin_contas_bancarias');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_caixa_movimentos');
    }
}
