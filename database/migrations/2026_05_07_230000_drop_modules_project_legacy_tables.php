<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3.8 — Drop schema do Modules/Project legacy (UltimatePOS Project).
 *
 * Wagner 2026-05-07: "acho que não tem nada no project muito simples acho
 * que só o cliente mesmo." — confirmado que não há dado de valor pra
 * extrair antes do delete.
 *
 * Schema dropado:
 *   - 2 colunas em transactions (pjt_project_id, pjt_title) + FK
 *   - 7 tabelas pjt_*
 *   - 3 permissions Spatie project.*
 *   - row em system table (project_module_version)
 *
 * `down()` recria estrutura vazia (rollback de schema; dados perdidos
 * não retornam — pra recuperar, restore from backup).
 *
 * Refs: ADR 0079 Fase 3.8 · ADR 0099 (legacy discovery preservada) ·
 *        US-TR-303 · PR #197 (discovery) · SCOPE.md ProjectMgmt.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop FK + colunas extras em transactions (criadas por Project legacy)
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'pjt_project_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Drop FK primeiro (nome convencional Laravel)
                try {
                    $table->dropForeign(['pjt_project_id']);
                } catch (\Throwable $e) {
                    // FK pode não existir em ambientes sandbox; segue
                }
                $table->dropColumn(['pjt_project_id', 'pjt_title']);
            });
        }

        // 2) Drop tabelas em ordem reversa de criação (respeita FKs internos)
        $tables = [
            'pjt_invoice_lines',
            'pjt_project_time_logs',
            'pjt_project_task_comments',
            'pjt_project_task_members',
            'pjt_project_tasks',
            'pjt_project_members',
            'pjt_projects',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }

        // 3) Remove permissions Spatie project.*
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', [
                'project.create_project',
                'project.edit_project',
                'project.delete_project',
            ])->delete();
        }

        // 4) Remove version row do system table
        if (Schema::hasTable('system')) {
            DB::table('system')->where('key', 'project_module_version')->delete();
        }
    }

    public function down(): void
    {
        // Rollback: recria estrutura vazia (NÃO restaura dados — restore de backup pra isso).

        if (! Schema::hasTable('pjt_projects')) {
            Schema::create('pjt_projects', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id');
                $table->string('name');
                $table->string('project_id')->nullable();
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->default('not_started');
                $table->json('settings')->nullable();
                $table->unsignedInteger('lead_id')->nullable();
                $table->unsignedInteger('contact_id')->nullable();
                $table->unsignedInteger('created_by');
                $table->timestamps();
            });
        }

        foreach ([
            'pjt_project_members',
            'pjt_project_tasks',
            'pjt_project_task_members',
            'pjt_project_task_comments',
            'pjt_project_time_logs',
            'pjt_invoice_lines',
        ] as $t) {
            if (! Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->increments('id');
                    $table->timestamps();
                });
            }
        }

        // Restore colunas transactions
        if (Schema::hasTable('transactions') && ! Schema::hasColumn('transactions', 'pjt_project_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedInteger('pjt_project_id')->nullable();
                $table->string('pjt_title')->nullable();
            });
        }

        // Restore permissions (reativa visibilidade do módulo se reinstalado)
        if (Schema::hasTable('permissions')) {
            foreach (['project.create_project', 'project.edit_project', 'project.delete_project'] as $perm) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $perm, 'guard_name' => 'web'],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
};
