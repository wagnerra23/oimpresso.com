<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot diário dos scorecards bucket-scoped (Wave 24 Agent A — 2026-05-16).
 *
 * Append-only audit trail consumido por:
 *   - `governance:scorecard-snapshot` (cron daily 07:00 BRT)
 *   - UI Governance/Scorecards (drift detection vs ontem)
 *   - Drift alerts (mcp_alertas kind='scorecard_drift')
 *
 * Cross-tenant INTENCIONAL — sem `business_id`. Pareado com demais `mcp_*`
 * tables de Governance (Constituição v2 Art. 6 + Art. 8 — observabilidade
 * cross-business). Módulos são entidades repo-wide (Modules/Vestuario existe
 * pra todos businesses; scorecard avalia código, não dados de negócio).
 *
 * @see Modules\Governance\Services\ScopedScorecardEvaluator
 * @see Modules\Governance\Console\Commands\ScorecardSnapshotCommand
 * @see memory/governance/buckets/<bucket>.yaml
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mcp_scorecard_runs')) {
            return;
        }

        Schema::create('mcp_scorecard_runs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('bucket', 50);
            $table->unsignedSmallInteger('score'); // 0-100 normalizado
            $table->json('breakdown_json');         // core + bucket_dimensions + paired_violations
            $table->date('snapshot_date');
            $table->timestamp('created_at')->useCurrent();

            // Index nomes ≤64 chars (MySQL hard limit — rule path-scoped migrations)
            $table->index(['module', 'snapshot_date'], 'idx_msr_module_date');
            $table->index(['bucket', 'snapshot_date'], 'idx_msr_bucket_date');
            $table->index('snapshot_date', 'idx_msr_snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_scorecard_runs');
    }
};
