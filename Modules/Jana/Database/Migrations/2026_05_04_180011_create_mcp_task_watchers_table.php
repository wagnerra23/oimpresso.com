<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Watcher = user que recebe notificações na inbox quando a task muda.
 * Diferente de owner/assignee — pode ser stakeholder não-executor.
 */
class CreateMcpTaskWatchersTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_task_watchers', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40);
            $table->unsignedBigInteger('user_id');

            $table->timestamps();

            $table->unique(['task_id', 'user_id'], 'uq_mcp_task_watchers_task_user');
            $table->index('user_id', 'idx_mcp_task_watchers_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_task_watchers');
    }
}
