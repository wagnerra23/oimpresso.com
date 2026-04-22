<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLicencaComputadorTable extends Migration
{
    public function up()
    {
        Schema::create('licenca_computador', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id'); // Ajustado para unsignedInteger

            $table->string('hd', 50)->nullable();
            $table->string('user_win', 191)->nullable();

            $table->boolean('bloqueado')->default(false);

            $table->string('tipodeacesso', 50)->nullable();
            $table->string('conexao', 100)->nullable();
            $table->string('usuario', 15)->nullable();
            $table->string('senha', 15)->nullable();
            $table->string('sistema_operacional', 50)->nullable();
            $table->string('ip_interno', 15)->nullable();
            $table->string('antivirus', 15)->nullable();
            $table->string('pasta_instalacao', 255)->nullable();
            $table->string('versao_exe', 15)->nullable();
            $table->string('versao_banco', 15)->nullable();
            $table->timestamp('data')->nullable();
            $table->timestamp('dt_ultima_assistencia')->nullable();

            $table->char('backup_automatico', 1)->nullable();
            $table->char('paf', 1)->nullable();
            $table->string('processador', 50)->nullable();
            $table->string('memoria', 20)->nullable();
            $table->string('velocidade_conexao', 20)->nullable();
            $table->string('impressora_fiscal', 50)->nullable();
            $table->string('leitor_barras', 50)->nullable();
            $table->char('gera_mensalidade', 1)->nullable();
            $table->string('hostname', 50)->nullable();
            $table->char('liberado', 1)->nullable();
            $table->timestamp('dt_validade')->nullable();
            $table->string('serial', 20)->nullable();
            $table->string('contra_senha', 20)->nullable();
            $table->char('oculto', 1)->nullable();
            $table->double('valor')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->string('caminho_banco', 255)->nullable();
            $table->timestamp('dt_ultimo_acesso')->nullable();
            $table->timestamps(); // Adiciona 'created_at' e 'updated_at'

            // Defina a chave estrangeira
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('licenca_computador');
    }
}
