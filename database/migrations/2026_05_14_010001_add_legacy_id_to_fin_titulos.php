<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `fin_titulos.legacy_id` (VARCHAR 32 nullable + INDEX composto business+legacy).
 *
 * Pareada com `contacts.legacy_id` + `vehicles.legacy_id` + `products.legacy_id` —
 * preserva chave natural Delphi (FINANCEIRO.CODIGO) pra idempotência do importer
 * `import-financeiro.py` (Wave Financeiro Martinho 2026-05-14).
 *
 * Bridge: `SELECT id FROM fin_titulos WHERE business_id=? AND legacy_id=?`
 *
 * Multi-tenant Tier 0 (ADR 0093): índice composto pra lookup performante.
 *
 * Idempotente (Schema::hasColumn guard).
 *
 * Refs:
 *   - memory/reference/migracao-officeimpresso-pattern.md §Fase 5 Financeiro
 *   - .claude/agents/migracao-firebird-versoes.md
 *   - ADR 0093
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('fin_titulos', 'legacy_id')) {
            Schema::table('fin_titulos', function (Blueprint $table) {
                $table->string('legacy_id', 32)
                    ->nullable()
                    ->after('numero')
                    ->comment('Chave natural FINANCEIRO.CODIGO Delphi pra dedup importer.');
            });

            $indexes = collect(\DB::select("SHOW INDEX FROM fin_titulos"))->pluck('Key_name')->unique();
            if (! $indexes->contains('fin_titulos_business_legacy_idx')) {
                Schema::table('fin_titulos', function (Blueprint $table) {
                    $table->index(['business_id', 'legacy_id'], 'fin_titulos_business_legacy_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fin_titulos', 'legacy_id')) {
            Schema::table('fin_titulos', function (Blueprint $table) {
                $indexes = collect(\DB::select("SHOW INDEX FROM fin_titulos"))->pluck('Key_name')->unique();
                if ($indexes->contains('fin_titulos_business_legacy_idx')) {
                    $table->dropIndex('fin_titulos_business_legacy_idx');
                }
                $table->dropColumn('legacy_id');
            });
        }
    }
};
