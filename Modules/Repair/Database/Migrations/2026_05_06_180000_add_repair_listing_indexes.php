<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 (MWART-0001) — índices compostos em `transactions` pra listagem
 * Repair via Inertia (`RepairController@index` dual-mode).
 *
 * Cada índice começa por `business_id` (multi-tenant first) e filtra pelo
 * domínio Repair (`sub_type='repair'`) através de `sub_type` na chave.
 *
 * Idempotente: testa SHOW INDEX antes de criar.
 *
 * Ver `memory/sprints/s2-os-listagem/02-schema-repair-indices.sql`.
 */
return new class extends Migration
{
    private array $indexes = [
        'idx_repair_biz_status_due'     => ['business_id', 'sub_type', 'repair_status_id', 'repair_due_date'],
        'idx_repair_biz_contact_created'=> ['business_id', 'sub_type', 'contact_id', 'created_at'],
        'idx_repair_biz_waiter_status'  => ['business_id', 'sub_type', 'res_waiter_id', 'repair_status_id'],
        'idx_repair_biz_creator_status' => ['business_id', 'sub_type', 'created_by', 'repair_status_id'],
        'idx_repair_biz_location_status'=> ['business_id', 'sub_type', 'location_id', 'repair_status_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        foreach ($this->indexes as $name => $cols) {
            if ($this->indexExists('transactions', $name)) {
                continue;
            }
            $colList = implode(', ', array_map(fn ($c) => "`{$c}`", $cols));
            DB::statement("CREATE INDEX `{$name}` ON `transactions` ({$colList})");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        foreach (array_keys($this->indexes) as $name) {
            if ($this->indexExists('transactions', $name)) {
                DB::statement("DROP INDEX `{$name}` ON `transactions`");
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }
};
