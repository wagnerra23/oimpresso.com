<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0076 (Fase 1) — versões append-only de skills.
 *
 * Cada save em UI cria nova version. Append-only — nunca update body.
 * Origin distingue: ui (humano editou) vs git_drift (webhook detectou)
 * vs git_seed (import inicial).
 *
 * Rationale 4 campos (Anthropic-style) obrigatórios em versões origin=ui:
 * problema observado / hipótese / métrica de sucesso / plano de rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_skill_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('skill_id');
            $table->unsignedInteger('version')
                ->comment('Auto-increment por skill (v1, v2, ...)');

            $table->mediumText('body_markdown');
            $table->json('frontmatter_json');

            // Rationale 4 campos (gap §4.3 do mercado — pesquisa cofre prompt mgmt)
            $table->text('rationale_problem')->nullable();
            $table->text('rationale_hypothesis')->nullable();
            $table->text('rationale_success_metric')->nullable();
            $table->text('rationale_rollback')->nullable();

            $table->enum('origin', ['ui', 'git_drift', 'git_seed'])->default('ui')
                ->comment('ui=editor humano; git_drift=webhook detectou; git_seed=import inicial');
            $table->enum('status', ['draft', 'review', 'published', 'drift_pending', 'archived'])->default('draft');

            $table->string('git_sha', 40)->nullable();
            $table->unsignedInteger('pr_number')->nullable()
                ->comment('Número do PR criado pelo Publish-to-git (NULL pra versões só em DB)');
            $table->timestamp('published_to_git_at')->nullable();

            $table->unsignedSmallInteger('pii_redactions_count')->default(0);

            $table->unsignedBigInteger('created_by')->nullable()
                ->comment('NULL pra versões origin=git_drift criadas pelo webhook');
            $table->timestamps();

            $table->unique(['skill_id', 'version'], 'uk_skill_version');
            $table->index(['skill_id', 'status'], 'idx_versions_skill_status');
            $table->index('origin', 'idx_versions_origin');

            $table->foreign('skill_id')
                ->references('id')->on('mcp_skills')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_skill_versions');
    }
};
