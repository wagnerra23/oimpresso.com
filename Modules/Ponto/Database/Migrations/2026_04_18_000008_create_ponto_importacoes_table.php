<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePontoImportacoesTable extends Migration
{
    public function up()
    {
        Schema::create('ponto_importacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->enum('tipo', ['AFD', 'AFDT', 'CSV_CADASTRO', 'CSV_ESCALA']);
            $table->string('nome_arquivo', 255);
            $table->string('arquivo_path', 512);
            $table->string('hash_arquivo', 64)->comment('SHA-256 do arquivo para dedup');
            $table->bigInteger('tamanho_bytes')->unsigned();
            $table->enum('estado', [
                'PENDENTE',
                'PROCESSANDO',
                'CONCLUIDA',
                'CONCLUIDA_COM_ERROS',
                'FALHOU',
            ])->default('PENDENTE');
            $table->integer('linhas_total')->unsigned()->default(0);
            $table->integer('linhas_processadas')->unsigned()->default(0);
            $table->integer('linhas_sucesso')->unsigned()->default(0);
            $table->integer('linhas_erro')->unsigned()->default(0);
            $table->json('erros_amostra')->nullable();
            $table->text('log')->nullable();
            $table->integer('usuario_id')->unsigned();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('usuario_id')->references('id')->on('users');
            $table->unique(['business_id', 'hash_arquivo']);
            $table->index(['business_id', 'estado', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_importacoes');
    }
}
