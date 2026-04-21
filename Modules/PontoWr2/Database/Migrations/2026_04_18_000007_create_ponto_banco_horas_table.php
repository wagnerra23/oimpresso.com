<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco de horas — saldo por colaborador + ledger de movimentações (append-only).
 * Reforma Trabalhista (Lei 13.467/2017): compensação até 6 meses por acordo individual.
 */
class CreatePontoBancoHorasTable extends Migration
{
    public function up()
    {
        // Saldo corrente por colaborador
        Schema::create('ponto_banco_horas_saldo', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('colaborador_config_id')->unsigned()->unique();
            $table->integer('saldo_minutos')->default(0);
            $table->date('ultima_movimentacao')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('colaborador_config_id')->references('id')->on('ponto_colaborador_config');
        });

        // Ledger de movimentações (append-only)
        Schema::create('ponto_banco_horas_movimentos', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->integer('business_id')->unsigned()->index();
            $table->integer('colaborador_config_id')->unsigned()->index();
            $table->date('data_referencia');
            $table->enum('tipo', ['CREDITO', 'DEBITO', 'PAGAMENTO', 'EXPIRACAO', 'AJUSTE']);
            $table->integer('minutos'); // positivo ou negativo
            $table->decimal('multiplicador', 4, 2)->default(1.00);
            $table->integer('saldo_posterior_minutos');
            $table->integer('apuracao_dia_id')->unsigned()->nullable();
            $table->char('intercorrencia_id', 36)->nullable();
            $table->text('observacao')->nullable();
            $table->integer('usuario_id')->unsigned();
            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at — append-only

            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('colaborador_config_id')->references('id')->on('ponto_colaborador_config');
            $table->foreign('apuracao_dia_id')->references('id')->on('ponto_apuracao_dia');
            $table->foreign('intercorrencia_id')->references('id')->on('ponto_intercorrencias');
            $table->foreign('usuario_id')->references('id')->on('users');
            // Nome explícito — auto-gerado estoura o limite de 64 chars do MySQL
            $table->index(['colaborador_config_id', 'data_referencia'], 'ponto_bh_mov_colab_data_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_banco_horas_movimentos');
        Schema::dropIfExists('ponto_banco_horas_saldo');
    }
}
