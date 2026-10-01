<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lembretes de bater ponto já disparados (ADR 0422 §6) — impede o envio repetido.
 *
 * Uma linha por (business, usuário, dia, tipo de marcação). O comando grava a linha ANTES de
 * despachar o Job; se dois ticks se sobrepuserem, o índice único deixa só um passar.
 */
class CreatePontoPushEnviosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ponto_push_envios')) {
            return;
        }

        Schema::create('ponto_push_envios', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('user_id')->unsigned();
            $table->date('data');
            $table->string('tipo', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['business_id', 'user_id', 'data', 'tipo'], 'ponto_push_envios_uq');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_push_envios');
    }
}
