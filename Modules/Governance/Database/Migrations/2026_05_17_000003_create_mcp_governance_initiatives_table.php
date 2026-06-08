<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 28 Agent 1 (2026-05-17) — Initiatives Governance (Cortex/Port.io-style).
 *
 * Loop "scorecard breach → auto-task com deadline + auto-close" canônico.
 * Disparado por `governance:initiative-sync` (daily 08:00 BRT) — varre últimos
 * scorecard_runs, abre Initiative quando regra cai abaixo do peso target,
 * fecha quando score_after>target, marca expired quando deadline passa.
 *
 * Cross-tenant INTENCIONAL — sem `business_id`. Pareado com demais `mcp_*`
 * tables de Governance: módulos + scorecards + initiatives são entidades
 * repo-wide (avaliam código, não dados de negócio). Wagner é dono de fato
 * via business_id=1 superadmin; outros usuários enxergam read-only via
 * Governance dashboard.
 *
 * Idempotência: composite logical key (module, rule_id, status='open')
 * garante 1 initiative aberta por par módulo+regra (Service::createFromScorecardBreach
 * checa antes de INSERT).
 *
 * @see Modules\Governance\Entities\Initiative
 * @see Modules\Governance\Services\InitiativeService
 * @see Modules\Governance\Console\Commands\ScorecardInitiativeSyncCommand
 * @see Modules/Governance/Database/Migrations/2026_05_17_000001_create_mcp_scorecard_runs_table.php
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mcp_governance_initiatives')) {
            return;
        }

        Schema::create('mcp_governance_initiatives', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('bucket', 50);
            $table->string('rule_id', 100); // ex: F1.a, V6.a, D1.b — rule_id do scorecard YAML
            $table->string('titulo');
            $table->text('descricao');
            $table->enum('status', ['open', 'in_progress', 'done', 'expired', 'cancelled'])->default('open');
            $table->date('deadline');
            $table->unsignedSmallInteger('score_before');
            $table->unsignedSmallInteger('score_target');
            $table->unsignedSmallInteger('score_after')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indices nomes ≤64 chars (MySQL hard limit — rule path-scoped migrations)
            $table->index('module', 'idx_mgi_module');
            $table->index('bucket', 'idx_mgi_bucket');
            $table->index('rule_id', 'idx_mgi_rule_id');
            $table->index('deadline', 'idx_mgi_deadline');
            $table->index(['status', 'deadline'], 'idx_mgi_status_deadline');
            // Idempotência: lookup rápido por (module, rule_id, status) — Service checa antes de INSERT
            $table->index(['module', 'rule_id', 'status'], 'idx_mgi_module_rule_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_governance_initiatives');
    }
};
