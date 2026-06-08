<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// T11+T18+T9 — colunas que destravam learning loop completo
class AddLearningLoopColumns extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_dual_brain_decisions', function (Blueprint $table) {
            // T9 PlannerAgent: subtarefas
            $table->unsignedBigInteger('parent_decision_id')->nullable()->after('id');
            $table->index('parent_decision_id', 'idx_dbd_parent');

            // T18 Retry inteligente
            $table->unsignedTinyInteger('attempts')->default(0)->after('outcome');
            $table->timestamp('next_retry_at')->nullable()->after('attempts');
            $table->index('next_retry_at', 'idx_dbd_next_retry');

            // T11 ReviewerAgent
            $table->unsignedTinyInteger('review_score')->nullable()->after('attempts');     // 0-100
            $table->json('review_breakdown')->nullable()->after('review_score');             // {correctness, safety, quality, cost}
            $table->decimal('review_confidence', 4, 3)->nullable()->after('review_breakdown'); // 0.000-1.000
            $table->index('review_score', 'idx_dbd_review');

            // T7 Auto Task Generator
            $table->boolean('auto_generated')->default(false)->after('event_source');
            $table->index('auto_generated', 'idx_dbd_auto_gen');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_dual_brain_decisions', function (Blueprint $table) {
            $table->dropIndex('idx_dbd_parent');
            $table->dropIndex('idx_dbd_next_retry');
            $table->dropIndex('idx_dbd_review');
            $table->dropIndex('idx_dbd_auto_gen');
            $table->dropColumn([
                'parent_decision_id', 'attempts', 'next_retry_at',
                'review_score', 'review_breakdown', 'review_confidence',
                'auto_generated',
            ]);
        });
    }
}
