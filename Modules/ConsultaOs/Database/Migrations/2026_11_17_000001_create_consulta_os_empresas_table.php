<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código público de cada empresa no portal /consulta-os/{codigo} (decisão [W] 2026-11-17:
 * "empresa na URL, celular só com série").
 *
 * Tabela-ponte em vez de coluna em `business` (core UltimatePOS, proibições §Código). O código é
 * aleatório e não sai do id: o `business.uuid` já é o identificador do webhook do WhatsApp
 * (VerifyWhatsmeowSignature) e o id é sequencial — nenhum dos dois vai para link público.
 * As linhas nascem sob demanda (EmpresaPublica::codigoDe); sem backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consulta_os_empresas')) {
            return;
        }

        Schema::create('consulta_os_empresas', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->string('codigo', 16);
            $t->timestamps();

            $t->unique('business_id', 'consulta_os_empresas_business_unique');
            $t->unique('codigo', 'consulta_os_empresas_codigo_unique');
            $t->foreign('business_id', 'consulta_os_empresas_business_fk')
                ->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_os_empresas');
    }
};
