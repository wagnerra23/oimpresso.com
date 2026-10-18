<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fundo do Modules/Fisio — F1 "Constância" (ADR 0437, HANDOFF-F1 §Modelo de dados, US-FISIO-003).
 *
 * Regras que esta migration fixa no banco, e não só no código:
 *  - `business_id` indexado + FK em TODA tabela (ADR 0093). `fisio_exercises.business_id` nulo
 *    = biblioteca global; as demais são NOT NULL.
 *  - Paciente É um `contact` (1:1 por business): UNIQUE(business_id, contact_id).
 *  - Guarda de 20 anos (Lei 13.787/2018): nenhuma FK de dado clínico tem ON DELETE CASCADE.
 *    Apagar o business ou o contact não leva prontuário junto; "excluir paciente" é `blocked_at`.
 *  - `fisio_records` APPEND-ONLY para registro assinado (Res. COFFITO 414/2012): trigger recusa
 *    UPDATE/DELETE de linha com `signed_at` preenchido. Correção é `kind=adendo` + `amends_id`.
 *  - `fisio_game_ledger` APPEND-ONLY (qualquer UPDATE/DELETE), `reason` enum fechado.
 *    Mesmo idioma do `pcp_apontamentos` (Modules/Producao, 2026_10_15).
 *  - Check-in único por paciente e data: UNIQUE(patient_id, date).
 *
 * Sem efeito em empresa sem o pacote `fisio_module`: tabelas novas, vazias, sem tocar em
 * tabela existente.
 */
