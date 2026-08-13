<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Inbox unificado: @mention, assignment, review_requested, status_changed, commented.
 * Tool MCP my-inbox retorna unread.
 */
class CreateMcpInboxNotificationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_inbox_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id')
                ->comment('Destinatário');

            $table->enum('type', [
                'mention',
                'assigned',
                'review_requested',
                'status_changed',
                'commented',
                'due_soon',
                'blocked_resolved',
            ]);

            $table->string('task_id', 40)->nullable()
                ->comment('Ref a mcp_tasks.task_id (ou identifier)');

            $table->unsignedBigInteger('actor_id')->nullable()
                ->comment('Quem causou (NULL = system)');

            $table->text('body')->nullable();
            $table->json('payload')->nullable()
                ->comment('Contexto extra: from/to status, comment_id, etc');

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'idx_mcp_inbox_user_read');
            $table->index('task_id', 'idx_mcp_inbox_task');
            $table->index('type', 'idx_mcp_inbox_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_inbox_notifications');
    }
}
