<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona fin_titulos.legacy_id pra ponte com origens legacy (WR Comercial
 * Delphi/Firebird FINANCEIRO.CODIGO composto). Formaliza coluna que foi
 * adicionada via DDL direto em prod biz=1 durante a sessão Eliana 5-7/jun
 * (handoff 2026-06-07-0220).
 *
 * Idempotente: no-op em prod (coluna já existe) — drift fix retroativo.
 * Em dev/CI, cria a coluna pra Pest test passar.
 *
 * Ref: importer scripts/legacy-migration/import-financeiro.py:582 que faz
 * SELECT por (business_id, legacy_id) — coluna era requisito implícito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_titulos')) {
            return;
        }

        if (! Schema::hasColumn('fin_titulos', 'legacy_id')) {
            Schema::table('fin_titulos', function (Blueprint $table) {
                // string pra acomodar PK composta Firebird (CODPEDIDO-CODIGO-CODEMPRESA)
                $table->string('legacy_id', 100)->nullable()->after('origem_id');
                $table->index(['business_id', 'legacy_id'], 'idx_fin_titulos_business_legacy');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fin_titulos') && Schema::hasColumn('fin_titulos', 'legacy_id')) {
            Schema::table('fin_titulos', function (Blueprint $table) {
                $table->dropIndex('idx_fin_titulos_business_legacy');
                $table->dropColumn('legacy_id');
            });
        }
    }
};
