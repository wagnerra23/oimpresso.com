<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Travamento do estudo (etapa 07 P-07, §4.4). Travar é uma vez só e não volta: a exportação
 * passa a usar só o que estava gravado até `locked_at`, e mudar participante ou evento do
 * estudo depois exige justificativa, registrada no activity_log. Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_studies') || Schema::hasColumn('fisio_studies', 'locked_at')) {
            return;
        }
        Schema::table('fisio_studies', function (Blueprint $t) {
            $t->dateTime('locked_at')->nullable()->after('status');
            $t->unsignedInteger('locked_by')->nullable()->after('locked_at');
            $t->string('lock_reason', 500)->nullable()->after('locked_by');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fisio_studies') && Schema::hasColumn('fisio_studies', 'locked_at')) {
            Schema::table('fisio_studies', function (Blueprint $t) {
                $t->dropColumn(['locked_at', 'locked_by', 'lock_reason']);
            });
        }
    }
};
