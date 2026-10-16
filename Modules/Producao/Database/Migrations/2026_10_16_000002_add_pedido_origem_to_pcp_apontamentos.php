<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `origem = pedido`: pausa gravada pelo ERP quando a venda entra em espera (on_hold).
 * Mesmo papel de `turno`/`ponto`: fechamento que o operador não fez. Decisão [W] 2026-10-16.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcp_apontamentos') || DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE pcp_apontamentos MODIFY origem ENUM('qr','manual','turno','ponto','pedido') NOT NULL DEFAULT 'qr'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('pcp_apontamentos') || DB::getDriverName() !== 'mysql') {
            return;
        }
        if (DB::table('pcp_apontamentos')->where('origem', 'pedido')->exists()) {
            throw new RuntimeException('Há apontamentos com origem=pedido; a tabela é append-only, o enum não volta.');
        }
        DB::statement("ALTER TABLE pcp_apontamentos MODIFY origem ENUM('qr','manual','turno','ponto') NOT NULL DEFAULT 'qr'");
    }
};
