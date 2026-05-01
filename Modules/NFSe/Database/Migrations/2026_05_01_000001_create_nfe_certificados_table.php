<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNfeCertificadosTable extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_certificados', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->text('cert_pfx_encrypted')->comment('Conteúdo .pfx criptografado (AES-256)');
            $table->string('senha_encrypted', 512)->comment('Senha do .pfx criptografada');
            $table->date('valido_ate');
            $table->string('titular_cnpj', 18)->nullable();
            $table->string('titular_nome', 150)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'ativo']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_certificados');
    }
}
