<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `contacts.legacy_id` (VARCHAR 32 nullable + INDEX composto biz_id+legacy_id).
 *
 * Pareada com `vehicles.legacy_id` (US-OFICINA-001 PR #556) — preserva chave
 * natural do legacy Delphi WR Comercial (CNPJ normalizado pra contacts /
 * EQUIPAMENTO_VEICULO.CODIGO pra vehicles).
 *
 * Bridge pra importer `scripts/legacy-migration/import-contacts-from-venda.py`:
 *  - SELECT id FROM contacts WHERE business_id=? AND legacy_id=? (CNPJ)
 *  - UPSERT idempotente: existente → UPDATE; novo → INSERT
 *
 * Multi-tenant Tier 0 (ADR 0093): índice composto (business_id, legacy_id)
 * garante lookup performante per-business. NUNCA usar legacy_id como UNIQUE
 * sozinho — mesmo CNPJ pode existir em N businesses distintos.
 *
 * Migration idempotente (Schema::hasColumn guard).
 *
 * Refs:
 *   - memory/reference/migracao-officeimpresso-pattern.md §FK convention drift
 *   - .claude/agents/migracao-firebird-versoes.md
 *   - ADR 0093 (multi-tenant Tier 0)
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'legacy_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('legacy_id', 32)
                    ->nullable()
                    ->after('contact_id')
                    ->comment('Chave natural legacy (CNPJ normalizado p/ Martinho/v1404, ou EMPRESA.CODIGO p/ WR2). Bridge importer-officeimpresso.');
            });

            // Índice composto pra lookup performante per-business no importer.
            $indexes = collect(\DB::select("SHOW INDEX FROM contacts"))->pluck('Key_name')->unique();
            if (! $indexes->contains('contacts_business_legacy_idx')) {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->index(['business_id', 'legacy_id'], 'contacts_business_legacy_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'legacy_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $indexes = collect(\DB::select("SHOW INDEX FROM contacts"))->pluck('Key_name')->unique();
                if ($indexes->contains('contacts_business_legacy_idx')) {
                    $table->dropIndex('contacts_business_legacy_idx');
                }
                $table->dropColumn('legacy_id');
            });
        }
    }
};
