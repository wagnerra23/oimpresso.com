<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pré-anamnese que o paciente responde no portal antes da primeira consulta (Lote 5, protótipo
 * `PacientePreAnamnese.dc.html`). Uma por paciente (UNIQUE): é relato do paciente, não registro
 * assinado do prontuário, por isso tabela própria. Só INSERT — não há rota que altere ou apague.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fisio_pre_anamneses')) {
            return;
        }

        Schema::create('fisio_pre_anamneses', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->foreignId('patient_id')->constrained('fisio_patients');
            $t->json('body');
            $t->json('red_flags');
            $t->string('channel', 30)->default('portal');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['business_id', 'patient_id'], 'fisio_pre_anamneses_biz_pac_uq');
            $t->foreign('business_id')->references('id')->on('business');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_pre_anamneses');
    }
};
