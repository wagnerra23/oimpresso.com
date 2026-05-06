<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Bidirectional git sync: commit/PR/branch ↔ task.
 * Webhook GitHub detecta refs:
 *   - "refs <KEY>-<N>" em commit msg → action=refs (apenas link)
 *   - "fixes/closes/resolves <KEY>-<N>" → action=fixes (status auto pra review/done)
 *   - branch "<KEY>-<N>-slug" → action=branch
 *   - PR aberto → action=pr_opened
 *   - PR merged em main/default → action=pr_merged → status=done
 */
class CreateMcpGitLinksTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_git_links', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40);

            $table->enum('action', [
                'refs',
                'fixes',
                'branch',
                'pr_opened',
                'pr_reviewed',
                'pr_merged',
                'pr_closed',
            ]);

            $table->string('repo_full_name', 120)->nullable()
                ->comment('Ex: wagnerra23/oimpresso.com');

            $table->string('commit_sha', 40)->nullable();
            $table->unsignedInteger('pr_number')->nullable();
            $table->string('branch', 200)->nullable();

            $table->string('author_username', 60)->nullable();
            $table->text('message')->nullable();

            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index('task_id', 'idx_mcp_git_links_task');
            $table->index('commit_sha', 'idx_mcp_git_links_commit');
            $table->index(['repo_full_name', 'pr_number'], 'idx_mcp_git_links_pr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_git_links');
    }
}
