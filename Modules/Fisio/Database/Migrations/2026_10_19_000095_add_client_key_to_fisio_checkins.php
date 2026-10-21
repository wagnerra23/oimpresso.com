<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chave do último envio aplicado ao check-in do dia (etapa 02 §3.6 · T-13 parte 2).
 *
 * O portal manda uma chave por envio. Sem rede, o check-in fica na fila do aparelho lembrado
 * e é reenviado quando a internet volta; se o envio já chegou antes (a resposta é que se
 * perdeu), a mesma chave volta e o servidor responde sucesso sem gravar de novo.
 *
 * Aditiva e idempotente (`Schema::hasColumn`); nula nos check-ins existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_checkins') || Schema::hasColumn('fisio_checkins', 'client_key')) {
            return;
        }

        Schema::table('fisio_checkins', function (Blueprint $t) {
            $t->string('client_key', 64)->nullable()->after('crisis')->comment('chave do último envio aplicado (idempotência da fila offline)');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fisio_checkins') || ! Schema::hasColumn('fisio_checkins', 'client_key')) {
            return;
        }

        Schema::table('fisio_checkins', function (Blueprint $t) {
            $t->dropColumn('client_key');
        });
    }
};
