<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinais de alerta do check-in que JÁ chegaram ao profissional (auditoria do Fisio d4, 2026-11-05).
 *
 * O aviso roda depois do check-in gravado. Antes desta coluna, uma falha no aviso sumia: no
 * reenvio os sinais já estavam no check-in e ninguém era avisado. Agora "pendente" é
 * `red_flags` menos `red_flags_avisados`, e o `fisio:reenviar-alertas` tenta de novo.
 *
 * Aditiva e idempotente. Os check-ins existentes nascem com tudo avisado (cópia de
 * `red_flags`): o comando não reenvia alerta antigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_checkins') || Schema::hasColumn('fisio_checkins', 'red_flags_avisados')) {
            return;
        }

        Schema::table('fisio_checkins', function (Blueprint $t) {
            $t->json('red_flags_avisados')->nullable()->after('red_flags')->comment('sinais de alerta já avisados ao profissional');
        });

        DB::table('fisio_checkins')->update(['red_flags_avisados' => DB::raw('red_flags')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fisio_checkins') || ! Schema::hasColumn('fisio_checkins', 'red_flags_avisados')) {
            return;
        }

        Schema::table('fisio_checkins', function (Blueprint $t) {
            $t->dropColumn('red_flags_avisados');
        });
    }
};
