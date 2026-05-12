<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-024 — Coluna boolean `is_grouped_invoice` em transactions.
 *
 * Sinal: Delphi infere "venda agrupada" do texto "ATIVO CRIADO" no campo
 * Status (confuso). CODFINANCEIRO_GRUPO usado em 43-65% das linhas de todos
 * os 4 clientes legacy (WR2 34.5% / Vargas 65.1% / Extreme 43.3% / Gold 53.1%
 * — heatmap-2026-05-11). Fazer certo: coluna explícita boolean default false
 * + badge "×N" na lista quando true.
 *
 * Default false preserva todas as vendas legadas. Backfill é manual quando
 * cliente OfficeImpresso for migrado (importer Python python pode setar true
 * em transactions cuja CODFINANCEIRO_GRUPO original tinha múltiplas vendas).
 *
 * Index composto (business_id, is_grouped_invoice) — multi-tenant Tier 0
 * (ADR 0093) + filtro futuro "Só agrupadas" rápido.
 *
 * Refs: ADR 0093 (multi-tenant Tier 0), SPEC US-SELL-024,
 *       memory/research/2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }
        if (Schema::hasColumn('transactions', 'is_grouped_invoice')) {
            return; // idempotente
        }

        Schema::table('transactions', function (Blueprint $t) {
            $t->boolean('is_grouped_invoice')->default(false)->after('current_stage_id');
            $t->index(['business_id', 'is_grouped_invoice'], 'transactions_biz_grouped_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }
        if (! Schema::hasColumn('transactions', 'is_grouped_invoice')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $t) {
            $t->dropIndex('transactions_biz_grouped_idx');
            $t->dropColumn('is_grouped_invoice');
        });
    }
};
