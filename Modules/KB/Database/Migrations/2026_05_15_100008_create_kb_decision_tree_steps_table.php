<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_decision_tree_steps — perguntas com branches yes/no.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §7
 *
 * Invariante por linha (enforce em KbDecisionTreeStepObserver):
 *   - exatamente UM de (yes_next_step_id, yes_fix) populado
 *   - exatamente UM de (no_next_step_id, no_fix) populado
 *
 * FK auto-referencial (yes_next_step_id, no_next_step_id) → SET NULL pra
 * permitir delete de step intermediário sem cascade massivo.
 *
 * Após criar tabela, alteramos kb_decision_trees.root_step_id pra FK kb_decision_tree_steps.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_decision_tree_steps')) {
            return;
        }

        Schema::create('kb_decision_tree_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('tree_id');
            $table->unsignedSmallInteger('position');
            $table->string('question', 500);

            // Branch SIM.
            $table->unsignedBigInteger('yes_next_step_id')->nullable();
            $table->text('yes_fix')->nullable()
                ->comment('pode citar #kb-NNN pra cross-link');
            $table->unsignedBigInteger('yes_fix_node_id')->nullable()
                ->comment('edge fix-of-decision opcional');

            // Branch NÃO.
            $table->unsignedBigInteger('no_next_step_id')->nullable();
            $table->text('no_fix')->nullable();
            $table->unsignedBigInteger('no_fix_node_id')->nullable();

            $table->timestamps();

            $table->unique(['tree_id', 'position'], 'uq_kb_dts_tree_pos');
            $table->index(['business_id', 'tree_id'], 'idx_kb_dts_biz_tree');

            $table->foreign('business_id', 'fk_kb_dts_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('tree_id', 'fk_kb_dts_tree')
                ->references('id')->on('kb_decision_trees')->onDelete('cascade');

            $table->foreign('yes_next_step_id', 'fk_kb_dts_yes_next')
                ->references('id')->on('kb_decision_tree_steps')->onDelete('set null');

            $table->foreign('no_next_step_id', 'fk_kb_dts_no_next')
                ->references('id')->on('kb_decision_tree_steps')->onDelete('set null');

            $table->foreign('yes_fix_node_id', 'fk_kb_dts_yes_fix_node')
                ->references('id')->on('kb_nodes')->onDelete('set null');

            $table->foreign('no_fix_node_id', 'fk_kb_dts_no_fix_node')
                ->references('id')->on('kb_nodes')->onDelete('set null');
        });

        // FK delayed: root_step_id em kb_decision_trees vira FK pra kb_decision_tree_steps.
        if (Schema::hasTable('kb_decision_trees') && Schema::hasColumn('kb_decision_trees', 'root_step_id')) {
            try {
                \DB::statement(
                    'ALTER TABLE kb_decision_trees '.
                    'ADD CONSTRAINT fk_kb_dt_root_step '.
                    'FOREIGN KEY (root_step_id) REFERENCES kb_decision_tree_steps(id) '.
                    'ON DELETE SET NULL'
                );
            } catch (\Throwable $e) {
                // Re-run idempotente — FK já existia.
            }
        }
    }

    public function down(): void
    {
        // Remove FK delayed primeiro (senão drop falha).
        if (Schema::hasTable('kb_decision_trees')) {
            try {
                \DB::statement('ALTER TABLE kb_decision_trees DROP FOREIGN KEY fk_kb_dt_root_step');
            } catch (\Throwable $e) {
                // FK pode já não existir.
            }
        }

        Schema::dropIfExists('kb_decision_tree_steps');
    }
};
