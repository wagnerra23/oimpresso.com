<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0076 (Fase 1) — entidade canônica de skills do Claude Code em DB.
 *
 * DB primary, git destino auditável (inversão fluxo ADR 0075).
 *
 * Drift detection por-skill via git_sync_mode (auto/manual/pinned).
 * Skills criadas via UI = origin='created'; importadas do git = 'imported'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_skills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 100)->unique()
                ->comment('Nome da skill = pasta = name do frontmatter');
            $table->unsignedBigInteger('business_id')->nullable()
                ->comment('NULL = global (visível pra todo tenant); setado = só esse business');

            $table->enum('source', ['claude-code', 'plugin', 'custom'])->default('claude-code');
            $table->enum('status', ['draft', 'review', 'published', 'archived'])->default('draft');
            $table->unsignedBigInteger('current_version_id')->nullable()
                ->comment('FK pra mcp_skill_versions; NULL antes da v1');
            $table->string('module', 50)->nullable();

            // ADR 0076 — origem da skill
            $table->enum('origin', ['imported', 'created'])->default('imported')
                ->comment('imported = veio do git no seed; created = criada via UI');

            // ADR 0076 — política por-skill de como reagir a edição direta no git
            $table->enum('git_sync_mode', ['auto', 'manual', 'pinned'])->default('manual')
                ->comment('auto=aceita drift sem revisar; manual=drift alert; pinned=ignora git');

            $table->boolean('auto_publish_to_git')->default(false)
                ->comment('Approve UI + TRUE = cria PR git auto; FALSE = ação manual separada');

            $table->string('git_path', 300)->nullable()
                ->comment('NULL pra skills criadas na UI antes do primeiro publish');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'business_id'], 'idx_skills_status_biz');
            $table->index('module', 'idx_skills_module');
            $table->index('source', 'idx_skills_source');
            $table->index('git_sync_mode', 'idx_skills_sync_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_skills');
    }
};
