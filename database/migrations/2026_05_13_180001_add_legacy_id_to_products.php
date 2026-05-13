<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `products.legacy_id` (VARCHAR 32 nullable + INDEX composto biz_id+legacy_id).
 *
 * Pareada com `contacts.legacy_id` (PR #803) e `vehicles.legacy_id` (US-OFICINA-001 PR #556)
 * — preserva chave natural do legacy Delphi WR Comercial (`PRODUTO.CODIGO`).
 *
 * Bridge pra importer `scripts/legacy-migration/import-produtos.py`:
 *  - SELECT id FROM products WHERE business_id=? AND legacy_id=? (PRODUTO.CODIGO)
 *  - UPSERT idempotente: existente → UPDATE; novo → INSERT
 *
 * Multi-tenant Tier 0 (ADR 0093): índice composto (business_id, legacy_id)
 * garante lookup performante per-business. NUNCA usar legacy_id como UNIQUE
 * sozinho — mesmo CODIGO Delphi pode existir em N businesses distintos
 * (1 business = 1 Firebird origem).
 *
 * Migration idempotente (Schema::hasColumn guard).
 *
 * Refs:
 *   - memory/reference/migracao-officeimpresso-pattern.md
 *   - .claude/agents/migracao-produtos.md
 *   - scripts/legacy-migration/import-produtos.py
 *   - ADR 0093 (multi-tenant Tier 0)
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'legacy_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('legacy_id', 32)
                    ->nullable()
                    ->after('id')
                    ->comment('Chave natural legacy (PRODUTO.CODIGO Delphi WR Comercial). Bridge importer-produtos.');
            });

            // Índice composto pra lookup performante per-business no importer.
            $indexes = collect(\DB::select("SHOW INDEX FROM products"))->pluck('Key_name')->unique();
            if (! $indexes->contains('products_business_legacy_idx')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->index(['business_id', 'legacy_id'], 'products_business_legacy_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'legacy_id')) {
            Schema::table('products', function (Blueprint $table) {
                $indexes = collect(\DB::select("SHOW INDEX FROM products"))->pluck('Key_name')->unique();
                if ($indexes->contains('products_business_legacy_idx')) {
                    $table->dropIndex('products_business_legacy_idx');
                }
                $table->dropColumn('legacy_id');
            });
        }
    }
};
