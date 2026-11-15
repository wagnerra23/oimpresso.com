<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Momentos de avaliação e participantes do estudo (etapa 07 P-04, ADR 0437 §7).
 *
 * - `fisio_study_moments`: entrada, 4, 8, 12 semanas... cada um com o dia (a partir da entrada
 *   do participante), a janela de tolerância em dias e os questionários que vencem nele.
 * - `fisio_study_participants`: o paciente no estudo, com um código que NÃO deriva de nome nem
 *   de CPF (aleatório, único no estudo). A tabela é a chave de volta código → paciente e fica
 *   só na clínica. Saída (perda de seguimento, revogação, encerramento) é registrada com motivo
 *   e data; a linha nunca é apagada.
 *
 * Pendência não é tabela: sai do momento + data de entrada + questionários respondidos.
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_study_moments')) {
            Schema::create('fisio_study_moments', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('study_id')->constrained('fisio_studies');
                $t->string('label', 60);
                $t->unsignedSmallInteger('day_offset')->comment('dias depois da entrada do participante');
                $t->unsignedSmallInteger('window_days')->default(0)->comment('tolerância ± dias');
                $t->json('instruments')->comment('questionários que vencem neste momento');
                $t->unsignedSmallInteger('sort')->default(0);
                $t->timestamps();
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('fisio_study_participants')) {
            Schema::create('fisio_study_participants', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('study_id')->constrained('fisio_studies');
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->string('code', 20)->comment('código do participante; não deriva de nome nem CPF');
                $t->date('entered_on');
                $t->foreignId('consent_id')->nullable()->constrained('fisio_consents');
                $t->date('exited_on')->nullable();
                $t->string('exit_type', 30)->nullable()->comment('perda_seguimento | revogacao | conclusao | outro');
                $t->string('exit_reason', 500)->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->unsignedInteger('exited_by')->nullable();
                $t->timestamps();
                $t->unique(['study_id', 'patient_id'], 'fisio_study_part_study_pac_uq');
                $t->unique(['study_id', 'code'], 'fisio_study_part_study_code_uq');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_study_participants');
        Schema::dropIfExists('fisio_study_moments');
    }
};
