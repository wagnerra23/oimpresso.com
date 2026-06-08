<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0076 (Fase 4) — approval queue obrigatório pré-publish.
 *
 * Resolve gap §4.2 do mercado: Langfuse #11284 aberta há 6 meses sem prioridade.
 * Humanloop tem mas é SaaS enterprise caro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_skill_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('version_id');

            $table->unsignedBigInteger('approver_id');
            $table->enum('decision', ['approve', 'reject', 'request_changes']);
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->useCurrent();

            $table->unsignedInteger('test_runs_count')->default(0);
            $table->unsignedInteger('test_runs_pass')->default(0);

            $table->index(['version_id', 'decided_at'], 'idx_approvals_version_decided');

            $table->foreign('version_id')
                ->references('id')->on('mcp_skill_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_skill_approvals');
    }
};
