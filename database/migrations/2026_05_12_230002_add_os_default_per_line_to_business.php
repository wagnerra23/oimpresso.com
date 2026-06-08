<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna `business.os_default_per_line BOOLEAN` — modo default de criação de OS.
 *
 * Cobre os 2 padrões de operação (validados em 2026-05-12):
 *   - **FALSE (default)** — 1 OS pra venda toda. Caso Martinho (caçambas):
 *     "vendi 3 caçambas pra Vargas, 1 OS de entrega cobre todas".
 *   - **TRUE** — 1 OS por linha de produto. Caso ComunicacaoVisual (gráfica):
 *     "vendi 5 banners + 1 placa = 6 OS, cada uma com produção/entrega independente".
 *
 * `CriarOsPorVendaService::criar(transaction, mode='auto')` lê esta coluna pra
 * decidir entre `single` e `per_line` quando o caller não força o mode.
 *
 * Schema decision: coluna boolean simples (não JSON `enabled_modules`/`common_settings`)
 * — facilita query, index, default explícito, alinha com pattern de outros toggles
 * UltimatePOS (`enable_inline_tax`, `enable_product_expiry`).
 *
 * ComunicacaoVisual (verticais gráfica) seta TRUE via seeder/admin UI.
 * Martinho (oficina caçambas) mantém FALSE (default).
 *
 * Multi-tenant Tier 0 (ADR 0093): coluna fica em `business`, scope global automático.
 *
 * Idempotente (Schema::hasColumn guard).
 *
 * @see app/Services/CriarOsPorVendaService.php
 * @see Modules/OficinaAuto/Database/Migrations/2026_05_12_230001_add_transaction_sell_line_id_to_service_orders.php
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('business', 'os_default_per_line')) {
            Schema::table('business', function (Blueprint $table) {
                $table->boolean('os_default_per_line')
                    ->default(false)
                    ->after('legacy_origin')
                    ->comment("Default mode pra criar OS a partir de venda: false=1 OS venda toda (Martinho), true=1 OS por linha (ComunicacaoVisual). CriarOsPorVendaService::criar() lê quando mode='auto'.");
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('business', 'os_default_per_line')) {
            Schema::table('business', function (Blueprint $table) {
                $table->dropColumn('os_default_per_line');
            });
        }
    }
};
