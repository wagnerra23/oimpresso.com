<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0192 — Auto-faturar OS → Venda via JobSheetObserver.
 *
 * Schema aditivo cross-source pra Integração Vendas × Oficina (A1 KB-9.75):
 *
 *   source           ENUM('balcao','oficina','online')  DEFAULT 'balcao'  AFTER type
 *   os_ref           VARCHAR(20)                        NULL              AFTER source
 *   commission_split JSON                               NULL              AFTER os_ref
 *
 *   INDEX idx_transactions_source (business_id, source, transaction_date)
 *
 * Default 'balcao' retroativo zero breaking change — vendas legacy aparecem
 * automaticamente como "Balcão" na coluna Origem (frontend Onda 3).
 *
 * `os_ref` formato "OS-{job_sheet_id}" preenchido pelo JobSheetObserver
 * (Onda 2) quando stage transiciona pra `entregue_completo` (FSM canonical
 * ADR 0143). Idempotência: chave de busca composta `(business_id, os_ref)`.
 *
 * `commission_split` JSON shape canon:
 *   { "mecanico_id": int, "mecanico_pct": float, "balcao_id": int|null, "balcao_pct": float }
 * Total mecanico_pct + balcao_pct === 100. Balcão NULL quando 100% mecânico.
 *
 * Índice composto (business_id, source, transaction_date) explora KPI hero
 * breakdown query do Sells/Index (Onda 4) — agrupamento por source dentro
 * do business + filtro temporal "hoje/semana/mês".
 *
 * Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL preservado: business_id PRIMEIRO
 * em todos índices · todas queries continuam scoped por business global scope.
 *
 * IDEMPOTENTE — Schema::hasColumn + SHOW INDEXES checks protegem re-execução.
 * down() reverte sem perda de dados (drop columns + drop index).
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'source')) {
                $table->enum('source', ['balcao', 'oficina', 'online'])
                    ->default('balcao')
                    ->nullable()
                    ->after('type')
                    ->comment('Origem da venda · A1 KB-9.75 · ADR 0192');
            }

            if (! Schema::hasColumn('transactions', 'os_ref')) {
                $table->string('os_ref', 20)
                    ->nullable()
                    ->after('source')
                    ->comment('Referência cross-módulo OS-NNNN quando source=oficina · ADR 0192');
            }

            if (! Schema::hasColumn('transactions', 'commission_split')) {
                $table->json('commission_split')
                    ->nullable()
                    ->after('os_ref')
                    ->comment('Split { mecanico_id, mecanico_pct, balcao_id, balcao_pct } total=100 · ADR 0192');
            }
        });

        // Índice composto Tier 0 multi-tenant (ADR 0093 IRREVOGÁVEL).
        // business_id PRIMEIRO · source pra agrupamento KPI · transaction_date pra filtro temporal.
        Schema::table('transactions', function (Blueprint $table) {
            $existing = collect(DB::select('SHOW INDEXES FROM transactions'))
                ->pluck('Key_name')
                ->toArray();

            if (! in_array('idx_transactions_source', $existing, true)) {
                $table->index(
                    ['business_id', 'source', 'transaction_date'],
                    'idx_transactions_source'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $existing = collect(DB::select('SHOW INDEXES FROM transactions'))
                ->pluck('Key_name')
                ->toArray();

            if (in_array('idx_transactions_source', $existing, true)) {
                $table->dropIndex('idx_transactions_source');
            }

            foreach (['commission_split', 'os_ref', 'source'] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
