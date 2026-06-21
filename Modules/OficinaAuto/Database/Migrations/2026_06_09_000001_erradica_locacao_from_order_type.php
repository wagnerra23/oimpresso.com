<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Erradica `locacao` do enum service_orders.order_type (ADR 0265).
 *
 * Decisão de Wagner 2026-06-09 (dono do negócio, soberano do domínio): locação de
 * caçamba NÃO é processo da Oficina — é alucinação herdada do legado WR Sistemas. A
 * Oficina é REPARO/mecânica, ponto. Esta migration FECHA o resíduo que a ADR 0194
 * reclassificou mas deixou: `order_type` cai de {locacao, manutencao, mecanica} →
 * {manutencao, mecanica}.
 *
 * Idempotente + reversível + multi-tenant-safe (schema-level, todos os business):
 *  - Só MySQL/MariaDB (SQLite trata enum como TEXT → no-op; qualquer string cabe).
 *  - up(): (1) reclassifica linhas legadas order_type='locacao' → 'manutencao' ANTES de
 *    estreitar (evita truncamento); (2) só altera o enum se 'locacao' ainda está nele.
 *  - down(): reverte pra {locacao, manutencao, mecanica} (não desfaz o data-fix — não há
 *    como saber quais linhas eram 'locacao' originalmente).
 *
 * Preserva Tier 0: FSM ServiceOrder (ADR 0143), multi-tenant global scope (ADR 0093),
 * idempotência FB_LEGACY_ID do importer. NÃO toca as FSM keys disponivel/locada do
 * kanban (dívida F3, charter v4 PR #2417 — order_type ≠ keys de coluna).
 *
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 * @see memory/requisitos/OficinaAuto/RUNBOOK-erradicacao-locacao.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'order_type')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return; // SQLite/outros: enum vira TEXT, 'manutencao'/'mecanica' já cabem → no-op.
        }

        // (1) Reclassifica o resíduo ANTES de estreitar o enum (evita erro de truncamento).
        //     Cross-business de propósito: é migration de schema, não operação tenant-scoped.
        DB::table('service_orders')->where('order_type', 'locacao')->update(['order_type' => 'manutencao']);

        // (2) Só estreita se 'locacao' ainda está no enum — idempotente.
        $current = $this->currentEnumDefinition();
        if ($current !== null && str_contains($current, "'locacao'")) {
            DB::statement(
                "ALTER TABLE service_orders MODIFY order_type "
                . "ENUM('manutencao','mecanica') NOT NULL DEFAULT 'manutencao' "
                . "COMMENT 'Tipo OS — manutencao (legado/default) | mecanica (reparo caminhao ADR 0194). locacao ERRADICADO ADR 0265.'"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'order_type')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $current = $this->currentEnumDefinition();
        if ($current !== null && ! str_contains($current, "'locacao'")) {
            DB::statement(
                "ALTER TABLE service_orders MODIFY order_type "
                . "ENUM('locacao','manutencao','mecanica') NOT NULL DEFAULT 'manutencao'"
            );
        }
    }

    private function currentEnumDefinition(): ?string
    {
        try {
            $row = DB::selectOne('SHOW COLUMNS FROM service_orders WHERE Field = ?', ['order_type']);
            return $row->Type ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
};
