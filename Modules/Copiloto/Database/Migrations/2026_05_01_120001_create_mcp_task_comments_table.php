<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TaskRegistry Fase 1 (US-TR-006) — comentários por task.
 *
 * DB-only: comentários NÃO são escritos de volta ao SPEC.
 * Timeline completa visível via tasks-detail ou futura UI Kanban.
 */
class CreateMcpTaskCommentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_task_comments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40)
                ->comment('US-NNN-MMM — ref à mcp_tasks.task_id (sem FK pra tolerância)');

            $table->string('author', 60)
                ->comment('Username de quem comentou (ex: wagner, eliana)');

            $table->text('body')
                ->comment('Texto do comentário em markdown');

            $table->timestamps();

            $table->index('task_id', 'idx_mcp_task_comments_task_id');
            $table->index('author', 'idx_mcp_task_comments_author');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_task_comments');
    }
}
