<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0192 follow-up — Reverse hook JobSheetObserver (OS reaberta cancela venda derivada).
 *
 * Schema aditivo minimal · Caminho B' (não-destrutivo, sem mexer no ENUM `status`):
 *
 *   cancelled_at  TIMESTAMP  NULL  AFTER transaction_date
 *
 * Comportamento:
 *  - NULL  (default) → Transaction ativa, conta em KPIs e listagens
 *  - SET   (timestamp) → Transaction cancelada, preserva audit trail (row + Spatie activity log)
 *
 * Por que NÃO SoftDeletes:
 *  - `Transaction` UPOS legacy NÃO usa trait `SoftDeletes` · adicionar exigiria
 *    rever ~50 controllers/queries pra ignorar `deleted_at` (risco grande de bug
 *    sutil em multi-tenant Tier 0 ADR 0093)
 *  - `forceDelete` aniquila history (não queremos)
 *
 * Por que NÃO `status='cancelled'`:
 *  - Coluna `status` é ENUM rígido `['received','pending','ordered','draft','final']`
 *    (migration 2017_08_19_054827) · adicionar `'cancelled'` exige ALTER TABLE
 *    schema change em produção · zero benefício vs `cancelled_at` nullable
 *
 * Idempotência preservada (ADR 0192):
 *  - JobSheetObserver consulta `whereNull('cancelled_at')` no check exists()
 *  - Re-completion (OS terminal → aberto → terminal AGAIN) cria NOVA Transaction
 *  - History de cancelamentos prévios fica visível em queries `whereNotNull('cancelled_at')`
 *
 * Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL preservado:
 *  - Coluna não-scoped (timestamp puro) · scope continua via `business_id` global
 *  - Reverse hook do Observer filtra `where('business_id', $jobSheet->business_id)`
 *
 * IDEMPOTENTE — `Schema::hasColumn` check protege re-execução.
 * down() reverte sem perda de dados (drop column).
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('transaction_date')
                    ->comment('Marcador de cancelamento · NULL = ativa · timestamp = cancelada (preserva row + audit) · ADR 0192 reverse hook');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
