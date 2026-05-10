<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Proposta: Modules/Insights — tabela `benchmark_aggregates` (k-anonymous benchmarks).
 * Origem: memory/decisions/proposals/gap-schema-oimpresso-multi-cliente-multi-vertical.md
 *
 * ⚠️ COMPLIANCE LGPD — k-anonymity ≥5:
 *   - Toda linha em `benchmark_aggregates` representa agregação de ≥5 businesses.
 *   - CHECK constraint database-level garante invariante mesmo se BenchmarkAggregator falhar.
 *   - Felipe: validar se MariaDB versão Hostinger suporta CHECK constraint
 *     (MySQL 8.0+ enforce; MariaDB 10.2+ enforce; older parses but ignore).
 *   - Se MariaDB ignorar, fallback é trigger BEFORE INSERT/UPDATE.
 *
 * Ordem de execução: 4 de 4 (depende de `verticals`).
 *
 * Tenancy:
 *   - Tabela NÃO tem `business_id` por design — agregados são CROSS-business por natureza.
 *   - Acesso via service `BenchmarkAggregator` (não Eloquent direto) pra forçar guard.
 *   - PRs de leitura DEVEM usar `withoutGlobalScopes` com comentário SUPERADMIN.
 *
 * Backwards compat: tabela nova, zero risco.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('benchmark_aggregates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('vertical_id')->constrained('verticals')->cascadeOnDelete();
            $table->string('metric_key', 100)
                ->comment('Ex: receita_anual, ticket_medio, m2_produzidos_mes');
            $table->string('uf', 2)->nullable()->comment('NULL = agregado nacional; SP/RJ/etc = regional');
            // D1 (Felipe 2026-05-11): granularidade mensal nativa via string YYYY-MM.
            // YYYY-WW reservado pra weekly futuro sem dor (migration trivial).
            $table->string('period', 10)->comment('YYYY-MM (mensal). YYYY-WW reservado pra weekly futuro.');
            $table->unsignedInteger('n_businesses')
                ->comment('# de negócios na agregação. MUST be >= 5 (CHECK constraint).');
            $table->decimal('value_min', 18, 2)->nullable();
            // D2 (Felipe 2026-05-11): mediana + p90 cauda — padrão SaaS benchmarking
            // (Stripe, ProfitWell, ChartMogul). p25/p75 quartile descartado.
            $table->decimal('value_p50', 18, 2)->nullable()->comment('Mediana');
            $table->decimal('value_p90', 18, 2)->nullable()->comment('Top 10% (cauda)');
            $table->decimal('value_max', 18, 2)->nullable();
            $table->decimal('value_avg', 18, 2)->nullable();
            $table->timestamp('computed_at')->useCurrent();
            $table->timestamps();

            $table->index(['vertical_id', 'metric_key', 'uf', 'period'], 'idx_bench_lookup');
            $table->index('n_businesses');
        });

        // CHECK constraint k-anonymity ≥5 (LGPD compliance).
        // MySQL 8+ / MariaDB 10.2+ enforcement. Felipe deve validar versão antes do PR.
        DB::statement(
            'ALTER TABLE benchmark_aggregates ADD CONSTRAINT chk_k_anonymity CHECK (n_businesses >= 5)'
        );
    }

    public function down(): void
    {
        // Drop constraint primeiro pra evitar warning em algumas versões.
        try {
            DB::statement('ALTER TABLE benchmark_aggregates DROP CONSTRAINT chk_k_anonymity');
        } catch (\Throwable $e) {
            // Constraint pode não existir se MariaDB ignorou o CHECK na criação — silent ok.
        }
        Schema::dropIfExists('benchmark_aggregates');
    }
};
