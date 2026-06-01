<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 da [ADR 0236] — Conciliação passa a enxergar o extrato da API.
 *
 * Adiciona colunas de workflow de conciliação à `fin_extrato_lancamentos`
 * (que até aqui era read-only puro, espelho do banco via SyncBankStatementsJob).
 * É ADITIVO e idempotente: não altera nenhuma linha existente, não toca o
 * `ExtratoController` read-only nem o upsert do sync (que não menciona estas
 * colunas). `status` default NULL = "linha nunca avaliada pela conciliação".
 *
 * @see memory/decisions/0236-extrato-conciliacao-modelo-unificado.md
 * @see memory/requisitos/Financeiro/PLANO-FASE1-CONCILIACAO-LE-EXTRATO-API.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_extrato_lancamentos')) {
            return;
        }

        Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'status')) {
                // NULL = nunca avaliada (tratada como "a conciliar" na leitura).
                $table->enum('status', ['pendente', 'sugerido', 'conciliado', 'ignorado'])
                    ->nullable()
                    ->after('descricao');
            }
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'titulo_id')) {
                $table->unsignedInteger('titulo_id')->nullable()->after('status')
                    ->comment('FK pro Titulo quando conciliado (Fase 1 ADR 0236)');
            }
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'match_score')) {
                $table->decimal('match_score', 5, 2)->nullable()->after('titulo_id');
            }
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'conciliado_by')) {
                $table->unsignedInteger('conciliado_by')->nullable()->after('match_score');
            }
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'conciliado_at')) {
                $table->timestamp('conciliado_at')->nullable()->after('conciliado_by');
            }
        });

        // Index pros stats por status (separado pra poder checar existência).
        $hasIdx = collect(Schema::getIndexes('fin_extrato_lancamentos'))
            ->contains(fn ($i) => $i['name'] === 'fin_extrato_concil_status_idx');
        if (! $hasIdx) {
            Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
                $table->index(['business_id', 'status'], 'fin_extrato_concil_status_idx');
            });
        }

        // FK pro Titulo (set null no delete) — espelha fin_bank_statement_lines.
        $hasFk = collect(Schema::getForeignKeys('fin_extrato_lancamentos'))
            ->contains(fn ($f) => $f['name'] === 'fin_extrato_titulo_fk');
        if (! $hasFk && Schema::hasTable('fin_titulos')) {
            Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
                $table->foreign('titulo_id', 'fin_extrato_titulo_fk')
                    ->references('id')->on('fin_titulos')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fin_extrato_lancamentos')) {
            return;
        }

        Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
            $hasFk = collect(Schema::getForeignKeys('fin_extrato_lancamentos'))
                ->contains(fn ($f) => $f['name'] === 'fin_extrato_titulo_fk');
            if ($hasFk) {
                $table->dropForeign('fin_extrato_titulo_fk');
            }

            $hasIdx = collect(Schema::getIndexes('fin_extrato_lancamentos'))
                ->contains(fn ($i) => $i['name'] === 'fin_extrato_concil_status_idx');
            if ($hasIdx) {
                $table->dropIndex('fin_extrato_concil_status_idx');
            }

            foreach (['conciliado_at', 'conciliado_by', 'match_score', 'titulo_id', 'status'] as $col) {
                if (Schema::hasColumn('fin_extrato_lancamentos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
