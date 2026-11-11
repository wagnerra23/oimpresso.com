<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laudo de avaliação biopsicossocial para fibromialgia (US-FISIO-060).
 *
 * Base: Lei 15.176/2025 (art. 1º-C da Lei 14.705/2023) e, em Santa Catarina, o modelo aprovado
 * pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026), versão 1.0
 * de 26/10/2026, usado para a Carteira de Identificação da Pessoa com Fibromialgia (CIPF).
 *
 * `fisio_laudos_bps` guarda o formulário inteiro em `dados` (identificação, matriz IFBr-M, fatores
 * contextuais, conclusão da equipe, local e data) e o `modelo` que ele segue. `fisio_laudos_bps_equipe`
 * guarda quem assina, uma linha por profissional, com a área do formulário.
 *
 * Laudo finalizado (`finalizado_em`) não muda, nem a equipe dele: trigger nas duas tabelas, igual ao
 * `fisio_records`. Correção é laudo novo com `amends_id` apontando o anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_laudos_bps')) {
            Schema::create('fisio_laudos_bps', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->string('modelo', 30)->comment('ses-sc-cipf-1.0');
                $t->json('dados')->nullable();
                $t->unsignedInteger('created_by');
                $t->unsignedInteger('finalizado_por')->nullable();
                $t->dateTime('finalizado_em')->nullable();
                $t->foreignId('amends_id')->nullable()->constrained('fisio_laudos_bps');
                $t->timestamps();
                $t->index(['business_id', 'patient_id'], 'fisio_laudos_bps_biz_pac_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('created_by')->references('id')->on('users');
                $t->foreign('finalizado_por')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('fisio_laudos_bps_equipe')) {
            Schema::create('fisio_laudos_bps_equipe', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('laudo_id')->constrained('fisio_laudos_bps');
                $t->string('area', 30)->comment('medicina | enfermagem | psicologia | servico_social | fisioterapia_to');
                $t->string('nome', 120);
                $t->string('registro', 40);
                $t->string('assinatura', 10)->comment('sistema | externa');
                $t->unsignedInteger('incluido_por');
                $t->timestamps();
                $t->index(['business_id', 'laudo_id'], 'fisio_laudos_bps_eq_biz_laudo_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('incluido_por')->references('id')->on('users');
            });
        }

        // Triggers só existem em MySQL; na lane sqlite os contratos do Fisio fazem skip (ADR 0358).
        if (DB::getDriverName() === 'mysql') {
            $msg = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Laudo biopsicossocial finalizado não muda. Correção é laudo novo.'";
            foreach (['UPDATE', 'DELETE'] as $op) {
                $nome = 'trg_fisio_laudos_bps_no_'.strtolower($op);
                DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON fisio_laudos_bps FOR EACH ROW
                    BEGIN IF OLD.finalizado_em IS NOT NULL THEN {$msg}; END IF; END");
            }
            foreach (['INSERT' => 'NEW', 'UPDATE' => 'OLD', 'DELETE' => 'OLD'] as $op => $linha) {
                $nome = 'trg_fisio_laudos_bps_equipe_no_'.strtolower($op);
                DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON fisio_laudos_bps_equipe FOR EACH ROW
                    BEGIN IF (SELECT finalizado_em FROM fisio_laudos_bps WHERE id = {$linha}.laudo_id) IS NOT NULL THEN {$msg}; END IF; END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['update', 'delete'] as $op) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_fisio_laudos_bps_no_{$op}");
            }
            foreach (['insert', 'update', 'delete'] as $op) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_fisio_laudos_bps_equipe_no_{$op}");
            }
        }
        Schema::dropIfExists('fisio_laudos_bps_equipe');
        Schema::dropIfExists('fisio_laudos_bps');
    }
};
