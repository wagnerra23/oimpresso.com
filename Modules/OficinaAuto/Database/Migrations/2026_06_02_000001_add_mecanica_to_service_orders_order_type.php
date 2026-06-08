<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estende o enum service_orders.order_type pra incluir 'mecanica'.
 *
 * Contexto ([W] 2026-06-02 · port Kanban do carro): o fluxo REAL da oficina do
 * Martinho é manutenção/reparo de caminhão pesado (NÃO locação de caçamba — equívoco
 * legado corrigido pela ADR 0194). OS de carro novas nascem com order_type='mecanica'
 * e rodam no processo FSM `oficina_mecanica_os` (6 etapas) — ver OficinaAutoFsmSeeder.
 *
 * NÃO remapeia nem migra OS 'manutencao' legadas (preservam cacamba_manutencao,
 * sem orfanar current_stage_id) — decisão [W] "novo processo, não mexe no legado".
 *
 * Idempotente + reversível:
 *  - Só MySQL/MariaDB (SQLite trata enum como TEXT, 'mecanica' já cabe → no-op).
 *  - up(): só altera se 'mecanica' ainda não está no enum atual.
 *  - down(): reverte pra ('locacao','manutencao'), reclassificando linhas 'mecanica'
 *    pra 'manutencao' ANTES de estreitar o enum (evita erro de truncamento).
 *
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (seedMecanicaOsProcess)
 * @see app/Http/Controllers/ServiceOrderFsmActionController.php (ORDER_TYPE_TO_PROCESS)
 * @see memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'order_type')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return; // SQLite/outros: enum vira TEXT, qualquer string cabe.
        }

        $current = $this->currentEnumDefinition();
        if ($current !== null && str_contains($current, "'mecanica'")) {
            return; // já estendido — idempotente.
        }

        DB::statement(
            "ALTER TABLE service_orders MODIFY order_type "
            . "ENUM('locacao','manutencao','mecanica') NOT NULL DEFAULT 'manutencao' "
            . "COMMENT 'Tipo OS — locacao (sub-vertical 3 hipotético) | manutencao (legado cacamba) | mecanica (fluxo real reparo caminhão ADR 0194 · oficina_mecanica_os)'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'order_type')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Reclassifica antes de estreitar — evita truncamento de linhas 'mecanica'.
        DB::table('service_orders')->where('order_type', 'mecanica')->update(['order_type' => 'manutencao']);

        DB::statement(
            "ALTER TABLE service_orders MODIFY order_type "
            . "ENUM('locacao','manutencao') NOT NULL DEFAULT 'manutencao'"
        );
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
