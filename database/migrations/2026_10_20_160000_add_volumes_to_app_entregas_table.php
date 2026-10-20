<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Volumes no protocolo de entrega do app (UC-FLX-23 · US-SELL-073).
 * Contrato: memory/requisitos/AppMobile/api/tela-22-entrega.md.
 *
 * Total de volumes entregues (caixas, rolos, pacotes). Nulo = "não informado": as entregas já
 * gravadas e o app antigo, que não manda o campo, continuam válidos. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_entregas') || Schema::hasColumn('app_entregas', 'volumes')) {
            return;
        }

        Schema::table('app_entregas', function (Blueprint $table) {
            $table->unsignedSmallInteger('volumes')->nullable()->after('itens');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('app_entregas') && Schema::hasColumn('app_entregas', 'volumes')) {
            Schema::table('app_entregas', function (Blueprint $table) {
                $table->dropColumn('volumes');
            });
        }
    }
};
