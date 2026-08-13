<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Anexos de task: print, log, dump, planilha. SHA256 garante dedup.
 */
class CreateMcpTaskAttachmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_task_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40);

            $table->string('filename', 255);
            $table->string('file_url', 500);
            $table->string('sha256', 64)->index('idx_mcp_task_attach_sha256');
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('task_id', 'idx_mcp_task_attach_task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_task_attachments');
    }
}
