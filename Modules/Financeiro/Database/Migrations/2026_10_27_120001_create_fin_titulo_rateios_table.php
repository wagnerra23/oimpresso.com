<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rateio do título em centros de custo (US-FIN-075). Uma linha por (título, centro).
 * `valor` é sempre gravado e a soma das linhas fecha `fin_titulos.valor_total` em centavos
 * (RateioService). `percentual` é o que o usuário digitou no modo %, ou o derivado no modo valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_titulo_rateios')) {
            return;
        }

        Schema::create('fin_titulo_rateios', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('titulo_id');
            $t->unsignedInteger('centro_custo_id');
            $t->decimal('percentual', 7, 4);
            $t->decimal('valor', 22, 4);
            $t->timestamps();

            $t->unique(['titulo_id', 'centro_custo_id'], 'fin_rateio_titulo_centro_uq');
            $t->index(['business_id', 'centro_custo_id'], 'fin_rateio_business_centro_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('titulo_id')->references('id')->on('fin_titulos')->cascadeOnDelete();
            $t->foreign('centro_custo_id')->references('id')->on('fin_centros_custo')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_titulo_rateios');
    }
};
