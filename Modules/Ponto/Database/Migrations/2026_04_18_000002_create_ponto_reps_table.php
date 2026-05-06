<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePontoRepsTable extends Migration
{
    public function up()
    {
        Schema::create('ponto_reps', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->integer('business_id')->unsigned()->index();
            $table->enum('tipo', ['REP_P', 'REP_C', 'REP_A']);
            $table->string('identificador', 17)->unique(); // ID REP (17 chars da Portaria)
            $table->string('descricao', 120);
            $table->string('local', 120)->nullable();
            $table->string('cnpj', 14)->nullable();
            $table->bigInteger('ultimo_nsr')->unsigned()->default(0);
            $table->json('certificado_info')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->index(['business_id', 'tipo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ponto_reps');
    }
}
