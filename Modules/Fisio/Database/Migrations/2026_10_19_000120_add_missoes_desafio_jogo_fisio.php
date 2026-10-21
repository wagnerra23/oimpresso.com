<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Missões do dia e desafio semanal do jogo de constância (US-FISIO-008, T-08c, etapa 02 §3.4).
 *
 *  - `fisio_game_ledger.reason` ganha `missao` e `desafio_semanal`; `ref` diz QUAL missão
 *    (`registro`, `exercicio:<item>`, `series:<item>`) ou QUAL semana (segunda) o lançamento
 *    paga. O ledger segue append-only: os triggers de UPDATE/DELETE não mudam; o trigger do
 *    enum fechado é recriado com a lista nova (o banco roda `strict => false`).
 *  - `fisio_game_desafios`: a decisão do paciente sobre o desafio da semana (aceitou com meta
 *    N, ou recusou). Uma por semana; append-only (decisão não se edita). Recusar não lança nada.
 *
 * Medalha não entra no ledger: é derivada do histórico (MedalhasCalculator), vale 0 XP.
 */
return new class extends Migration
{
    /** Fonte única do enum e do trigger; igual ao bloco de memory/dominio/fisio.md. */
    private const LEDGER_REASONS = ['exercicio_concluido', 'checkin', 'missao', 'desafio_semanal'];

    public function up(): void
    {
        if (! Schema::hasTable('fisio_game_ledger')) {
            return;
        }

        Schema::table('fisio_game_ledger', function (Blueprint $t) {
            $t->enum('reason', ['exercicio_concluido', 'checkin', 'missao', 'desafio_semanal'])->change(); // manter igual a LEDGER_REASONS (o dominio:check lê o literal)
        });
        if (! Schema::hasColumn('fisio_game_ledger', 'ref')) {
            Schema::table('fisio_game_ledger', function (Blueprint $t) {
                $t->string('ref', 40)->nullable()->after('reason')->comment('missão (registro|exercicio:<item>|series:<item>) ou semana do desafio');
            });
        }

        if (! Schema::hasTable('fisio_game_desafios')) {
            Schema::create('fisio_game_desafios', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->date('week_start')->comment('segunda-feira da semana');
                $t->boolean('accepted');
                $t->unsignedTinyInteger('target_days')->nullable()->comment('dias da semana a cumprir; nulo se recusou');
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['patient_id', 'week_start'], 'fisio_desafios_pac_semana_uq');
                $t->index(['business_id', 'patient_id'], 'fisio_desafios_biz_pac_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $lista = "'".implode("','", self::LEDGER_REASONS)."'";
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_game_ledger_reason');
            DB::unprepared("CREATE TRIGGER trg_fisio_game_ledger_reason BEFORE INSERT ON fisio_game_ledger FOR EACH ROW
                BEGIN IF NEW.reason IS NULL OR NEW.reason NOT IN ({$lista}) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fisio_game_ledger: razão fora do enum fechado (ADR 0437 §6).';
                END IF; END");
            foreach (['UPDATE', 'DELETE'] as $op) {
                $nome = 'trg_fisio_game_desafios_no_'.strtolower($op);
                DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON fisio_game_desafios FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fisio_game_desafios é append-only. Decisão não se edita.'");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_game_desafios_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_game_desafios_no_delete');
        }
        Schema::dropIfExists('fisio_game_desafios');
        // O enum e o `ref` ficam: o ledger é append-only e pode já ter linhas de missão.
    }
};
