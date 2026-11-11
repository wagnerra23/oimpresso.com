<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos adversos do estudo (etapa 07 P-06, ADR 0437 §5).
 *
 * Nascem do check-in de quem está no estudo: crise por mais de 48 h (3 dias seguidos com
 * `crisis`) e alerta de dor (dor no limiar em vigor do programa, a mesma regra que pausa o
 * jogo). O software registra e mostra; NÃO classifica gravidade. A pesquisadora revisa e
 * escreve a nota; o que comunicar ao CEP é decisão dela.
 *
 * Um evento por participante × origem × dia de início (idempotente no reenvio do check-in).
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fisio_study_adverse_events')) {
            return;
        }
        Schema::create('fisio_study_adverse_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->foreignId('study_id')->constrained('fisio_studies');
            $t->foreignId('participant_id')->constrained('fisio_study_participants');
            $t->string('origin', 30)->comment('crise_48h | alerta_dor');
            $t->date('started_on');
            $t->date('ended_on');
            $t->json('detail')->nullable()->comment('dor, limiar e dias, como vieram do check-in');
            $t->dateTime('reviewed_at')->nullable();
            $t->unsignedInteger('reviewed_by')->nullable();
            $t->string('review_note', 1000)->nullable()->comment('nota da pesquisadora; o software não classifica');
            $t->timestamps();
            $t->unique(['participant_id', 'origin', 'started_on'], 'fisio_study_ae_part_orig_dia_uq');
            $t->foreign('business_id')->references('id')->on('business');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_study_adverse_events');
    }
};
