<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aparelhos que recebem o lembrete de bater ponto (ADR 0422).
 *
 * Uma linha = um token FCM de aparelho, ligado ao usuário e ao business da sessão em que ele
 * foi registrado. O token é ÚNICO: aparelho compartilhado na loja passa para quem logou por
 * último, e só o dono atual recebe o lembrete. `ativo=false` é o opt-out do colaborador.
 */
class CreatePontoPushDispositivosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ponto_push_dispositivos')) {
            return;
        }

        Schema::create('ponto_push_dispositivos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('user_id')->unsigned();
            $table->string('token', 255)->unique('ponto_push_disp_token_uq');
            $table->enum('plataforma', ['android', 'ios']);
            $table->boolean('ativo')->default(true);
            $table->dateTime('ultimo_uso_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'user_id', 'ativo'], 'ponto_push_disp_biz_user_ativo_idx');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_push_dispositivos');
    }
}
