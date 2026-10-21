<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dia de crise no check-in (US-FISIO-008 · protocolo 11 — fibromialgia).
 *
 * O profissional escreve a versão de crise do programa (`fisio_programs.crisis_items`); no dia
 * ruim, o paciente escolhe "hoje estou em crise" e faz essa versão. Esse dia CONTA como dia
 * cumprido para a sequência e dá o XP do registro (protocolo 11, "Versão de crise"). O software
 * não decide nada: só guarda a escolha do paciente.
 *
 * Aditiva e idempotente (`Schema::hasColumn`); o padrão false não muda check-in existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_checkins') || Schema::hasColumn('fisio_checkins', 'crisis')) {
            return;
        }

        Schema::table('fisio_checkins', function (Blueprint $t) {
            $t->boolean('crisis')->default(false)->after('red_flags')->comment('paciente escolheu a versão de crise do programa');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fisio_checkins') || ! Schema::hasColumn('fisio_checkins', 'crisis')) {
            return;
        }

        Schema::table('fisio_checkins', function (Blueprint $t) {
            $t->dropColumn('crisis');
        });
    }
};