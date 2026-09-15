<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0399 (2026-09-15) Onda 4b — DROP da tabela mcp_module_grades_history.
 *
 * Último artefato da rubrica module-grade. O produtor já saiu na Onda 4a
 * (ModuleGradeService + ModuleGradeSnapshotCommand + o cron das 06:05), e o
 * vigia de frescor (checkModuleGradesSnapshotRecent) saiu junto — a tabela
 * ficou sem escritor e sem leitor.
 *
 * MEDIDO EM PRODUÇÃO ANTES DE APAGAR (2026-09-15, SSH Hostinger, SELECT):
 *   linhas ................. 960
 *   módulos distintos ...... 32
 *   dias distintos ......... 30
 *   primeiro snapshot ...... 2026-08-08 19:00:30
 *   último snapshot ........ 2026-09-14 06:05:04
 *
 * 960 = 32 × 30, exato. A janela começa em 08/08 porque foi ali que o
 * GovernanceServiceProvider ganhou loadMigrationsFrom (#5443/#5444) e as
 * migrations do módulo passaram a rodar — antes disso a tabela não existia e o
 * cron morria diariamente há ~3 meses.
 *
 * O que se perde: 30 dias de sparkline de uma rubrica aposentada. A ADR 0399
 * mediu que não havia o que a catraca travar (32/32 módulos casando com o
 * baseline, zero regressões em 10 runs de CI).
 *
 * A migration de CRIAÇÃO (2026_05_16_120000) NÃO é deletada — é história.
 *
 * Idempotente: dropIfExists. O down() recria o schema idêntico ao da criação.
 *
 * @see memory/decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md
 * @see Modules/Governance/Database/Migrations/2026_05_16_120000_create_mcp_module_grades_history_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('mcp_module_grades_history');
    }

    public function down(): void
    {
        if (Schema::hasTable('mcp_module_grades_history')) {
            return;
        }

        // Espelha 2026_05_16_120000_create_mcp_module_grades_history_table.php.
        // Cross-tenant INTENCIONAL — sem business_id, pareado com as demais
        // mcp_* de Governance (Constituição Art. 6 + Art. 8).
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
};
