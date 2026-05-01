<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNfseProviderConfigsTable extends Migration
{
    public function up(): void
    {
        Schema::create('nfse_provider_configs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned();
            $table->string('provider', 30)->default('sn_nfse_federal')
                ->comment('sn_nfse_federal | abrasf (municípios não-aderidos ao SN-NFSe)');
            $table->string('municipio_codigo_ibge', 7);
            $table->string('serie_default', 10)->default('RPS');
            $table->string('cnae', 10)->nullable()->comment('Ex: 6201-5/00');
            $table->string('lc116_codigo_default', 5)->nullable()->comment('Ex: 1.05');
            $table->decimal('aliquota_iss', 5, 4)->nullable()->comment('Ex: 0.0200 = 2%');
            $table->enum('ambiente', ['homologacao', 'producao'])->default('homologacao');
            $table->integer('cert_id')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'municipio_codigo_ibge']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('cert_id')->references('id')->on('nfe_certificados')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_provider_configs');
    }
}
