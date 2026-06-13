<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico diário do scorecard SDD (GT-G7 — ADR 0275 §1): 1 row/dia com payload
 * JSON do scorecard + composta v1. Cross-tenant INTENCIONAL — sem `business_id`,
 * precedente `mcp_briefs` + `mcp_module_grades_history` (Constituição v2 Art. 6 —
 * métrica do projeto, não do tenant). Alimentado por `governance:sdd-scorecard-snapshot`
 * (daily 07:00 BRT); consumido pelo card SDD em /governance e pelo brief (GT-G8).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mcp_sdd_scorecard_history')) {
            return;
        }

        Schema::create('mcp_sdd_scorecard_history', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->json('payload');                       // scorecard completo + resumo (vivas, alerts, k)
            $table->decimal('composta', 5, 1)->nullable(); // v1 — null enquanto 0 métricas armadas (ADR 0275 §4)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_sdd_scorecard_history');
    }
};