return new class extends Migration
{
    /** Tabelas append-only: [tabela => condição extra do trigger (null = qualquer linha)]. */
    private const APPEND_ONLY = [
        'fisio_records' => 'OLD.signed_at IS NOT NULL',
        'fisio_game_ledger' => null,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fisio_patients')) {
            Schema::create('fisio_patients', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('contact_id');
                $t->text('main_complaint')->nullable();
                $t->json('goals')->nullable();
                $t->unsignedTinyInteger('sessions_per_week')->default(3)->comment('meta semanal 1..7 (validada na entrada)');
                $t->string('status', 20)->default('ativo');
                // Marcadores de condição que disparam aviso de contraindicação (ADR 0437 §13.2).
                $t->json('condition_flags')->nullable();
                // "Excluir paciente" = bloquear (guarda de 20 anos). Nunca DELETE.
                $t->timestamp('blocked_at')->nullable();
                $t->timestamps();
                $t->unique(['business_id', 'contact_id'], 'fisio_patients_biz_contact_uq');
                $t->index(['business_id', 'status'], 'fisio_patients_biz_status_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('contact_id')->references('id')->on('contacts');
            });
        }

        if (! Schema::hasTable('fisio_records')) {
            Schema::create('fisio_records', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->enum('kind', ['avaliacao', 'evolucao', 'telemonitoramento', 'adendo']);
                $t->json('body');
                // Telemonitoramento (Res. COFFITO 619/2025): modalidade e canal do atendimento remoto.
                $t->string('modality', 20)->nullable();
                $t->string('channel', 30)->nullable();
                // Carimbo (Res. COFFITO 414/2012): quem, CREFITO, quando — do servidor, nunca do corpo.
                $t->unsignedInteger('signed_by')->nullable();
                $t->string('signed_crefito', 40)->nullable();
                $t->dateTime('signed_at')->nullable();
                $t->foreignId('amends_id')->nullable()->constrained('fisio_records');
                $t->timestamp('created_at')->useCurrent();
                $t->index(['business_id', 'patient_id', 'created_at'], 'fisio_records_biz_pac_criado_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('signed_by')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('fisio_consents')) {
            Schema::create('fisio_consents', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                // Separados e versionados (LGPD, ADR 0437 §7): teleatendimento, whatsapp, imagem, marketing, pesquisa_ia...
                $t->string('purpose', 30);
                $t->string('version', 30);
                $t->dateTime('granted_at');
                $t->dateTime('revoked_at')->nullable();
                $t->string('ip', 45)->nullable();
                $t->string('channel', 20)->nullable()->comment('clinica | portal');
                $t->timestamps();
                $t->index(['business_id', 'patient_id', 'purpose'], 'fisio_consents_biz_pac_purpose_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }
        if (! Schema::hasTable('fisio_exercises')) {
            Schema::create('fisio_exercises', function (Blueprint $t) {
                $t->id();
                // NULO = biblioteca global do oimpresso (visível a todo business com o módulo, editável por nenhum).
                $t->unsignedInteger('business_id')->nullable()->index();
                $t->string('name', 160);
                $t->string('region', 40);
                $t->string('type', 40);
                $t->string('pose', 40)->nullable();
                $t->json('steps');
                $t->unsignedSmallInteger('default_sets')->nullable();
                $t->unsignedSmallInteger('default_reps')->nullable();
                $t->unsignedSmallInteger('default_hold_s')->nullable();
                $t->unsignedTinyInteger('times_per_day')->default(1);
                $t->json('safety_notes')->nullable();
                $t->json('contraindication_flags')->nullable();
                $t->boolean('needs_support')->default(false);
                $t->boolean('is_custom')->default(false);
                $t->timestamps();
                $t->index(['business_id', 'region'], 'fisio_exercises_biz_region_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('fisio_protocols')) {
            Schema::create('fisio_protocols', function (Blueprint $t) {
                $t->id();
                // NULO = protocolo-modelo do produto (revisado pela responsável técnica, ADR 0437);
                // preenchido = protocolo da clínica. A clínica trabalha numa CÓPIA do modelo
                // (origin_protocol_id), então revisar o modelo global nunca altera a cópia dela.
                $t->unsignedInteger('business_id')->nullable()->index();
                $t->unsignedBigInteger('origin_protocol_id')->nullable()->index();
                $t->string('name', 160);
                $t->string('region', 40)->nullable();
                // Modelo da clínica com a lógica toda visível (não é SaMD — ADR 0437 §5, P58).
                $t->json('items');
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('origin_protocol_id')->references('id')->on('fisio_protocols')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fisio_programs')) {
            Schema::create('fisio_programs', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->string('phase', 40)->nullable();
                $t->string('status', 20)->default('ativo')->comment('ativo | encerrado');
                $t->enum('origin', ['manual', 'protocolo'])->default('manual');
                // Instrução ESTÁTICA escrita pelo profissional; é o que o paciente vê com dor alta (ADR 0437 §5).
                $t->text('stop_instruction')->nullable();
                $t->unsignedTinyInteger('pain_alert_threshold')->nullable()->comment('nulo = config fisio.pain_alert_threshold_padrao');
                $t->enum('pain_alert_mode', ['absoluto', 'sobre_basal'])->default('absoluto');
                // Versão de crise escrita pelo profissional (não gerada pelo software).
                $t->json('crisis_items')->nullable();
                $t->unsignedInteger('created_by');
                $t->date('starts_at')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'patient_id', 'status'], 'fisio_programs_biz_pac_status_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('created_by')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('fisio_program_items')) {
            Schema::create('fisio_program_items', function (Blueprint $t) {
                $t->id();
                // Cópia do business do programa, preenchida pelo model (ADR 0093: business_id em toda tabela).
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('program_id')->constrained('fisio_programs');
                $t->foreignId('exercise_id')->constrained('fisio_exercises');
                $t->unsignedSmallInteger('sets')->nullable();
                $t->unsignedSmallInteger('reps')->nullable();
                $t->unsignedSmallInteger('hold_s')->nullable();
                $t->unsignedTinyInteger('times_per_day')->default(1);
                $t->text('notes')->nullable();
                $t->unsignedSmallInteger('position')->default(1);
                // Contraindicação AVISA, não bloqueia (ADR 0437 §13.2): quem confirmou, o quê, quando e por quê.
                $t->json('contraindication_ack_flags')->nullable();
                $t->unsignedInteger('contraindication_ack_by')->nullable();
                $t->dateTime('contraindication_ack_at')->nullable();
                $t->text('contraindication_justification')->nullable();
                $t->timestamps();
                $t->index(['program_id', 'position'], 'fisio_prog_items_prog_pos_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('contraindication_ack_by')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('fisio_checkins')) {
            Schema::create('fisio_checkins', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->foreignId('program_id')->nullable()->constrained('fisio_programs');
                $t->date('date');
                $t->json('done_item_ids')->nullable();
                $t->unsignedTinyInteger('pain')->nullable()->comment('0..10');
                $t->string('effort', 20)->nullable();
                $t->text('note')->nullable();
                $t->json('red_flags')->nullable();
                $t->timestamps();
                $t->unique(['patient_id', 'date'], 'fisio_checkins_pac_date_uq');
                $t->index(['business_id', 'date'], 'fisio_checkins_biz_date_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }
        if (! Schema::hasTable('fisio_game_ledger')) {
            Schema::create('fisio_game_ledger', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->date('date');
                // Enum fechado: insert fora dele falha. A etapa 02 propõe mais razões — decisão da US-FISIO-008.
                $t->enum('reason', ['exercicio_concluido', 'checkin']);
                $t->unsignedSmallInteger('xp');
                $t->timestamp('created_at')->useCurrent();
                $t->index(['business_id', 'patient_id', 'date'], 'fisio_ledger_biz_pac_date_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('fisio_questionnaires')) {
            Schema::create('fisio_questionnaires', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                // Só os de uso livre (ADR 0437 §8). ODI, NDI, KOOS, QuickDASH e WOMAC ficam fora até haver licença.
                $t->enum('instrument', ['eva', 'rmdq', 'psfs', 'lefs', 'spadi']);
                $t->json('answers');
                $t->decimal('score', 6, 2)->nullable();
                $t->dateTime('answered_at');
                $t->timestamps();
                $t->index(['business_id', 'patient_id', 'instrument'], 'fisio_quest_biz_pac_instr_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('fisio_portal_tokens')) {
            Schema::create('fisio_portal_tokens', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                // Só o hash: o token e o PIN em claro existem apenas na mensagem ao paciente.
                $t->char('token_hash', 64)->unique();
                $t->string('pin_hash', 255);
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->dateTime('locked_until')->nullable();
                $t->dateTime('expires_at');
                $t->dateTime('revoked_at')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'patient_id'], 'fisio_tokens_biz_pac_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        // Triggers só existem em MySQL; na lane sqlite os contratos do Fisio fazem skip (ADR 0358).
        if (DB::getDriverName() === 'mysql') {
            foreach (self::APPEND_ONLY as $tabela => $condicao) {
                foreach (['UPDATE', 'DELETE'] as $op) {
                    $nome = "trg_{$tabela}_no_".strtolower($op);
                    $sinal = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$tabela} é append-only. Correção é lançamento novo.'";
                    $corpo = $condicao === null ? $sinal : "BEGIN IF {$condicao} THEN {$sinal}; END IF; END";
                    DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                    DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON {$tabela} FOR EACH ROW {$corpo}");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (array_keys(self::APPEND_ONLY) as $tabela) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_{$tabela}_no_update");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_{$tabela}_no_delete");
            }
        }
        foreach ([
            'fisio_portal_tokens', 'fisio_questionnaires', 'fisio_game_ledger', 'fisio_checkins',
            'fisio_program_items', 'fisio_programs', 'fisio_protocols', 'fisio_exercises',
            'fisio_consents', 'fisio_records', 'fisio_patients',
        ] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};