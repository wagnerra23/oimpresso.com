<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0188 — Contatos multi-type · flags aditivas.
 *
 * Adiciona 4 colunas bool aditivas em `contacts` mantendo coluna `type` enum
 * (UPOS legacy) como "papel principal" pra retrocompat com 200+ telas Blade.
 *
 * Schema:
 *   is_customer       TINYINT(1) NOT NULL DEFAULT 0  AFTER type
 *   is_supplier       TINYINT(1) NOT NULL DEFAULT 0  AFTER is_customer
 *   is_employee       TINYINT(1) NOT NULL DEFAULT 0  AFTER is_supplier
 *   is_representative TINYINT(1) NOT NULL DEFAULT 0  AFTER is_employee
 *
 * Backfill: papel principal (`type` UPOS) ganha flag correspondente `is_X=1`.
 * Backward-compat total: `type` permanece authoritative pra Sells/Compras/Folha
 * UPOS legacy. Frontend novo (Pages/Cliente Slot 2 PT-01) filtra via `is_X=1`.
 *
 * Índices compostos (`business_id`, `is_X`) preservam Tier 0 multi-tenant
 * IRREVOGÁVEL (ADR 0093) · todas queries continuam scoped por business.
 *
 * IDEMPOTENTE — Schema::hasColumn check protege re-execução.
 * down() reverte sem perda de dados (`type` enum não muda).
 *
 * @see memory/decisions/0188-contacts-multi-type-flag-aditiva.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'is_customer')) {
                $table->boolean('is_customer')->default(false)->after('type');
            }
            if (! Schema::hasColumn('contacts', 'is_supplier')) {
                $table->boolean('is_supplier')->default(false)->after('is_customer');
            }
            if (! Schema::hasColumn('contacts', 'is_employee')) {
                $table->boolean('is_employee')->default(false)->after('is_supplier');
            }
            if (! Schema::hasColumn('contacts', 'is_representative')) {
                $table->boolean('is_representative')->default(false)->after('is_employee');
            }
        });

        // Backfill: papel principal = type atual → flag correspondente.
        // Execução condicional pra idempotência (não re-backfilla se rerun).
        DB::table('contacts')->where('type', 'customer')->whereNull('is_customer')->update(['is_customer' => 1]);
        DB::table('contacts')->where('type', 'customer')->where('is_customer', 0)->update(['is_customer' => 1]);
        DB::table('contacts')->where('type', 'supplier')->where('is_supplier', 0)->update(['is_supplier' => 1]);
        DB::table('contacts')->where('type', 'employee')->where('is_employee', 0)->update(['is_employee' => 1]);
        DB::table('contacts')->where('type', 'representative')->where('is_representative', 0)->update(['is_representative' => 1]);

        // Índices compostos Tier 0 multi-tenant (ADR 0093 IRREVOGÁVEL).
        // business_id PRIMEIRO em todos · filtro is_X explora rapidez do índice.
        Schema::table('contacts', function (Blueprint $table) {
            $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                ->pluck('Key_name')
                ->toArray();

            if (! in_array('idx_contacts_biz_customer', $existing, true)) {
                $table->index(['business_id', 'is_customer'], 'idx_contacts_biz_customer');
            }
            if (! in_array('idx_contacts_biz_supplier', $existing, true)) {
                $table->index(['business_id', 'is_supplier'], 'idx_contacts_biz_supplier');
            }
            if (! in_array('idx_contacts_biz_employee', $existing, true)) {
                $table->index(['business_id', 'is_employee'], 'idx_contacts_biz_employee');
            }
            if (! in_array('idx_contacts_biz_representative', $existing, true)) {
                $table->index(['business_id', 'is_representative'], 'idx_contacts_biz_representative');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                ->pluck('Key_name')
                ->toArray();

            foreach ([
                'idx_contacts_biz_customer',
                'idx_contacts_biz_supplier',
                'idx_contacts_biz_employee',
                'idx_contacts_biz_representative',
            ] as $idx) {
                if (in_array($idx, $existing, true)) {
                    $table->dropIndex($idx);
                }
            }

            foreach (['is_representative', 'is_employee', 'is_supplier', 'is_customer'] as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
