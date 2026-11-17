<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exames e receitas que o paciente traz (US-FISIO-063).
 *
 * Cada linha é um documento do prontuário: o tipo, a data que está no papel e a observação de quem
 * anexou. O arquivo (foto ou PDF) fica no backbone `Modules/Arquivos` (ADR 0123), ligado a esta
 * linha pelo vínculo polimórfico; nunca há caminho de disco aqui.
 *
 * Faz parte do prontuário (Res. COFFITO 414/2012; Lei 13.787/2018): não se apaga. "Tirar da lista"
 * preenche `removed_at`, `removed_by` e o motivo; a linha e o arquivo continuam. O trigger recusa
 * DELETE no banco, igual ao `fisio_records`.
 *
 * `origin` e `status` já nascem aqui porque o envio pelo portal (parte 4 da US) chega como
 * `pendente` e só entra no prontuário quando alguém da clínica aceita (`reviewed_by`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_patient_documents')) {
            Schema::create('fisio_patient_documents', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->string('kind', 20)->comment('exame_imagem | exame_laboratorial | laudo_medico | receita | outro');
                $t->date('document_date')->nullable();
                $t->string('note', 500)->nullable();
                $t->string('origin', 10)->default('clinica')->comment('clinica | portal');
                $t->string('status', 12)->default('prontuario')->comment('prontuario | pendente | recusado');
                $t->unsignedInteger('created_by')->nullable()->comment('nulo quando veio do portal');
                $t->unsignedInteger('reviewed_by')->nullable();
                $t->dateTime('reviewed_at')->nullable();
                $t->dateTime('removed_at')->nullable();
                $t->unsignedInteger('removed_by')->nullable();
                $t->string('removed_reason', 255)->nullable();
                $t->timestamps();
                $t->index(['business_id', 'patient_id', 'status'], 'fisio_pat_docs_biz_pac_status_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('created_by')->references('id')->on('users');
                $t->foreign('reviewed_by')->references('id')->on('users');
                $t->foreign('removed_by')->references('id')->on('users');
            });
        }

        // Triggers só existem em MySQL; na lane sqlite os contratos do Fisio fazem skip (ADR 0358).
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_patient_documents_no_delete');
            DB::unprepared("CREATE TRIGGER trg_fisio_patient_documents_no_delete BEFORE DELETE ON fisio_patient_documents FOR EACH ROW
                BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Documento do prontuario nao se apaga. Tire da lista com motivo.'; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_patient_documents_no_delete');
        }
        Schema::dropIfExists('fisio_patient_documents');
    }
};
