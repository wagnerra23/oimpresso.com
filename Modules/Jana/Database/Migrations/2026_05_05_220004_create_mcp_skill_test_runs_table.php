<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0076 (Fase 3) — testes inline contra inputs reais multi-tenant.
 *
 * Resolve gap §4.4 do mercado (pesquisa cofre prompt mgmt 2026-05):
 * NENHUMA das 10 ferramentas faz "rodar skill nova contra últimos 50
 * inputs reais do business_id=4 com PII redactor". Aqui faz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_skill_test_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('version_id');

            $table->enum('input_source', ['manual', 'real_conversations', 'fixture'])->default('manual');
            $table->json('input_json')
                ->comment('Input enviado: prompt + contexto. PII redacted antes de gravar');
            $table->mediumText('output')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->unsignedBigInteger('business_id_scope')->nullable()
                ->comment('Se input_source=real_conversations, qual business_id foi usado');
            $table->unsignedSmallInteger('pii_redactions_count')->default(0);

            $table->boolean('passed')->nullable()
                ->comment('Manual pelo dev: clicou approve no resultado? null=não avaliado');
            $table->text('pass_reason')->nullable();

            $table->unsignedBigInteger('executed_by')->nullable();
            $table->timestamp('executed_at')->useCurrent();

            $table->index(['version_id', 'executed_at'], 'idx_test_runs_version');

            $table->foreign('version_id')
                ->references('id')->on('mcp_skill_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_skill_test_runs');
    }
};
