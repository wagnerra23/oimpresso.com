<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TaskRegistry Fase 0 (ADR TaskRegistry/0001).
 *
 * Cache governado das US-* extraídas dos SPECs canônicos em memory/requisitos/.
 * Source-of-truth = git; esta tabela é cache via parser idempotente.
 *
 * Padrão idêntico a mcp_memory_documents — webhook GitHub dispara sync após push.
 */
class CreateMcpTasksTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40)->unique()
                ->comment('Identificador canônico, ex: US-NFSE-001');

            $table->string('module', 60)
                ->comment('Módulo do source (NFSe, Copiloto, Financeiro...)');

            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->enum('status', ['todo', 'doing', 'review', 'done', 'blocked', 'cancelled'])
                ->default('todo');

            $table->string('owner', 60)->nullable()
                ->comment('Username dev ou null se não atribuído');

            $table->string('sprint', 40)->nullable()
                ->comment('Sprint A/B/C/D ou semana ISO 2026-W18');

            $table->enum('priority', ['p0', 'p1', 'p2', 'p3'])
                ->default('p2')
                ->nullable();

            $table->decimal('estimate_h', 5, 1)->nullable()
                ->comment('Estimativa em horas');

            $table->json('blocked_by')->nullable()
                ->comment('Array de task_ids que bloqueiam esta');

            $table->string('source_path', 500)
                ->comment('Path relativo do SPEC, ex: memory/requisitos/NFSe/SPEC.md#US-NFSE-001');

            $table->string('source_git_sha', 40)->nullable()
                ->comment('Commit SHA do último parse');

            $table->timestamp('parsed_at')
                ->comment('Quando foi parseado pela última vez');

            $table->timestamps();

            $table->index(['module', 'status'], 'idx_mcp_tasks_module_status');
            $table->index(['owner', 'status'], 'idx_mcp_tasks_owner_status');
            $table->index('sprint', 'idx_mcp_tasks_sprint');
            $table->index('priority', 'idx_mcp_tasks_priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tasks');
    }
}
