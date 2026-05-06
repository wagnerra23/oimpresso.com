<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Estende mcp_tasks com:
 *   - identifier humano (Linear-style: COPI-123) — único, populado por TaskCrudService
 *   - project_id, epic_id, cycle_id, component_id, parent_task_id (subtask)
 *   - type ENUM(story|task|bug|spike|chore|epic-stub)
 *   - story_points + estimate_unit + estimate_value (flexível)
 *   - due_date, started_at, completed_at
 *   - labels JSON (free-form)
 *   - custom_fields JSON (validável por mcp_jira_projects.custom_field_schema)
 *
 * Mantém status enum compatível (adiciona "backlog" como state inicial).
 */
class ExtendMcpTasksForJiraStyle extends Migration
{
    public function up(): void
    {
        // Status: precisa adicionar "backlog" no enum sem quebrar dados existentes.
        // MySQL 8 — ALTER TABLE MODIFY funciona porque "backlog" só amplia o enum.
        DB::statement("ALTER TABLE mcp_tasks MODIFY status ENUM('backlog','todo','doing','review','done','blocked','cancelled') NOT NULL DEFAULT 'todo'");

        Schema::table('mcp_tasks', function (Blueprint $table) {
            $table->string('identifier', 24)->nullable()->after('task_id')
                ->comment('Linear-style: <PROJECT_KEY>-<NNNN>, ex: COPI-123');

            $table->unsignedBigInteger('project_id')->nullable()->after('identifier')
                ->comment('FK lógico mcp_jira_projects.id');

            $table->unsignedBigInteger('epic_id')->nullable()->after('project_id');
            $table->unsignedBigInteger('cycle_id')->nullable()->after('epic_id');
            $table->unsignedBigInteger('component_id')->nullable()->after('cycle_id');

            $table->unsignedBigInteger('parent_task_id')->nullable()->after('component_id')
                ->comment('Para subtasks — FK lógico mcp_tasks.id');

            $table->enum('type', ['story', 'task', 'bug', 'spike', 'chore', 'epic-stub'])
                ->default('story')
                ->after('parent_task_id');

            $table->decimal('story_points', 5, 1)->nullable()->after('estimate_h');

            $table->enum('estimate_unit', ['points', 'hours', 'days', 'tshirt', 'fibonacci'])
                ->default('points')
                ->after('story_points');

            $table->decimal('estimate_value', 8, 2)->nullable()->after('estimate_unit')
                ->comment('Valor numérico ou index (tshirt: 1=XS,2=S,3=M,4=L,5=XL)');

            $table->timestamp('due_date')->nullable()->after('estimate_value');
            $table->timestamp('started_at')->nullable()->after('due_date');
            $table->timestamp('completed_at')->nullable()->after('started_at');

            $table->json('labels')->nullable()->after('completed_at')
                ->comment('Array de strings: ["lgpd","perf","tier-a"]');

            $table->json('custom_fields')->nullable()->after('labels')
                ->comment('Schema validável via mcp_jira_projects.custom_field_schema');

            $table->unique('identifier', 'uq_mcp_tasks_identifier');
            $table->index(['project_id', 'cycle_id', 'status'], 'idx_mcp_tasks_proj_cycle_status');
            $table->index('epic_id', 'idx_mcp_tasks_epic');
            $table->index('component_id', 'idx_mcp_tasks_component');
            $table->index('parent_task_id', 'idx_mcp_tasks_parent');
            $table->index('due_date', 'idx_mcp_tasks_due');
            $table->index('completed_at', 'idx_mcp_tasks_completed');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_tasks', function (Blueprint $table) {
            $table->dropUnique('uq_mcp_tasks_identifier');
            $table->dropIndex('idx_mcp_tasks_proj_cycle_status');
            $table->dropIndex('idx_mcp_tasks_epic');
            $table->dropIndex('idx_mcp_tasks_component');
            $table->dropIndex('idx_mcp_tasks_parent');
            $table->dropIndex('idx_mcp_tasks_due');
            $table->dropIndex('idx_mcp_tasks_completed');

            $table->dropColumn([
                'identifier',
                'project_id',
                'epic_id',
                'cycle_id',
                'component_id',
                'parent_task_id',
                'type',
                'story_points',
                'estimate_unit',
                'estimate_value',
                'due_date',
                'started_at',
                'completed_at',
                'labels',
                'custom_fields',
            ]);
        });

        DB::statement("ALTER TABLE mcp_tasks MODIFY status ENUM('todo','doing','review','done','blocked','cancelled') NOT NULL DEFAULT 'todo'");
    }
}
