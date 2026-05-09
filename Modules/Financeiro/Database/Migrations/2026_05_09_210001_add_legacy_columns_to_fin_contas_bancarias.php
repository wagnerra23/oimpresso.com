<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas legacy_source/legacy_id em fin_contas_bancarias.
 *
 * Decisão: módulo Financeiro é territory próprio (não-core), permite
 * adicionar colunas direto sem bridge table — diferente de accounts core
 * (que usa bridge accounts_legacy_map, ADR 0118).
 *
 * Idempotência via UNIQUE (business_id, legacy_source, legacy_id) —
 * UPSERT quando re-importar.
 *
 * Multi-tenant Tier 0 (ADR 0093) — business_id já existe na tabela.
 */
class AddLegacyColumnsToFinContasBancarias extends Migration
{
    public function up(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->string('legacy_source', 50)->nullable()
                  ->after('metadata')
                  ->comment('Origem legacy: wr-comercial-delphi, bling, etc — null se cadastrada nativa no oimpresso');
            $table->string('legacy_id', 100)->nullable()
                  ->after('legacy_source')
                  ->comment('PK original no sistema legacy (string pra acomodar tipos diversos)');
            $table->timestamp('legacy_imported_at')->nullable()
                  ->after('legacy_id');

            // Idempotência multi-tenant
            $table->unique(
                ['business_id', 'legacy_source', 'legacy_id'],
                'uq_fin_cb_biz_source_legacy'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->dropUnique('uq_fin_cb_biz_source_legacy');
            $table->dropColumn(['legacy_source', 'legacy_id', 'legacy_imported_at']);
        });
    }
}
