<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * jana_ui_judge_runs — colunas de self-consistency (robustez do juiz · 2026-06-23).
 *
 * O PR UI Judge passou de single-shot pra N amostras agregadas (UiJudgeConsensus,
 * dossiê 2026-06-23-arte-validacao-L3-humano-judge §3b). Agora cada run mede TAMBÉM:
 *  - confidence — confiança geral 0-1 (menor concordância entre as 3 dims semânticas)
 *  - samples    — quantas amostras válidas entraram na mediana
 *
 * Sem essas colunas o `jana:ui-judge-trend` não responde "a confiança está caindo?"
 * / "quantas amostras por run?". Idempotente (Schema::hasColumn) + down().
 *
 * Mantém a filosofia append-only da tabela (ENFORCEMENT §L7): isto é DDL aditivo
 * (colunas nullable), não UPDATE de rows existentes.
 *
 * @see Modules/Jana/Ai/UiJudgeConsensus.php
 * @see app/Console/Commands/UiJudgePrCommand.php (grava)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jana_ui_judge_runs')) {
            return;
        }

        Schema::table('jana_ui_judge_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('jana_ui_judge_runs', 'confidence')) {
                $table->decimal('confidence', 4, 2)->nullable()->after('dimensoes')
                    ->comment('confiança geral 0-1 (menor concordância entre as 3 dims semânticas) · self-consistency');
            }
            if (! Schema::hasColumn('jana_ui_judge_runs', 'samples')) {
                $table->unsignedTinyInteger('samples')->nullable()->after('confidence')
                    ->comment('amostras válidas agregadas na mediana · self-consistency');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jana_ui_judge_runs')) {
            return;
        }

        Schema::table('jana_ui_judge_runs', function (Blueprint $table) {
            foreach (['confidence', 'samples'] as $col) {
                if (Schema::hasColumn('jana_ui_judge_runs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
