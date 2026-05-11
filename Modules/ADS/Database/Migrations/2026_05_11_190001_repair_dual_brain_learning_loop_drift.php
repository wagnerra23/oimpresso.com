<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Reparo idempotente — schema drift em mcp_dual_brain_decisions.
 *
 * Migration `2026_05_03_200001_add_learning_loop_columns` registrada como Ran [73]
 * no `migrations` table, mas as 7 colunas que ela deveria adicionar NÃO existem
 * em produção (Hostinger). Causa exata desconhecida — hipótese: DDL manual
 * (drop column) ou restore de backup mais antigo após migration registrada.
 *
 * Sintomas em prod (2026-05-11):
 * - `ads:plan-decisions` falha com `Unknown column 'parent_decision_id'`
 * - `ads:review-decisions` falha com `Unknown column 'review_score'`
 * - `ads:auto-generate-tasks` falha com `Unknown column 'auto_generated'`
 * - Brain B autônomo parado
 *
 * Esta migration usa `Schema::hasColumn` antes de cada `ADD COLUMN` pra ser
 * idempotente — segura em qualquer ambiente (local sem drift = no-op).
 *
 * @see Modules/ADS/Database/Migrations/2026_05_03_200001_add_learning_loop_columns.php (original)
 * @see ADR 0094 §5 SoC brutal — procedure_drift check em jana:health-check
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'mcp_dual_brain_decisions';

        Schema::table($table, function (Blueprint $t) use ($table) {
            // T9 PlannerAgent — subtarefas
            if (! Schema::hasColumn($table, 'parent_decision_id')) {
                $t->unsignedBigInteger('parent_decision_id')->nullable()->after('id');
            }

            // T7 Auto Task Generator (vem antes de attempts pela posição original after('event_source'))
            if (! Schema::hasColumn($table, 'auto_generated')) {
                $t->boolean('auto_generated')->default(false)->after('event_source');
            }

            // T18 Retry inteligente
            if (! Schema::hasColumn($table, 'attempts')) {
                $t->unsignedTinyInteger('attempts')->default(0)->after('outcome');
            }
            if (! Schema::hasColumn($table, 'next_retry_at')) {
                $t->timestamp('next_retry_at')->nullable()->after('attempts');
            }

            // T11 ReviewerAgent
            if (! Schema::hasColumn($table, 'review_score')) {
                $t->unsignedTinyInteger('review_score')->nullable()->after('attempts');
            }
            if (! Schema::hasColumn($table, 'review_breakdown')) {
                $t->json('review_breakdown')->nullable()->after('review_score');
            }
            if (! Schema::hasColumn($table, 'review_confidence')) {
                $t->decimal('review_confidence', 4, 3)->nullable()->after('review_breakdown');
            }
        });

        // Índices — só cria se não existe (MySQL: SHOW INDEX FROM)
        $this->addIndexIfMissing($table, 'idx_dbd_parent', 'parent_decision_id');
        $this->addIndexIfMissing($table, 'idx_dbd_next_retry', 'next_retry_at');
        $this->addIndexIfMissing($table, 'idx_dbd_review', 'review_score');
        $this->addIndexIfMissing($table, 'idx_dbd_auto_gen', 'auto_generated');
    }

    public function down(): void
    {
        // Down intencionalmente NO-OP — esta migration é reparo de drift, não
        // mudança intencional. Reverter dropando colunas re-introduziria o
        // drift que estamos consertando. Pra remover de verdade, usar a
        // migration original `2026_05_03_200001_add_learning_loop_columns::down()`.
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        $exists = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        if (empty($exists)) {
            // Confirma que a coluna existe antes de indexar (se hasColumn falhou no up, evita erro)
            if (Schema::hasColumn($table, $column)) {
                DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`{$column}`)");
            }
        }
    }
};
