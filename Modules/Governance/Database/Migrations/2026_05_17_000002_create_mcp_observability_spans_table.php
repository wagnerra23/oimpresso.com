<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de observability OTel pro pipeline ScopedScorecardEvaluator
 * (Wave 26 Agent 3 — 2026-05-17, ADR 0162).
 *
 * Duas tabelas append-only:
 *
 * 1. `mcp_observability_spans` — raw spans recebidos via OTel Collector
 *    (exporter → mysql-write). Schema espelha `OtelHelper::spanBiz()` attributes
 *    + business_id Tier 0 obrigatório.
 *
 * 2. `mcp_observability_aggregates_daily` — rollup pré-computado (cron daily 02:00
 *    BRT via `observability:aggregate-daily`). Consultado por
 *    `ScopedScorecardEvaluator::detectOtelQuery()` D9.b — devolve p99 sem
 *    `PERCENTILE_CONT` (MySQL <8.0 não suporta).
 *
 * Cross-tenant INTENCIONAL no aggregates: pareado com demais `mcp_*` tables
 * Governance (Constituição v2 Art. 6 + Art. 8 — observabilidade cross-business).
 * Spans crus preservam `business_id` por compliance Tier 0 e queries scoped futuras.
 *
 * Idempotência: ambas tabelas guardadas por `Schema::hasTable()` — re-run sobrevive.
 *
 * @see Modules\Governance\Services\ObservabilitySnapshotService
 * @see Modules\Governance\Console\Commands\ObservabilityAggregateCommand
 * @see Modules\Governance\Services\ScopedScorecardEvaluator::detectOtelQuery
 * @see memory/decisions/0162-otel-collector-prod-observability.md
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mcp_observability_spans')) {
            Schema::create('mcp_observability_spans', function (Blueprint $table) {
                $table->id();
                // Multi-tenant Tier 0 ADR 0093 — nullable APENAS pra spans system-level
                // sem context (cron, console). Toda chamada via OtelHelper::spanBiz()
                // resolve session()->user.business_id ou retorna 0.
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->string('module', 100)->index();
                $table->string('span_name', 200);
                $table->unsignedInteger('duration_ms');
                // status: ok | error | timeout (string em vez de enum pra flexibilidade futura)
                $table->string('status', 20)->default('ok');
                // attributes scalars only — Tier 0 PII proibida (CPF/CNPJ/email/telefone)
                $table->json('attributes_json')->nullable();
                $table->timestamp('timestamp')->index();
                $table->timestamp('created_at')->useCurrent();

                // Indexes compostos (nomes ≤64 chars — MySQL hard limit)
                $table->index(['module', 'span_name', 'timestamp'], 'idx_mos_mod_span_time');
                $table->index(['business_id', 'module', 'timestamp'], 'idx_mos_biz_mod_time');
            });
        }

        if (! Schema::hasTable('mcp_observability_aggregates_daily')) {
            Schema::create('mcp_observability_aggregates_daily', function (Blueprint $table) {
                $table->id();
                $table->string('module', 100)->index();
                $table->string('span_name', 200);
                $table->date('snapshot_date')->index();
                $table->unsignedInteger('count_total');
                $table->unsignedInteger('count_error');
                $table->unsignedInteger('p50_ms');
                $table->unsignedInteger('p95_ms');
                $table->unsignedInteger('p99_ms');
                $table->timestamp('created_at')->useCurrent();

                // 1 row por (module, span, date) — upsert idempotente
                $table->unique(['module', 'span_name', 'snapshot_date'], 'uq_moad_mod_span_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_observability_aggregates_daily');
        Schema::dropIfExists('mcp_observability_spans');
    }
};
