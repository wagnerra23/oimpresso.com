<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laudo biopsicossocial integrado (US-FISIO-060).
 *
 * Base legal: Lei 15.176/2025, que criou o art. 1º-C na Lei 14.705/2023. A equiparação da
 * pessoa com fibromialgia à pessoa com deficiência depende de avaliação biopsicossocial por
 * equipe multiprofissional, nos termos do art. 2º da Lei 13.146/2015 (LBI). O §1º do art. 2º
 * lista os quatro itens da avaliação, que viram as quatro seções do laudo:
 *   I   impedimentos — funções e estruturas do corpo (costuma ser o médico, CRM)
 *   II  fatores      — fatores socioambientais, psicológicos e pessoais (psicólogo, CRP)
 *   III atividades   — limitação no desempenho de atividades (fisioterapeuta, CREFITO)
 *   IV  participacao — restrição de participação (assistente social, CRESS)
 *
 * `fisio_laudos_bps` é o documento; `fisio_laudos_bps_secoes` guarda cada seção com o próprio
 * autor e a própria assinatura. Seção registrada (`registrado_em` preenchido) não muda nem se
 * apaga: o trigger recusa, igual ao `fisio_records`. Correção é seção nova com `amends_id`.
 * O carimbo (`registrado_por`, `registrado_em`) vem do usuário logado e do relógio do servidor.
 *
 * `assinatura`:
 *   sistema — o próprio usuário da clínica é o autor e assina com o CREFITO do perfil;
 *   externa — o autor é de fora da clínica (sem usuário); a clínica transcreve o texto e o
 *             PDF sai com espaço para a assinatura dele à mão ou por certificado externo.
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
                $t->unsignedInteger('created_by');
                $t->timestamp('created_at')->useCurrent();
                $t->index(['business_id', 'patient_id'], 'fisio_laudos_bps_biz_pac_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('created_by')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('fisio_laudos_bps_secoes')) {
            Schema::create('fisio_laudos_bps_secoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('laudo_id')->constrained('fisio_laudos_bps');
                $t->string('item', 20)->comment('impedimentos | fatores | atividades | participacao');
                $t->text('texto')->nullable();
                $t->string('autor_nome', 120)->nullable();
                $t->string('conselho', 10)->nullable()->comment('CRM | CREFITO | CRP | CRESS');
                $t->string('registro_conselho', 40)->nullable();
                $t->string('assinatura', 10)->comment('sistema | externa');
                $t->unsignedInteger('registrado_por')->nullable();
                $t->dateTime('registrado_em')->nullable();
                $t->foreignId('amends_id')->nullable()->constrained('fisio_laudos_bps_secoes');
                $t->timestamps();
                $t->index(['business_id', 'laudo_id', 'item'], 'fisio_laudos_bps_sec_biz_laudo_item_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('registrado_por')->references('id')->on('users');
            });
        }

        // Triggers só existem em MySQL; na lane sqlite os contratos do Fisio fazem skip (ADR 0358).
        if (DB::getDriverName() === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $op) {
                $nome = 'trg_fisio_laudos_bps_secoes_no_'.strtolower($op);
                DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON fisio_laudos_bps_secoes FOR EACH ROW
                    BEGIN IF OLD.registrado_em IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fisio_laudos_bps_secoes: seção registrada não muda. Correção é seção nova.';
                    END IF; END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_laudos_bps_secoes_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_laudos_bps_secoes_no_delete');
        }
        Schema::dropIfExists('fisio_laudos_bps_secoes');
        Schema::dropIfExists('fisio_laudos_bps');
    }
};
