<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Medicamentos em uso do paciente, estruturados (US-FISIO-063, parte 2).
 *
 * Antes só havia o texto livre "Medicamentos em uso" da avaliação, que continua como está. Aqui
 * cada medicamento é uma linha: nome, dose, frequência, desde quando e até quando. `document_id`
 * aponta a receita de onde veio, quando veio de uma; `source` diz se foi digitado ou transcrito
 * da foto e conferido (parte 3), e `confirmed_by` guarda quem conferiu.
 *
 * Faz parte do prontuário: sem DELETE (model e trigger). Errado se tira da lista com motivo;
 * cada alteração vai para o `activity_log` com o antes e o depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_patient_medications')) {
            Schema::create('fisio_patient_medications', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->string('name', 120);
                $t->string('dose', 60)->nullable();
                $t->string('frequency', 120)->nullable();
                $t->date('started_on')->nullable();
                $t->date('ended_on')->nullable();
                $t->string('source', 12)->default('manual')->comment('manual | transcricao');
                $t->foreignId('document_id')->nullable()->constrained('fisio_patient_documents');
                $t->unsignedInteger('created_by');
                $t->unsignedInteger('confirmed_by')->nullable();
                $t->dateTime('confirmed_at')->nullable();
                $t->dateTime('removed_at')->nullable();
                $t->unsignedInteger('removed_by')->nullable();
                $t->string('removed_reason', 255)->nullable();
                $t->timestamps();
                $t->index(['business_id', 'patient_id'], 'fisio_pat_meds_biz_pac_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('created_by')->references('id')->on('users');
                $t->foreign('confirmed_by')->references('id')->on('users');
                $t->foreign('removed_by')->references('id')->on('users');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_patient_medications_no_delete');
            DB::unprepared("CREATE TRIGGER trg_fisio_patient_medications_no_delete BEFORE DELETE ON fisio_patient_medications FOR EACH ROW
                BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Medicamento do prontuario nao se apaga. Tire da lista com motivo.'; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_patient_medications_no_delete');
        }
        Schema::dropIfExists('fisio_patient_medications');
    }
};
