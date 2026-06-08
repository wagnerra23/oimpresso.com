<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ARQ-0005 — confiança por (domínio × tipo), alimentada pelo Learning Loop L1/L2
class CreateMcpConfidenceScoresTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_confidence_scores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 50);
            $table->string('event_type', 80);

            $table->decimal('score', 4, 3)->default(0.500);    // inicial = 0.5 (ARQ-0005)
            $table->unsignedSmallInteger('sample_size')->default(0);
            $table->tinyInteger('hitl_level')->default(2);      // progride 2→1→0 por histórico

            $table->enum('last_outcome', [
                'success', 'fail', 'wagner_modified', 'wagner_rejected', 'cancelled',
            ])->nullable();

            // Contadores para cálculo sem precisar de JOIN com decisions
            $table->unsignedSmallInteger('consecutive_approvals')->default(0);
            $table->unsignedSmallInteger('consecutive_failures')->default(0);

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['domain', 'event_type'], 'uk_confidence_domain_type');
            $table->index('score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_confidence_scores');
    }
}
