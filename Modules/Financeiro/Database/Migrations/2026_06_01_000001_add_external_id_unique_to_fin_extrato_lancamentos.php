<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 da [ADR 0236] — UNIQUE unificado em fin_extrato_lancamentos.
 *
 * Cria UNIQUE (business_id, conta_bancaria_id, external_id) — a chave
 * anti-duplicata que serve as DUAS origens via external_id prefixado
 * ("ofx:<fitid>" / "api:<idempotency_key>").
 *
 * ⚠️ DEVE rodar DEPOIS de `financeiro:backfill-extrato-ofx` em TODOS os business
 * (external_id preenchido em todas as linhas). Se houver linha com external_id
 * NULL, esta migration FALHA de propósito (guard abaixo) — sinaliza backfill
 * incompleto. NÃO força: prefere abortar a criar UNIQUE sobre dados inconsistentes.
 *
 * Separada da migration de colunas (2026_06_01_000000) justamente pra permitir
 * a janela: colunas → backfill → UNIQUE. Cada passo seguro e reversível.
 *
 * @see memory/requisitos/Financeiro/PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_extrato_lancamentos')
            || ! Schema::hasColumn('fin_extrato_lancamentos', 'external_id')) {
            return;
        }

        // Guard: aborta se houver external_id NULL (backfill incompleto).
        $nulos = DB::table('fin_extrato_lancamentos')->whereNull('external_id')->count();
        if ($nulos > 0) {
            throw new \RuntimeException(
                "fin_extrato_lancamentos tem {$nulos} linha(s) com external_id NULL. "
                . 'Rode `financeiro:backfill-extrato-ofx --business=<id>` em TODOS os business '
                . 'antes desta migration (UNIQUE exige external_id preenchido).'
            );
        }

        $hasUnique = collect(Schema::getIndexes('fin_extrato_lancamentos'))
            ->contains(fn ($i) => $i['name'] === 'fin_extrato_external_unique');
        if (! $hasUnique) {
            Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
                $table->unique(
                    ['business_id', 'conta_bancaria_id', 'external_id'],
                    'fin_extrato_external_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fin_extrato_lancamentos')) {
            return;
        }

        $hasUnique = collect(Schema::getIndexes('fin_extrato_lancamentos'))
            ->contains(fn ($i) => $i['name'] === 'fin_extrato_external_unique');
        if ($hasUnique) {
            Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
                $table->dropUnique('fin_extrato_external_unique');
            });
        }
    }
};
