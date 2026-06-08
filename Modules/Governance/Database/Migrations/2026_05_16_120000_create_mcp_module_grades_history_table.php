<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot diário das notas Module Grades (rubrica v3 — ADR 0155).
 *
 * Cross-tenant INTENCIONAL — sem `business_id`. Pareado com demais `mcp_*`
 * tables de Governance (Art. 6 + Art. 8 da Constituição v2 — observabilidade
 * cross-business). Módulos são entidades repo-wide (Modules/Crm existe pra
 * todos businesses).
 *
 * Alimentado por `php artisan module:grade-snapshot` (cron daily 06:00 BRT
 * pareado com `jana:health-check`). Consumido por Show.tsx (sparkline 7d).
 *
 * @see Modules\Governance\Console\Commands\ModuleGradeSnapshotCommand
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mcp_module_grades_history')) {
            return;
        }

        Schema::create('mcp_module_grades_history', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80);
            $table->unsignedTinyInteger('score'); // 0-100 (normalizado v3)
            $table->string('bucket', 20);
            $table->json('dimensions')->nullable(); // breakdown 9 dimensões v3
            $table->timestamp('snapshot_at');

            $table->index(['module', 'snapshot_at'], 'idx_mgh_module_snapshot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_module_grades_history');
    }
};
