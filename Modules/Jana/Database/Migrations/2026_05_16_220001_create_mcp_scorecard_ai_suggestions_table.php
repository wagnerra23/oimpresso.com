<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 24 Agent B — Tabela baseline AI-driven scorecard suggestions (READ-ONLY 30 dias).
 *
 * Persiste sugestões do AiScorecardJudge (LLM-as-judge) sobre rubricas
 * Scoped Scorecards (ADR 0160). Score OFICIAL permanece determinístico
 * (ScopedScorecardEvaluator W21/W24-A). Esta tabela é OBSERVACIONAL —
 * anti-Goodhart Law (Jellyfish 2025).
 *
 * Tabela repo-wide (governance meta) — sem business_id scope.
 * Acessível APENAS via Admin Center Wagner-only (IsWagner middleware).
 *
 * Retention: append-only; agregação 30d via window query no Dashboard.
 *
 * @see Modules/Jana/Services/Scorecard/AiScorecardJudge.php
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_scorecard_ai_suggestions')) {
            return; // idempotente
        }

        Schema::create('mcp_scorecard_ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->unsignedSmallInteger('deterministic_score'); // 0..100
            $table->smallInteger('ai_suggested_delta'); // -10..+10 (signed)
            $table->text('ai_justificativa');
            $table->string('ai_model', 50);
            $table->decimal('confidence', 3, 2)->nullable(); // 0.00..1.00
            $table->timestamp('created_at')->useCurrent();

            // Index pra ranking + sparkline 30d
            $table->index(['module', 'created_at'], 'idx_module_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_scorecard_ai_suggestions');
    }
};
