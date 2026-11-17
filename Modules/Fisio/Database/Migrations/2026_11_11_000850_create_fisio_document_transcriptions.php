<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transcrição sugerida pela IA a partir da foto do exame ou da receita (US-FISIO-063, parte 3).
 *
 * `suggested` guarda o que a IA leu, campo a campo, sem interpretação (ADR 0437 §5; proposta da
 * ADR 0450). Nada daqui vai para o prontuário até o profissional conferir: `confirmed` guarda o
 * que ele gravou, `corrected` quais campos ele mudou em relação à sugestão, e `decided_by` quem
 * conferiu ou descartou. `model` registra quem respondeu.
 *
 * Sem DELETE (model e trigger): é a prova do que a IA sugeriu e do que a pessoa decidiu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_document_transcriptions')) {
            Schema::create('fisio_document_transcriptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('document_id')->constrained('fisio_patient_documents');
                $t->string('status', 12)->default('pendente')->comment('pendente | conferida | descartada');
                $t->json('suggested');
                $t->json('confirmed')->nullable();
                $t->json('corrected')->nullable()->comment('campos que o profissional mudou em relação à sugestão');
                $t->string('model', 80)->nullable();
                $t->unsignedInteger('requested_by');
                $t->unsignedInteger('decided_by')->nullable();
                $t->dateTime('decided_at')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'document_id'], 'fisio_doc_transcr_biz_doc_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('requested_by')->references('id')->on('users');
                $t->foreign('decided_by')->references('id')->on('users');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_document_transcriptions_no_delete');
            DB::unprepared("CREATE TRIGGER trg_fisio_document_transcriptions_no_delete BEFORE DELETE ON fisio_document_transcriptions FOR EACH ROW
                BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transcricao conferida e prova do prontuario. Nao se apaga.'; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_document_transcriptions_no_delete');
        }
        Schema::dropIfExists('fisio_document_transcriptions');
    }
};
