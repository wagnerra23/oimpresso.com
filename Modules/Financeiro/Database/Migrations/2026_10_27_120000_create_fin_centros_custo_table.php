<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centros de custo por empresa (US-FIN-075). Cadastro simples: código opcional, nome, ativo.
 * O rateio do título mora em `fin_titulo_rateios`; título sem rateio segue válido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_centros_custo')) {
            return;
        }

        Schema::create('fin_centros_custo', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id')->index();
            $t->string('codigo', 20)->nullable();
            $t->string('nome', 100);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->softDeletes();

            $t->index(['business_id', 'ativo'], 'fin_cc_business_ativo_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_centros_custo');
    }
};
