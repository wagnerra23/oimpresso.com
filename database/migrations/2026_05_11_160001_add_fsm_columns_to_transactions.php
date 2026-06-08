<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-012 — Gate de emissão NFe por venda (ADR 0129 §Schema · pivot 2026-05-10).
 *
 * Adiciona FK lógica em `transactions` (CORE UltimatePOS) pra apontar:
 *   - `process_id`        → sale_processes.id (qual processo escolhido pra venda)
 *   - `current_stage_id`  → sale_process_stages.id (stage atual da venda)
 *
 * Pivot conceitual (Wagner 2026-05-10): "venda sem nota é caminho feliz, não falha".
 * Auto-emissão NFe deixa de ser flag global por business e vira opt-in por venda
 * via processo escolhido. Larissa (biz=4 RotaLivre, vestuário Gravatal/SC) talvez
 * nunca opt-in a NFC-e — venda fica em processo `venda_sem_nota` e listener no-op.
 *
 * NÃO adiciona FK física (compat legacy UPos — `transactions` tem ~37 colunas
 * acumuladas em 8 anos; FKs lá quebram migrations).
 *
 * Multi-tenant Tier 0 (ADR 0093): `transactions.business_id` já existe; o gate
 * de tenancy é feito no ExecuteStageActionService via subject.business_id ===
 * process.business_id (ver tests/Feature/Domain/Fsm/ExecuteStageActionServiceTest.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return; // ambiente Pest inline (transactions criada no beforeEach com colunas já)
        }

        Schema::table('transactions', function (Blueprint $t) {
            if (! Schema::hasColumn('transactions', 'process_id')) {
                $col = $t->unsignedBigInteger('process_id')->nullable();
                // SQLite ignora ->after() silently; MySQL respeita
                if (config('database.default') !== 'sqlite') {
                    $col->after('business_id');
                }
            }

            if (! Schema::hasColumn('transactions', 'current_stage_id')) {
                $col = $t->unsignedBigInteger('current_stage_id')->nullable();
                if (config('database.default') !== 'sqlite') {
                    $col->after('process_id');
                }
            }
        });

        // Index composto pra queries do tipo "vendas no stage X de biz Y"
        // (criar fora do closure pra suportar SQLite + MySQL)
        $indexExists = false;
        try {
            $idx = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes('transactions');
            $indexExists = isset($idx['transactions_biz_stage_idx']);
        } catch (\Throwable $e) {
            // Doctrine pode não estar disponível; silencia
        }

        if (! $indexExists) {
            try {
                Schema::table('transactions', function (Blueprint $t) {
                    $t->index(['business_id', 'current_stage_id'], 'transactions_biz_stage_idx');
                });
            } catch (\Throwable $e) {
                // duplicata ou tabela inline simples — ignora
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $t) {
            try {
                $t->dropIndex('transactions_biz_stage_idx');
            } catch (\Throwable $e) {}

            if (Schema::hasColumn('transactions', 'current_stage_id')) {
                $t->dropColumn('current_stage_id');
            }
            if (Schema::hasColumn('transactions', 'process_id')) {
                $t->dropColumn('process_id');
            }
        });
    }
};
