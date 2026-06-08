<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ARQ-0009 — Decision Memory: log append-only de toda decisão do ADS
class CreateMcpDualBrainDecisionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_dual_brain_decisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');

            // Evento de entrada
            $table->string('event_type', 80);
            $table->enum('event_source', ['brain_a', 'evolution_agent', 'wagner', 'scheduler']);
            $table->string('domain', 50);
            $table->json('files_affected')->nullable();
            $table->json('event_metadata')->nullable();

            // Roteamento
            $table->decimal('risk_score', 4, 3);
            $table->decimal('confidence_score', 4, 3);
            $table->string('policy_applied', 50)->nullable();  // 'ALLOW_BRAIN_A', 'BLOCK_ALWAYS'…
            $table->enum('destination', [
                'brain_a', 'brain_b', 'pending_wagner', 'blocked', 'queued',
            ]);
            $table->tinyInteger('hitl_level')->default(2);

            // Execução
            $table->enum('brain_used', ['brain_a', 'brain_b', 'human', 'none']);
            $table->string('model_used', 50)->nullable();
            $table->text('instruction_generated')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->decimal('cost_usd', 8, 6)->nullable();
            $table->unsignedInteger('execution_ms')->nullable();

            // Outcome
            $table->enum('outcome', [
                'success', 'fail', 'wagner_modified', 'wagner_rejected', 'cancelled', 'expired',
            ])->default('cancelled');
            $table->text('wagner_modified_to')->nullable();
            $table->tinyInteger('diff_size_pct')->nullable();
            $table->string('pr_url', 255)->nullable();
            $table->char('commit_sha', 40)->nullable();

            // Conflito (ARQ-0010)
            $table->string('conflict_type', 80)->nullable();

            // Auditoria
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 50)->nullable();

            $table->index(['domain', 'event_type'], 'idx_dbd_domain_type');
            $table->index('outcome', 'idx_dbd_outcome');
            $table->index(['business_id', 'created_at'], 'idx_dbd_biz_created');
            $table->index('conflict_type', 'idx_dbd_conflict');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_dual_brain_decisions');
    }
}
