<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * jana_ui_judge_runs — medição do PR UI Judge (Onda 4.1 · ADR UI-0013).
 *
 * Cada julgamento do PrUiJudgeAgent vira 1 INSERT aqui (append-only audit ·
 * mesma filosofia de mcp_automation_runs / ENFORCEMENT.md §L7: nunca UPDATE/
 * DELETE em rows existentes). Sem isso o juiz era fire-and-forget — postava o
 * comentário no PR e o score evaporava, impossível medir trend/custo/acerto.
 *
 * NÃO É DADO MULTI-TENANT (Tier 0): o juiz roda a nível de repositório/CI, não
 * por business. Logo NÃO tem business_id nem HasBusinessScope — é artefato de
 * tooling de engenharia, não dado de cliente. (multi-tenant-patterns: caso
 * system/CLI tratado explicitamente.)
 *
 * Retenção: via jana:retention-purge se o volume crescer (~100 PRs/mês = trivial).
 * Idempotente (Schema::hasTable guard) + down().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jana_ui_judge_runs')) {
            return;
        }

        Schema::create('jana_ui_judge_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('pr_number');
            $table->string('repo', 140)->nullable();
            $table->string('provider', 40);
            $table->string('model', 60);
            $table->unsignedSmallInteger('score');
            $table->enum('verdict', ['approve', 'request_changes', 'comment']);
            $table->unsignedSmallInteger('violacoes_count')->default(0);
            $table->json('dimensoes')->nullable()
                ->comment('score 0-10 por dimensão (9 dimensões da Constituição UI v2)');
            $table->decimal('custo_usd_estimado', 8, 4)->nullable()
                ->comment('estimativa por modelo · não é billing real');
            $table->timestamp('judged_at');
            $table->timestamps();

            $table->index('judged_at', 'idx_ui_judge_judged_at');
            $table->index('pr_number', 'idx_ui_judge_pr');
            $table->index('verdict', 'idx_ui_judge_verdict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jana_ui_judge_runs');
    }
};
