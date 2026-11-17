<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sugestões do assistente de IA da pesquisadora (etapa 07 P-08; decisão [W] 2026-11-11:
 * "seria um auxílio para pesquisador, ele confirma se quiser").
 *
 * Só dentro da área do estudo: nada daqui vai para o paciente, o prontuário ou a tela clínica
 * (RDC 657/2022; ADR 0437 §5, ADR 0447). Cada sugestão nasce `pendente` e a pesquisadora aceita
 * ou descarta; quem e quando ficam gravados. Não é apagada. Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fisio_study_ai_suggestions')) {
            return;
        }
        Schema::create('fisio_study_ai_suggestions', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->foreignId('study_id')->constrained('fisio_studies');
            $t->string('kind', 20)->comment('qualidade | resumo | padrao');
            $t->text('text');
            $t->string('status', 20)->default('pendente')->comment('pendente | aceita | descartada');
            $t->unsignedInteger('requested_by')->nullable();
            $t->unsignedInteger('decided_by')->nullable();
            $t->dateTime('decided_at')->nullable();
            $t->string('model', 80)->nullable();
            $t->timestamps();
            $t->index(['study_id', 'status'], 'fisio_study_ai_sug_estudo_status_idx');
            $t->foreign('business_id')->references('id')->on('business');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_study_ai_suggestions');
    }
};
