<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-021 — 4 campos de data legacy OfficeImpresso/Delphi pra Lista de Vendas.
 *
 * Mapping Delphi → Laravel (ref: memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md §5):
 *   - DT_FATURAMENTO        → invoiced_at        (datetime nullable)
 *   - FATURAMENTO_DT_ENVIO  → invoice_sent_at    (datetime nullable)
 *   - DT_COMPETENCIA        → competence_date    (date nullable, default = transaction_date)
 *   - PROJETO_DT_FIM        → due_date           (date nullable — data prometida pro cliente)
 *
 * Campos NÃO criados (já existem):
 *   - DT_EMISSAO            → transactions.transaction_date (nativo UPos)
 *   - DT_ALTERACAO          → transactions.updated_at (nativo Laravel)
 *   - NF_DT_EMISSAO         → nfe_emissoes.emitido_em (JOIN, ver SellController@inertiaList)
 *
 * Índices: transaction_date (já indexado por UPos), invoiced_at, due_date
 * — header dropdown US-SELL-021 permite ordenar por qualquer um, e filtros
 * `date_from`/`date_to` (US-SELL-026 futura) vão atingir esses campos.
 *
 * Multi-tenant Tier 0 (ADR 0093): transactions.business_id já filtra; queries
 * sobre esses campos passam pelo global scope normalmente.
 *
 * Migration de dados (importer Firebird → Laravel) fica em US-SELL-027 (não escopo aqui).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return; // Pest inline — transactions criada no beforeEach com colunas só básicas
        }

        Schema::table('transactions', function (Blueprint $t) {
            if (! Schema::hasColumn('transactions', 'invoiced_at')) {
                $col = $t->dateTime('invoiced_at')->nullable()
                    ->comment('US-SELL-021 · DT_FATURAMENTO legacy — quando a venda foi faturada');
                if (config('database.default') !== 'sqlite') {
                    $col->after('transaction_date');
                }
            }

            if (! Schema::hasColumn('transactions', 'invoice_sent_at')) {
                $col = $t->dateTime('invoice_sent_at')->nullable()
                    ->comment('US-SELL-021 · FATURAMENTO_DT_ENVIO legacy — quando a fatura foi enviada ao cliente');
                if (config('database.default') !== 'sqlite') {
                    $col->after('invoiced_at');
                }
            }

            if (! Schema::hasColumn('transactions', 'competence_date')) {
                $col = $t->date('competence_date')->nullable()
                    ->comment('US-SELL-021 · DT_COMPETENCIA legacy — mês contábil de competência (≠ emissão)');
                if (config('database.default') !== 'sqlite') {
                    $col->after('invoice_sent_at');
                }
            }

            if (! Schema::hasColumn('transactions', 'due_date')) {
                $col = $t->date('due_date')->nullable()
                    ->comment('US-SELL-021 · PROJETO_DT_FIM legacy — data prometida pro cliente (entrega/serviço)');
                if (config('database.default') !== 'sqlite') {
                    $col->after('competence_date');
                }
            }
        });

        // Índices fora do closure pra suportar SQLite + MySQL.
        // transactions.transaction_date já é indexado por UPos core (não recria).
        $this->safeIndex('transactions', ['business_id', 'invoiced_at'], 'transactions_biz_invoiced_idx');
        $this->safeIndex('transactions', ['business_id', 'due_date'], 'transactions_biz_due_date_idx');
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        // Drop índices primeiro (alguns dialects exigem).
        try {
            Schema::table('transactions', function (Blueprint $t) {
                $t->dropIndex('transactions_biz_invoiced_idx');
            });
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Schema::table('transactions', function (Blueprint $t) {
                $t->dropIndex('transactions_biz_due_date_idx');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('transactions', function (Blueprint $t) {
            foreach (['due_date', 'competence_date', 'invoice_sent_at', 'invoiced_at'] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }

    private function safeIndex(string $table, array $cols, string $name): void
    {
        $exists = false;
        try {
            $idx = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table);
            $exists = isset($idx[$name]);
        } catch (\Throwable $e) {
            // Doctrine pode não estar disponível em SQLite — segue tentando
        }
        if (! $exists) {
            try {
                Schema::table($table, function (Blueprint $t) use ($cols, $name) {
                    $t->index($cols, $name);
                });
            } catch (\Throwable $e) {
                // duplicata, sqlite-inline, etc — ignora
            }
        }
    }
};
