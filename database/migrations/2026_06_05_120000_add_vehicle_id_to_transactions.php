<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0192 (extensão) — Veículo na venda direta de oficina.
 *
 * Schema aditivo: liga uma venda (transaction) a 1 veículo do catálogo
 * `vehicles` (Modules/OficinaAuto). Pareia com `source`/`os_ref` (migration
 * 2026_05_25_140000): aquela cobre a venda DERIVADA de OS (OS→Venda); esta
 * cobre a venda DIRETA de balcão de oficina, onde o vendedor seleciona/cadastra
 * o veículo no próprio /sells/create sem abrir OS.
 *
 *   vehicle_id  BIGINT UNSIGNED  NULL  AFTER os_ref
 *   FK vehicle_id → vehicles.id  ON DELETE SET NULL  (preserva a venda se o
 *                                                     veículo for removido)
 *   INDEX idx_transactions_vehicle (business_id, vehicle_id)
 *
 * nullable + default null retroativo = zero breaking change (vendas de
 * vestuário/balcão comum nunca têm veículo; a coluna fica NULL).
 *
 * Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL: business_id PRIMEIRO no índice;
 * todas queries continuam scoped por business global scope. O Vehicle model
 * já tem global scope por business_id — leak cross-tenant impossível.
 *
 * FK guardada por Schema::hasTable('vehicles'): se OficinaAuto não estiver
 * instalado num ambiente, a coluna é criada mesmo assim (fica sempre NULL) e
 * a FK é pulada — degrada gracioso sem quebrar a migration.
 *
 * IDEMPOTENTE — Schema::hasColumn + SHOW INDEXES checks protegem re-execução.
 * down() reverte sem perda de dados.
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/OficinaAuto/Entities/Vehicle.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'vehicle_id')) {
                $after = Schema::hasColumn('transactions', 'os_ref') ? 'os_ref' : 'id';
                $table->unsignedBigInteger('vehicle_id')
                    ->nullable()
                    ->after($after)
                    ->comment('Veículo do catálogo OficinaAuto na venda direta de oficina · ext ADR 0192');
            }
        });

        // FK só quando a tabela vehicles existe (OficinaAuto instalado).
        if (Schema::hasTable('vehicles')) {
            $fks = collect(DB::select('SHOW CREATE TABLE transactions'))
                ->first();
            $createSql = $fks->{'Create Table'} ?? '';
            if (! str_contains((string) $createSql, 'fk_transactions_vehicle')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreign('vehicle_id', 'fk_transactions_vehicle')
                        ->references('id')->on('vehicles')
                        ->nullOnDelete();
                });
            }
        }

        // Índice composto Tier 0 multi-tenant (ADR 0093): business_id PRIMEIRO.
        Schema::table('transactions', function (Blueprint $table) {
            $existing = collect(DB::select('SHOW INDEXES FROM transactions'))
                ->pluck('Key_name')
                ->toArray();

            if (! in_array('idx_transactions_vehicle', $existing, true)) {
                $table->index(['business_id', 'vehicle_id'], 'idx_transactions_vehicle');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $createSql = (string) (collect(DB::select('SHOW CREATE TABLE transactions'))->first()->{'Create Table'} ?? '');
            if (str_contains($createSql, 'fk_transactions_vehicle')) {
                $table->dropForeign('fk_transactions_vehicle');
            }

            $existing = collect(DB::select('SHOW INDEXES FROM transactions'))
                ->pluck('Key_name')
                ->toArray();
            if (in_array('idx_transactions_vehicle', $existing, true)) {
                $table->dropIndex('idx_transactions_vehicle');
            }

            if (Schema::hasColumn('transactions', 'vehicle_id')) {
                $table->dropColumn('vehicle_id');
            }
        });
    }
};
