<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conferido per-user (Onda Edit 2026-05-18).
 *
 * Eliana confere ≠ Wagner confere — audit trail mostra QUEM marcou. Substitui
 * persistência localStorage do FinConferidoToggle (Onda 5 R1) por DB sync MCP.
 *
 * Per-user (FK users.id ON DELETE SET NULL): user removido preserva audit do título,
 * só desliga vínculo de identidade. Idempotente (`Schema::hasColumn`) — sobrevive re-run.
 */
class AddConferidoToFinTitulos extends Migration
{
    public function up(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            if (! Schema::hasColumn('fin_titulos', 'conferido_by')) {
                $table->integer('conferido_by')->unsigned()->nullable()
                    ->after('updated_by')
                    ->comment('FK users.id — quem marcou como conferido (per-user audit)');
            }
            if (! Schema::hasColumn('fin_titulos', 'conferido_at')) {
                $table->timestamp('conferido_at')->nullable()
                    ->after('conferido_by')
                    ->comment('Timestamp da conferência');
            }
        });

        // FK em bloco separado (idempotente — verifica se já existe pelo info schema).
        // MySQL não tem IF NOT EXISTS pra FK; usa try/catch via raw query check.
        $database = config('database.connections.'.config('database.default').'.database');
        $fkExists = collect(\DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'fin_titulos'
             AND COLUMN_NAME = 'conferido_by' AND REFERENCED_TABLE_NAME = 'users'",
            [$database]
        ))->isNotEmpty();

        if (! $fkExists) {
            Schema::table('fin_titulos', function (Blueprint $table) {
                $table->foreign('conferido_by', 'fk_titulo_conferido_by')
                    ->references('id')->on('users')
                    ->onDelete('set null');
            });
        }

        // Index pra queries "conferidos por user X" e "não conferidos do business".
        if (! collect(\DB::select(
            "SHOW INDEX FROM fin_titulos WHERE Key_name = 'idx_business_conferido'"
        ))->isNotEmpty()) {
            Schema::table('fin_titulos', function (Blueprint $table) {
                $table->index(['business_id', 'conferido_by'], 'idx_business_conferido');
            });
        }
    }

    public function down(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            if (collect(\DB::select(
                "SHOW INDEX FROM fin_titulos WHERE Key_name = 'idx_business_conferido'"
            ))->isNotEmpty()) {
                $table->dropIndex('idx_business_conferido');
            }
        });

        $database = config('database.connections.'.config('database.default').'.database');
        $fkExists = collect(\DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'fin_titulos'
             AND CONSTRAINT_NAME = 'fk_titulo_conferido_by'",
            [$database]
        ))->isNotEmpty();

        if ($fkExists) {
            Schema::table('fin_titulos', function (Blueprint $table) {
                $table->dropForeign('fk_titulo_conferido_by');
            });
        }

        Schema::table('fin_titulos', function (Blueprint $table) {
            if (Schema::hasColumn('fin_titulos', 'conferido_at')) {
                $table->dropColumn('conferido_at');
            }
            if (Schema::hasColumn('fin_titulos', 'conferido_by')) {
                $table->dropColumn('conferido_by');
            }
        });
    }
}
